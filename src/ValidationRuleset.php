<?php

namespace KK\Validator;

use Closure;
use InvalidArgumentException;
use SplFileInfo;
use function array_map;
use function count;
use function explode;
use function filter_var;
use function implode;
use function is_array;
use function is_countable;
use function is_null;
use function is_numeric;
use function is_string;
use function ksort;
use function mb_strlen;
use function sprintf;
use function strtolower;
use function trim;

class ValidationRuleset
{
    protected const FLAG_SOMETIMES = 1 << 0;
    protected const FLAG_REQUIRED = 1 << 1;
    protected const FLAG_NULLABLE = 1 << 2;
    protected const FLAG_BAIL = 1 << 3;

    /** @var int base flags */
    protected int $flags;

    /** @var Closure[] */
    protected array $validatesAttributes = [];
    protected array $validatesAttributesArgs = [];

    protected static array $pool = [];

    public static function make(string $ruleString): static
    {
        $ruleMap = static::convertRuleStringToRuleMap($ruleString);
        $hash = static::getHashOfRuleMap($ruleMap);
        if (!isset(static::$pool[$hash])) {
            $ruleset = static::$pool[$hash] = new static($ruleMap);
        } else {
            $ruleset = static::$pool[$hash];
        }
        return $ruleset;
    }

    protected function __construct(array $ruleMap)
    {
        $flags = 0;
        $validatesAttributes = [];
        $validatesAttributesArgs = [];

        foreach ($ruleMap as $rule => $ruleArgs) {
            $rule = strtolower($rule);
            if ($rule == 'sometimes') {
                $flags |= static::FLAG_SOMETIMES;
            } elseif ($rule == 'required') {
                $flags |= static::FLAG_REQUIRED;
                $validatesAttributes[$rule] = Closure::fromCallable([static::class, 'validateRequired']);
            } elseif ($rule == 'nullable') {
                $flags |= static::FLAG_NULLABLE;
            } elseif ($rule == 'numeric') {
                $validatesAttributes[$rule] = Closure::fromCallable([static::class, 'validateNumeric']);
            } elseif ($rule == 'int' || $rule == 'integer') {
                $validatesAttributes[$rule] = Closure::fromCallable([static::class, 'validateInt']);
            } elseif ($rule == 'string') {
                $validatesAttributes[$rule] = Closure::fromCallable([static::class, 'validateString']);
            } elseif ($rule == 'array') {
                $validatesAttributes[$rule] = Closure::fromCallable([static::class, 'validateArray']);
            } elseif ($rule === 'min' || $rule === 'max') {
                if (count($ruleArgs) !== 1) {
                    throw new InvalidArgumentException("Rule {$rule} require 1 parameter");
                }
                if ($rule === 'min') {
                    $validatesAttribute = Closure::fromCallable([static::class, 'validateMin']);
                } else /* if ($ruleParts[0] === 'max') */ {
                    $validatesAttribute = Closure::fromCallable([static::class, 'validateMax']);
                }
                $validatesAttributes[$rule] = $validatesAttribute;
                $validatesAttributesArgs[$rule] = $ruleArgs;
            } elseif ($rule == 'bail') {
                $flags |= static::FLAG_BAIL;
            } else {
                throw new InvalidArgumentException("Unknown rule part '{$rule}'");
            }
        }

        $this->flags = $flags;
        $this->validatesAttributes = $validatesAttributes;
        $this->validatesAttributesArgs = $validatesAttributesArgs;
    }

    public function isDefinitelyRequired(): bool
    {
        return ($this->flags & static::FLAG_REQUIRED) && !($this->flags & static::FLAG_SOMETIMES);
    }

    /**
     * @return string[] Error attribute names
     */
    public function validate(mixed $data): array
    {
        if (($this->flags & static::FLAG_NULLABLE) && $data === null) {
            return [];
        }

        $errors = [];
        $argsMap = $this->validatesAttributesArgs;
        foreach ($this->validatesAttributes as $name => $attribute) {
            $valid = $attribute($data, ...($argsMap[$name] ?? []));
            if (!$valid) {
                $errors[] = $name;
                if ($this->flags & static::FLAG_BAIL) {
                    break;
                }
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
                $hashSlots[] = sprintf("%s:%s", $rule, implode(', ', $ruleArgs));
            }
        }
        return implode('|', $hashSlots);
    }

    protected static function convertRuleStringToRuleMap(string $ruleString): array
    {
        $rules = array_map('trim', explode('|', $ruleString));
        $ruleMap = [];
        foreach ($rules as $rule) {
            $ruleParts = explode(':', $rule, 2);
            $rule = trim($ruleParts[0]);
            if (isset($ruleMap[$rule])) {
                throw new InvalidArgumentException("Duplicated rule '{$rule}' in ruleset '{$ruleString}'");
            }
            if (($ruleParts[1] ?? '') !== '') {
                $ruleArgs = array_map('trim', explode(',', $ruleParts[1]));
                $ruleMap[$rule] = $ruleArgs;
            } else {
                $ruleMap[$rule] = [];
            }
        }
        return $ruleMap;
    }

    protected static function validateRequired(mixed $value): bool
    {
        if (is_null($value)) {
            return false;
        }
        if (is_string($value) && trim($value) === '') {
            return false;
        }
        if (is_countable($value) && count($value) < 1) {
            return false;
        }
        if ($value instanceof SplFileInfo) {
            return $value->getPath() !== '';
        }

        return true;
    }

    protected static function validateNumeric(mixed $value): bool
    {
        return is_numeric($value);
    }

    protected static function validateInt(mixed $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_INT) !== false;
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
            $length = $value + 0;
        } elseif (is_string($value)) {
            $length = mb_strlen($value);
        } elseif (is_array($value)) {
            $length = count($value);
        } elseif ($value instanceof SplFileInfo) {
            $length = $value->getSize();
        } else {
            $length = mb_strlen((string) $value);
        }
        return $length;
    }

    protected static function validateMin(mixed $value, string $min): bool
    {
        // TODO: file min support b, kb, mb, gb ...
        return static::getLength($value) >= $min;
    }

    protected static function validateMax(mixed $value, $max): bool
    {
        // TODO: file max support b, kb, mb, gb ...
        return static::getLength($value) <= $max;
    }
}