<?php

namespace KK\Validation;

use Closure;
use InvalidArgumentException;
use SplFileInfo;
use SplPriorityQueue;
use function array_map;
use function count;
use function ctype_space;
use function explode;
use function filter_var;
use function implode;
use function is_array;
use function is_countable;
use function is_null;
use function is_numeric;
use function is_string;
use function ksort;
use function ltrim;
use function mb_strlen;
use function sprintf;
use function str_replace;
use function strtolower;
use function trim;
use function ucwords;

class ValidationRuleset
{
    protected const FLAG_SOMETIMES = 1 << 0;
    protected const FLAG_REQUIRED = 1 << 1;
    protected const FLAG_NULLABLE = 1 << 2;

    protected const PRIORITY_MAP = [
        'required' => 10,
        'numeric' => 100,
        'integer' => 100,
        'string' => 100,
        'array' => 100,
        'min' => 1,
        'max' => 1,
        'sometimes' => 0,
        'nullable' => 0,
        'bail' => 0,
    ];

    protected const TYPED_RULES = [
        /* note: integer should be in front of numeric,
        /* because it is more specific */
        'integer',
        'numeric',
        'string',
        'array'
    ];

    /** @var int base flags */
    protected int $flags;

    /** @var ValidationRule[] */
    protected array $rules;

    /** @var static[] */
    protected static array $pool = [];

    /** @var Closure[] */
    protected static array $closureCache = [];

    public static function make(string $ruleString): static
    {
        $ruleMap = static::convertRuleStringToRuleMap($ruleString);
        $hash = static::getHashOfRuleMap($ruleMap);
        return static::$pool[$hash] ?? (static::$pool[$hash] = new static($ruleMap));
    }

    protected function __construct(array $ruleMap)
    {
        $flags = 0;
        $rules = [];

        foreach ($ruleMap as $rule => $ruleArgs) {
            if ($rule === 'sometimes') {
                $flags |= static::FLAG_SOMETIMES;
            } elseif ($rule === 'required') {
                $flags |= static::FLAG_REQUIRED;
                if (!isset($ruleMap['numeric']) && !isset($ruleMap['integer'])) {
                    $rules[] = ValidationRule::make('required', static::getClosure('validateRequired' . static::fetchTypedRule($ruleMap)));
                }
            } elseif ($rule === 'nullable') {
                $flags |= static::FLAG_NULLABLE;
            } elseif ($rule === 'numeric') {
                if (isset($ruleMap['array'])) {
                    throw new InvalidArgumentException("Rule 'numeric' conflicts with 'array'");
                }
                $rules[] = ValidationRule::make('numeric', static::getClosure('validateNumeric'));
            } elseif ($rule === 'integer') {
                $rules[] = ValidationRule::make('integer', static::getClosure('validateInteger'));
            } elseif ($rule === 'string') {
                if (isset($ruleMap['array'])) {
                    throw new InvalidArgumentException("Rule 'string' conflicts with 'array'");
                }
                $rules[] = ValidationRule::make('string', static::getClosure('validateString'));
            } elseif ($rule === 'array') {
                $rules[] = ValidationRule::make('array', static::getClosure('validateArray'));
            } elseif ($rule === 'min' || $rule === 'max') {
                if (count($ruleArgs) !== 1) {
                    throw new InvalidArgumentException("Rule '{$rule}' require 1 parameter at least");
                }
                if (!is_numeric($ruleArgs[0])) {
                    throw new InvalidArgumentException("Rule '{$rule}' require numeric parameters");
                }
                $ruleArgs[0] += 0;
                $name = "{$rule}:{$ruleArgs[0]}";
                $methodPart = $rule === 'min' ? 'Min' : 'Max';
                $rules[] = ValidationRule::make($name, static::getClosure("validate{$methodPart}" . static::fetchTypedRule($ruleMap)), $ruleArgs);
            } elseif ($rule !== 'bail') { /* compatibility */
                throw new InvalidArgumentException("Unknown rule '{$rule}'");
            }
        }

        $this->flags = $flags;
        $this->rules = $rules;
    }

    public function isDefinitelyRequired(): bool
    {
        return ($this->flags & static::FLAG_REQUIRED) && !($this->flags & static::FLAG_SOMETIMES);
    }

    /**
     * @return string[] Error attribute names
     */
    public function check(mixed $data): array
    {
        if (($this->flags & static::FLAG_NULLABLE) && $data === null) {
            return [];
        }

        $errors = [];

        foreach ($this->rules as $rule) {
            $closure = $rule->closure;
            $valid = $closure($data, ...$rule->args);
            if (!$valid) {
                $errors[] = $rule->name;
                /* Always bail here, for example:
                 * if we have a rule like `integer|max:255`,
                 * then user input a string `x`, it's not even an integer,
                 * continue to check its' length is meaningless,
                 * in Laravel validation, it may violate `max:255` when
                 * string length is longer than 255, how fool it is. */
                break;
            }
        }

        return $errors;
    }

