<?php

namespace KK\Validator;

use InvalidArgumentException;
use function array_map;
use function count;
use function explode;
use function implode;

class ValidationPair
{
    protected string $pattern;
    protected array $patternParts;

    public function __construct(
        string $pattern,
        protected ValidationRuleset $ruleset
    ) {
        /* explode pattern and trim all parts of pattern */
        $patternParts = array_map('trim', explode('.', $pattern));
        /* Check if pattern is valid */
        if (count($patternParts) === 0) {
            throw new InvalidArgumentException('Unable to solve empty pattern');
        }
        foreach ($patternParts as $part) {
            if ($part === '') {
                throw new InvalidArgumentException("Invalid pattern '{$pattern}'");
            }
        }
        $this->patternParts = $patternParts;
    }

    /**
     * @return string
     */
    public function getPattern(): string
    {
        /* lazy loading, useless for the time being */
        if ($this->pattern === null) {
            /* re-implode pattern here
             * to make sure it's valid and clear */
            $this->pattern = implode('.', $this->patternParts);
        }
        return $this->pattern;
    }

    /**
     * @return array
     */
    public function getPatternParts(): array
    {
        return $this->patternParts;
    }

    /**
     * @return ValidationRuleset
     */
    public function getRuleset(): ValidationRuleset
    {
        return $this->ruleset;
    }
}
