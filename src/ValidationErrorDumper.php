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