    protected static function getHashOfRuleMap(array $ruleMap): string
    {
        $hashSlots = [];
        ksort($ruleMap);
        foreach ($ruleMap as $rule => $ruleArgs) {
            if ($ruleArgs === []) {
                $hashSlots[] = $rule;
            } else {
                $hashSlots[] = sprintf('%s:%s', $rule, implode(', ', $ruleArgs));
            }
        }
        return implode('|', $hashSlots);
    }

    protected static function convertRuleStringToRuleMap(string $ruleString): array
    {
        $rules = array_map('trim', explode('|', $ruleString));
        $ruleQueue = new SplPriorityQueue();
        $ruleMap = [];
        foreach ($rules as $rule) {
            $ruleParts = explode(':', $rule, 2);
            $rule = strtolower(trim($ruleParts[0]));
            if (!isset(static::PRIORITY_MAP[$rule])) {
                throw new InvalidArgumentException("Unknown rule '{$rule}'");
            }
            if (($ruleParts[1] ?? '') !== '') {
                $ruleArgs = array_map('trim', explode(',', $ruleParts[1]));
            } else {
                $ruleArgs = [];
            }
            $ruleQueue->insert([$rule, $ruleArgs], static::PRIORITY_MAP[$rule]);
        }
        while (!$ruleQueue->isEmpty()) {
            [$rule, $ruleArgs] = $ruleQueue->extract();
            if (isset($ruleMap[$rule])) {
                throw new InvalidArgumentException("Duplicated rule '{$rule}' in ruleset '{$ruleString}'");
            }
            $ruleMap[$rule] = $ruleArgs;
        }
        return $ruleMap;
    }

    protected static function upperCamelize(string $uncamelized_words, string $separator = '_'): string
    {
        $uncamelized_words = str_replace($separator, ' ', strtolower($uncamelized_words));
        return ltrim(str_replace(' ', '', ucwords($uncamelized_words)), $separator);
    }

    protected static function fetchTypedRule(array $ruleMap): string
    {
        foreach (static::TYPED_RULES as $typedRule) {
            if (isset($ruleMap[$typedRule])) {
                return static::upperCamelize($typedRule);
            }
        }

        return '';
    }

    protected static function getClosure(string $method): Closure
    {
        return static::$closureCache[$method] ??
            (static::$closureCache[$method] = Closure::fromCallable([static::class, $method]));
    }

    protected static function validateRequired(mixed $value): bool
    {
        if (is_null($value)) {
            return false;
        }
        if (is_string($value) && ($value === '' || ctype_space($value))) {
            return false;
        }
        if (is_countable($value) && count($value) === 0) {
            return false;
        }
        if ($value instanceof SplFileInfo) {
            return $value->getPath() !== '';
        }

        return true;
    }

    protected static function validateRequiredString(mixed $value): bool
    {
        return $value !== '' && !ctype_space($value);
    }

    protected static function validateRequiredArray(array $value): bool
    {
        return count($value) !== 0;
    }

    protected static function validateRequiredFile(SplFileInfo $value): bool
    {
        return $value->getPath() !== '';
    }

    protected static function validateNumeric(mixed &$value): bool
    {
        if (!is_numeric($value)) {
            return false;
        }
        $value += 0;
        return true;
    }

    protected static function validateInteger(mixed &$value): bool
    {
        if (filter_var($value, FILTER_VALIDATE_INT) === false) {
            return false;
        }
        $value = (int) $value;
        return true;
    }

    protected static function validateString(mixed $value): bool
    {
        return is_string($value);
    }

    protected static function validateArray(mixed $value): bool
    {
        return is_array($value);
    }

    protected static function getLength(mixed $value): int
    {
        if (is_numeric($value)) {
            return $value + 0;
        } elseif (is_string($value)) {
            return mb_strlen($value);
        } elseif (is_array($value)) {
            return count($value);
        } elseif ($value === null) {
            return 0;
        } elseif ($value instanceof SplFileInfo) {
            return $value->getSize();
        }

        return mb_strlen((string) $value);
    }

    protected static function validateMin(mixed $value, int|float $min): bool
    {
        // TODO: file min support b, kb, mb, gb ...
        return static::getLength($value) >= $min;
    }

    protected static function validateMax(mixed $value, int|float $max): bool
    {
        // TODO: file max support b, kb, mb, gb ...
        return static::getLength($value) <= $max;
    }

    protected static function validateMinInteger(int $value, int|float $min): bool
    {
        return $value >= $min;
    }

    protected static function validateMaxInteger(int $value, int|float $max): bool
    {
        return $value <= $max;
    }

    protected static function validateMinNumeric(int|float $value, int|float $min): bool
    {
        return $value >= $min;
    }

    protected static function validateMaxNumeric(int|float $value, int|float $max): bool
    {
        return $value <= $max;
    }

    protected static function validateMinString(string $value, int|float $min): bool
    {
        return mb_strlen($value) >= $min;
    }

    protected static function validateMaxString(string $value, int|float $max): bool
    {
        return mb_strlen($value) <= $max;
    }
}
