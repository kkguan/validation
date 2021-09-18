<?php

namespace KK\Validator;

class ValidationPair
{
    public function __construct(
        protected string $pattern,
        protected array $patternParts,
        protected ValidationRuleset $ruleset
    ) {
    }

    /**
     * @return string
     */
    public function getPattern(): string
    {
        return $this->pattern;
    }

    public function setPattern(string $pattern): static
    {
        $this->pattern = $pattern;

        return $this;
    }

    /**
     * @return array
     */
    public function getPatternParts(): array
    {
        return $this->patternParts;
    }

    public function setPatternParts(array $patternParts): static
    {
        $this->patternParts = $patternParts;

        return $this;
    }

    /**
     * @return ValidationRuleset
     */
    public function getRuleset(): ValidationRuleset
    {
        return $this->ruleset;
    }

    public function setRuleset(ValidationRuleset $ruleset): static
    {
        $this->ruleset = $ruleset;

        return $this;
    }
}
