<?php

namespace KK\Validator;

use Closure;
use InvalidArgumentException;
use SplFileInfo;
use function array_map;
use function count;
use function explode;
use function filter_var;
use function is_array;
use function is_countable;
use function is_null;
use function is_numeric;
use function is_string;
use function mb_strlen;
use function str_starts_with;
use function strtolower;
use function trim;

class ValidationRuleset
{
    protected const FLAG_SOMETIMES = 1 << 0;
    protected const FLAG_REQUIRED = 1 << 1;
    protected const FLAG_NULLABLE = 1 << 2;
    protected const FLAG_BAIL = 1 << 3;

    /**
     * @var int base flags
     */
    protected int $flags;

    /** @var Closure[] */
    protected array $validatesAttributes = [];
    protected array $validatesAttributesArgsMap = [];

    public function __construct(string $ruleString)
    {
        $flags = 0;
        $validatesAttributes = [];
        $validatesAttributesArgs = [];
        $ruleParts = array_map('trim', explode('|', $ruleString));
        foreach ($ruleParts as $rolePart) {
            $rolePart = strtolower($rolePart);
            if ($rolePart == 'sometimes') {
                $flags |= static::FLAG_SOMETIMES;
            } elseif ($rolePart == 'required') {
                $flags |= static::FLAG_REQUIRED;
                $validatesAttributes[$rolePart] = Closure::fromCallable([static::class, 'validateRequired']);
            } elseif ($rolePart == 'nullable') {
                $flags |= static::FLAG_NULLABLE;
            } elseif ($rolePart == 'numeric') {
                $validatesAttributes[$rolePart] = Closure::fromCallable([static::class, 'validateNumeric']);
            } elseif ($rolePart == 'int' || $rolePart == 'integer') {
                $validatesAttributes[$rolePart] = Closure::fromCallable([static::class, 'validateInt']);
            } elseif ($rolePart == 'string') {
                $validatesAttributes[$rolePart] = Closure::fromCallable([static::class, 'validateString']);
            } elseif ($rolePart == 'array') {
                $validatesAttributes[$rolePart] = Closure::fromCallable([static::class, 'validateArray']);
            } elseif (str_starts_with($rolePart, 'min:') || str_starts_with($rolePart, 'max:')) {
                $rolePartParts = explode(':', $rolePart, 2);
                if (count($rolePartParts) !== 2) {
                    throw new InvalidArgumentException("Invalid rule part {$rolePart}");
                }
                if ($rolePartParts[0] === 'min') {
                    $validatesAttribute = Closure::fromCallable([static::class, 'validateMin']);
                } else /* if ($rolePartParts[0] === 'max') */ {
                    $validatesAttribute = Closure::fromCallable([static::class, 'validateMax']);
                }
                $validatesAttributes[$rolePart] = $validatesAttribute;
                $validatesAttributesArgs[$rolePart] = [trim($rolePartParts[1])];
            } elseif ($rolePart == 'bail') {
                $flags |= static::FLAG_BAIL;
            } else {
                throw new InvalidArgumentException("Unknown role part '{$rolePart}'");
            }
        }
        $this->flags = $flags;
        $this->validatesAttributes = $validatesAttributes;
        $this->validatesAttributesArgsMap = $validatesAttributesArgs;
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
        $argsMap = $this->validatesAttributesArgsMap;
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