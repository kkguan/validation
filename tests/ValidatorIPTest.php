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

namespace KKTest\Validation;

use Jchook\AssertThrows\AssertThrows;
use KK\Validation\ValidationException;
use KK\Validation\Validator;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 * @coversNothing
 */
class ValidatorIPTest extends TestCase
{
    use AssertThrows;

    public function testIP()
    {
        $validator = new Validator([
            'ip' => 'required|ip',
        ]);
        $this->assertSame($data = ['ip' => '127.0.0.1'], $validator->validate($data));
        $this->assertThrows(ValidationException::class, function () use ($validator) {
            $validator->validate(['ip' => 'xxx']);
        });
    }

    public function testIPV4()
    {
        $validator = new Validator([
            'ip' => 'required|ipv4',
        ]);
        $this->assertSame($data = ['ip' => '0.0.0.0'], $validator->validate($data));
        $this->assertSame($data = ['ip' => '127.0.0.1'], $validator->validate($data));
        $this->assertThrows(ValidationException::class, function () use ($validator) {
            $validator->validate(['ip' => '::']);
        });
    }

    public function testIPV6()
    {
        $validator = new Validator([
            'ip' => 'required|ipv6',
        ]);
        $this->assertSame($data = ['ip' => '::'], $validator->validate($data));
        $this->assertSame($data = ['ip' => '2001:0db8:86a3:08d3:1319:8a2e:0370:7344'], $validator->validate($data));
        $this->assertThrows(ValidationException::class, function () use ($validator) {
            $validator->validate(['ip' => '0.0.0.0']);
        });
        $this->assertThrows(ValidationException::class, function () use ($validator) {
            $validator->validate(['ip' => '127.0.0.1']);
        });
    }
}
