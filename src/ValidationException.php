<?php

declare(strict_types=1);
/**
 * This file is part of Hyperf.
 *
 * @link     https://www.hyperf.io
 * @document https://hyperf.wiki
 * @contact  group@hyperf.io
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
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
