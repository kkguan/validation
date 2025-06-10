<?php

declare(strict_types=1);
/**
 *  本文件属于KK馆版权所有。
 *  This file belong to KKGUAN.
 */

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
