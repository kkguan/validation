<?php

namespace KK;

use InvalidArgumentException;
use KK\Validator\ValidationPair;
use KK\Validator\ValidationRuleset;
use function array_key_exists;
use function array_map;
use function array_merge;
use function count;
use function explode;
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
                $pattern, $ruleset
            );
        }
        $this->validationPairs = $validationPairs;
    }

    public function valid(array $data): array
    {
        $this->errors = [];

        return $this->validRecursive($data, $this->validationPairs);
    }

    /**
     * Get last errors
     * @return string[][]
     */
    public function errors(): array
    {
        return $this->errors;
    }

    /**
     * @param array $data
     * @param ValidationPair[] $validationPairs
     * @param string[] $currentDir
     * @return array
     */
    protected function validRecursive(array $data, array $validationPairs, array $currentDir = []): array
    {
        $this->currentDir = $currentDir;

        $deeperValidationPairsMap = [];
        $wildcardValidationPairs = [];
        $invalid = false;

        foreach ($validationPairs as $validationPair) {
            $patternParts = $validationPair->getPatternParts();
            $diffLevel = count($patternParts) - count($currentDir) - 1;
            if ($diffLevel !== 0) {
                $deeperPatternPart = $patternParts[count($currentDir)];
                if ($deeperPatternPart !== '*') {
                    $deeperValidationPairsMap[$deeperPatternPart][] = $validationPair;
                } else {
                    $wildcardValidationPairs[] = $validationPair;
                }
                continue;
            }
            $ruleset = $validationPair->getRuleset();
            $currentPatternPart = $patternParts[count($patternParts) - 1];
            if ($currentPatternPart === '*') {
                foreach ($data as $key => $value) {
                    $errors = $ruleset->validate($value);
                    if ($errors) {
                        $this->recordErrors($key, $errors);
                        $invalid = true;
                    }
                }
            } else {
                /* Check required fields */
                if (!array_key_exists($currentPatternPart, $data)) {
                    if ($ruleset->isDefinitelyRequired()) {
                        $this->recordError($currentPatternPart, 'required');
                        $invalid = true;
                    }
                    continue;
                }
                $value = $data[$currentPatternPart];
                $errors = $ruleset->validate($value);
                if ($errors) {
                    $this->recordErrors($currentPatternPart, $errors);
                    $invalid = true;
                }
            }
        }

        foreach ($deeperValidationPairsMap as $deeperPatternPart => $deeperValidationPairs) {
            $value = $data[$deeperPatternPart] ?? null;
            if (!is_array($value)) {
                /* required but not found | not definitely required | nullable */
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

        if ($wildcardValidationPairs !== []) {
            foreach ($data as $key => $value) {
                $nextDir = $currentDir;
                $nextDir[] = $key;
                if (!is_array($value)) {
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
        $this->errors[$fullPath] = array_merge($this->errors[$fullPath], $errors);
    }

    protected static function generateFullPath(array $dir, string $key): string
    {
        return implode('.', $dir) . ".{$key}";
    }
}
