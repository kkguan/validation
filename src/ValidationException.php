<?php

declare(strict_types=1);
/**
 *  本文件属于KK馆版权所有。
 *  This file belong to KKGUAN.
 */

namespace KK\Validation;

use Exception;
use JetBrains\PhpStorm\Pure;

class ValidationException extends Exception
{
    #[Pure]
    public function __construct(protected array $errors)
    {
        parent::__construct('The given data was invalid');
    }

    public function errors(): array
    {
        return $this->errors;
    }
}
