<?php

declare(strict_types=1);
/**
 *  本文件属于KK馆版权所有。
 *  This file belong to KKGUAN.
 */

namespace KK\Validation;

use InvalidArgumentException;

use function array_map;
use function count;
use function explode;

class ValidationPair
{
    public array $patternParts;

    public function __construct(
        string $pattern,
        public ValidationRuleset $ruleset
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
}
