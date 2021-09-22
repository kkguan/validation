<?php

namespace KK\Validation;

use JetBrains\PhpStorm\Pure;

class ValidationException extends \Exception
{
    #[Pure] public function __construct(protected array $errors)
    {
        parent::__construct('The given data was invalid');
    }

    public function errors(): array
    {
        return $this->errors;
    }
}
