<?php

declare(strict_types=1);
/**
 *  本文件属于KK馆版权所有。
 *  This file belong to KKGUAN.
 */

namespace KK\Validation;

use function array_key_exists;
use function array_merge;
use function count;
use function implode;
use function is_array;

class Validator
{
    /** @var ValidationPair[] */
    protected array $validationPairs = [];

    /** @var string[] */
    protected array $currentDir = [];

    /** @var string[][] */
    protected array $errors = [];

    public function __construct(array $rules)
    {
        $validationPairs = [];
        foreach ($rules as $pattern => $ruleString) {
            $ruleset = ValidationRuleset::make($ruleString);
            $validationPairs[] = new ValidationPair(
                $pattern,
                $ruleset
            );
        }
        $this->validationPairs = $validationPairs;
    }

    public function valid(array $data): array
    {
        $this->errors = [];

        return $this->validRecursive($data, $this->validationPairs);
    }

    /** @throws ValidationException */
    public function validate(array $data): array
    {
        $result = $this->valid($data);
        if (! empty($this->errors)) {
            throw new ValidationException($this->errors);
        }

        return $result;
    }

    /**
     * Get last errors.
     * @return string[][]
     */
    public function errors(): array
    {
        return $this->errors;
    }

    /**
     * @return ValidationPair[]
     */
    public function getValidationPairs(): array
    {
        return $this->validationPairs;
    }

    /**
     * @param ValidationPair[] $validationPairs
     * @param string[] $currentDir
     */
    protected function validRecursive(array $data, array $validationPairs, array $currentDir = []): array
    {
        $this->currentDir = $currentDir;
        $currentLevel = count($currentDir);

        $deeperValidationPairsMap = [];
        $wildcardValidationPairs = [];
        $invalid = false;

        /* Filter out and verify the rules that match the current level */
        foreach ($validationPairs as $validationPair) {
            $patternParts = $validationPair->patternParts;
            $validationLevel = count($patternParts);
            $diffLevel = $validationLevel - $currentLevel - 1;
            if ($diffLevel !== 0) {
                $deeperPatternPart = $patternParts[$currentLevel];
                if ($deeperPatternPart !== '*') {
                    $deeperValidationPairsMap[$deeperPatternPart][] = $validationPair;
                } else {
                    $wildcardValidationPairs[] = $validationPair;
                }
                continue;
            }
            $ruleset = $validationPair->ruleset;
            $currentPatternPart = $patternParts[$validationLevel - 1];
            if ($currentPatternPart === '*') {
                foreach ($data as $key => $value) {
                    $errors = $ruleset->check($value);
                    if ($errors) {
                        $this->recordErrors($key, $errors);
                        $invalid = true;
                    }
                }
            } else {
                /* Check required fields */
                if (! array_key_exists($currentPatternPart, $data)) {
                    if ($ruleset->isDefinitelyRequired()) {
                        $this->recordError($currentPatternPart, 'required');
                        $invalid = true;
                    }

                    if ($errors = $ruleset->check(null, $data, 'required_if')) {
                        $this->recordErrors($currentPatternPart, $errors);
                        $invalid = true;
                    }

                    continue;
                }
                $value = $data[$currentPatternPart];
                $errors = $ruleset->check($value, $data);
                if ($errors) {
                    $this->recordErrors($currentPatternPart, $errors);
                    $invalid = true;
                }
            }
        }

        /* go deeper first, some invalid data will be removed */
        foreach ($deeperValidationPairsMap as $deeperPatternPart => $deeperValidationPairs) {
            $value = $data[$deeperPatternPart] ?? null;
            if ($value === null) {
                /* required but not found | not definitely required | nullable */
                continue;
            }
            if (! is_array($value)) {
                $this->recordError($deeperPatternPart, 'array');
                $invalid = true;
                continue;
            }
            $nextDir = $currentDir;
            $nextDir[] = $deeperPatternPart;
            $ret = $this->validRecursive($value, $deeperValidationPairs, $nextDir);
            if ($ret !== []) {
                $data[$deeperPatternPart] = $ret;
            } else {
                unset($data[$deeperPatternPart]);
            }
        }

        /* Apply wildcard rule after deeper check, data was cleaned up */
        if ($wildcardValidationPairs !== []) {
            foreach ($data as $key => $value) {
                $nextDir = $currentDir;
                $nextDir[] = $key;
                if (! is_array($value)) {
                    $this->recordError($key, 'array');
                    $invalid = true;
                    continue;
                }
                $ret = $this->validRecursive($value, $wildcardValidationPairs, $nextDir);
                if ($ret !== []) {
                    $data[$key] = $ret;
                } else {
                    unset($data[$key]);
                }
            }
        }

        if ($invalid) {
            return [];
        }

        return $data;
    }

    protected function recordError(string $key, string $error): void
    {
        $this->errors[static::generateFullPath($this->currentDir, $key)][] = $error;
    }

    protected function recordErrors(string $key, array $errors): void
    {
        $fullPath = static::generateFullPath($this->currentDir, $key);
        $this->errors[$fullPath] = array_merge($this->errors[$fullPath] ?? [], $errors);
    }

    protected static function generateFullPath(array $dir, string $key): string
    {
        $path = [...$dir, $key];
        return implode('.', $path);
    }
}
