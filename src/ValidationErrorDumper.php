<?php

namespace KK\Validation;

use function implode;

class ValidationErrorDumper
{
    public static function dump(array $errors): string
    {
        $messages = [];
        foreach ($errors as $key => $errorSet) {
            $messages[] = "Attribute '{$key}' violates the following rules: " . implode('|', $errorSet);
        }

        return implode("\n", $messages);
    }
}
