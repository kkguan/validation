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

use Hyperf\Translation\ArrayLoader;
use Hyperf\Translation\Translator;
use Hyperf\Validation\ValidationException;
use Hyperf\Validation\ValidatorFactory;
use KK\Validation\Adapter\HyperfValidator;
use Mockery;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

use function extension_loaded;

/**
 * @internal
 * @coversNothing
 */
class HyperfValidatorTest extends TestCase
{
    protected function setUp(): void
    {
        if (! extension_loaded('swow') && ! extension_loaded('swoole')) {
            $this->markTestSkipped('Swow/Swoole extension is unavailable');
        }
    }

    protected function tearDown(): void
    {
        Mockery::close();
    }

    public function testFails()
    {
        $validator = $this->makeValidator(['id' => 256], ['id' => 'required|max:255']);
        $this->assertTrue($validator->fails());

        $validator = $this->makeValidator([], ['id' => 'required|max:255']);
        $this->assertTrue($validator->fails());

        $validator = $this->makeValidator(['id' => 1], ['id' => 'required|max:255']);
        $this->assertFalse($validator->fails());
    }

    public function testRequiredIf()
    {
        $validator = $this->makeValidator(['type' => 1], ['id' => 'required_if:type,1', 'type' => 'required']);
        $this->assertTrue($validator->fails());
    }

    public function testMax()
    {
        $validator = $this->makeValidator(['id' => 256], ['id' => 'max:255']);
        $this->assertTrue($validator->fails());
    }

    public function testMaxArray()
    {
        $validator = $this->makeValidator(['id' => [1, 2, 3]], ['id' => 'max:2']);
        $this->assertTrue($validator->fails());

        $validator = $this->makeValidator(['id' => [1, 2, 3]], ['id' => 'max:3']);
        $this->assertFalse($validator->fails());
    }

    public function testRequiredIfArray()
    {
        $validator = $this->makeValidator(['id' => 3, 'type' => 1], ['id' => 'required_if:type,1,2,3', 'type' => 'required']);
        $this->assertFalse($validator->fails());

        $validator = $this->makeValidator(['type' => 2], ['id' => 'required_if:type,1,2,3']);
        $this->assertTrue($validator->fails());
    }

    public function testRequiredIfArray2()
    {
        $validator = $this->makeValidator(['type' => 1], ['id' => 'array|required_if:type,1,2,3']);
        $this->assertTrue($validator->fails());

        $validator = $this->makeValidator(['type' => 1, 'id' => ['1']], ['id' => 'array|required_if:type,1,2,3']);
        $this->assertFalse($validator->fails());

        $validator = $this->makeValidator(['type' => 1, 'id' => '1'], ['id' => 'array|required_if:type,1,2,3']);
        $this->assertTrue($validator->fails());

        $validator = $this->makeValidator(['type' => 5], ['id' => 'array|required_if:type,1,2,3']);
        $this->assertFalse($validator->fails());
    }

    public function testGetMessageBag()
    {
        $data = [['id' => 256], ['id' => 'required|integer|max:255']];
        $validator = $this->makeValidator(...$data);
        $validator2 = (new ValidatorFactory($this->getTranslator(), $this->getContainer()))->make(...$data);

        $this->assertEquals($validator->getMessageBag(), $validator2->getMessageBag());
    }

    public function testValidatedAndErrors()
    {
        $data = [['id' => 200, 'name' => 'kk', 'data' => ['gender' => 1]], ['id' => 'required|integer|max:255', 'data.gender' => 'integer']];
        $validator = $this->makeValidator(...$data);
        $validator2 = (new ValidatorFactory($this->getTranslator(), $this->getContainer()))->make(...$data);

        $this->assertSame($validator->validated(), $validator2->validated());

        $data = [['id' => 256, 'name' => 'kk', 'data' => ['gender' => 1]], ['id' => 'required|integer|max:255', 'data.gender' => 'integer']];

        try {
            $validator = $this->makeValidator(...$data);
            $validator->validated();
        } catch (ValidationException $exception) {
            $errors = $exception->validator->errors();
        }

        try {
            $validator2 = (new ValidatorFactory($this->getTranslator(), $this->getContainer()))->make(...$data);
            $validator2->validated();
        } catch (ValidationException $exception) {
            $errors2 = $exception->validator->errors();
        }

        $this->assertEquals($errors, $errors2);
    }

    public function testBoolean()
    {
        // 测试普通 boolean 验证 - 应该通过的值
        $validator = $this->makeValidator(['flag' => true], ['flag' => 'boolean']);
        $this->assertFalse($validator->fails());

        $validator = $this->makeValidator(['flag' => false], ['flag' => 'boolean']);
        $this->assertFalse($validator->fails());

        $validator = $this->makeValidator(['flag' => 1], ['flag' => 'boolean']);
        $this->assertFalse($validator->fails());

        $validator = $this->makeValidator(['flag' => 0], ['flag' => 'boolean']);
        $this->assertFalse($validator->fails());

        $validator = $this->makeValidator(['flag' => '1'], ['flag' => 'boolean']);
        $this->assertFalse($validator->fails());

        $validator = $this->makeValidator(['flag' => '0'], ['flag' => 'boolean']);
        $this->assertFalse($validator->fails());

        $validator = $this->makeValidator(['flag' => 'true'], ['flag' => 'boolean']);
        $this->assertFalse($validator->fails());

        $validator = $this->makeValidator(['flag' => 'false'], ['flag' => 'boolean']);
        $this->assertFalse($validator->fails());

        $validator = $this->makeValidator(['flag' => 'TRUE'], ['flag' => 'boolean']);
        $this->assertFalse($validator->fails());

        $validator = $this->makeValidator(['flag' => 'FALSE'], ['flag' => 'boolean']);
        $this->assertFalse($validator->fails());

        // 测试普通 boolean 验证 - 应该失败的值
        $validator = $this->makeValidator(['flag' => 'yes'], ['flag' => 'boolean']);
        $this->assertTrue($validator->fails());

        $validator = $this->makeValidator(['flag' => 'no'], ['flag' => 'boolean']);
        $this->assertTrue($validator->fails());

        $validator = $this->makeValidator(['flag' => '2'], ['flag' => 'boolean']);
        $this->assertTrue($validator->fails());

        $validator = $this->makeValidator(['flag' => 2], ['flag' => 'boolean']);
        $this->assertTrue($validator->fails());

        $validator = $this->makeValidator(['flag' => []], ['flag' => 'boolean']);
        $this->assertTrue($validator->fails());

        $validator = $this->makeValidator(['flag' => 'invalid'], ['flag' => 'boolean']);
        $this->assertTrue($validator->fails());
    }

    public function testBooleanStrict()
    {
        // 测试严格 boolean 验证 - 应该通过的值
        $validator = $this->makeValidator(['flag' => true], ['flag' => 'boolean:strict']);
        $this->assertFalse($validator->fails());

        $validator = $this->makeValidator(['flag' => false], ['flag' => 'boolean:strict']);
        $this->assertFalse($validator->fails());

        // 测试严格 boolean 验证 - 应该失败的值
        $validator = $this->makeValidator(['flag' => 1], ['flag' => 'boolean:strict']);
        $this->assertTrue($validator->fails());

        $validator = $this->makeValidator(['flag' => 0], ['flag' => 'boolean:strict']);
        $this->assertTrue($validator->fails());

        $validator = $this->makeValidator(['flag' => '1'], ['flag' => 'boolean:strict']);
        $this->assertTrue($validator->fails());

        $validator = $this->makeValidator(['flag' => '0'], ['flag' => 'boolean:strict']);
        $this->assertTrue($validator->fails());

        $validator = $this->makeValidator(['flag' => 'true'], ['flag' => 'boolean:strict']);
        $this->assertTrue($validator->fails());

        $validator = $this->makeValidator(['flag' => 'false'], ['flag' => 'boolean:strict']);
        $this->assertTrue($validator->fails());
    }

    public function testRequiredBoolean()
    {
        // 测试 required boolean 组合 - 应该通过的值
        $validator = $this->makeValidator(['flag' => true], ['flag' => 'required|boolean']);
        $this->assertFalse($validator->fails());

        $validator = $this->makeValidator(['flag' => false], ['flag' => 'required|boolean']);
        $this->assertFalse($validator->fails());

        $validator = $this->makeValidator(['flag' => 1], ['flag' => 'required|boolean']);
        $this->assertFalse($validator->fails());

        $validator = $this->makeValidator(['flag' => 0], ['flag' => 'required|boolean']);
        $this->assertFalse($validator->fails());

        $validator = $this->makeValidator(['flag' => '1'], ['flag' => 'required|boolean']);
        $this->assertFalse($validator->fails());

        $validator = $this->makeValidator(['flag' => '0'], ['flag' => 'required|boolean']);
        $this->assertFalse($validator->fails());

        // 测试 required boolean 组合 - 应该失败的值
        $validator = $this->makeValidator([], ['flag' => 'required|boolean']);
        $this->assertTrue($validator->fails());

        $validator = $this->makeValidator(['flag' => null], ['flag' => 'required|boolean']);
        $this->assertTrue($validator->fails());

        $validator = $this->makeValidator(['flag' => ''], ['flag' => 'required|boolean']);
        $this->assertTrue($validator->fails());

        $validator = $this->makeValidator(['flag' => 'invalid'], ['flag' => 'required|boolean']);
        $this->assertTrue($validator->fails());
    }

    public function testRequiredBooleanStrict()
    {
        // 测试 required boolean:strict 组合 - 应该通过的值
        $validator = $this->makeValidator(['flag' => true], ['flag' => 'required|boolean:strict']);
        $this->assertFalse($validator->fails());

        $validator = $this->makeValidator(['flag' => false], ['flag' => 'required|boolean:strict']);
        $this->assertFalse($validator->fails());

        // 测试 required boolean:strict 组合 - 应该失败的值
        $validator = $this->makeValidator([], ['flag' => 'required|boolean:strict']);
        $this->assertTrue($validator->fails());

        $validator = $this->makeValidator(['flag' => null], ['flag' => 'required|boolean:strict']);
        $this->assertTrue($validator->fails());

        $validator = $this->makeValidator(['flag' => 1], ['flag' => 'required|boolean:strict']);
        $this->assertTrue($validator->fails());

        $validator = $this->makeValidator(['flag' => 0], ['flag' => 'required|boolean:strict']);
        $this->assertTrue($validator->fails());

        $validator = $this->makeValidator(['flag' => '1'], ['flag' => 'required|boolean:strict']);
        $this->assertTrue($validator->fails());

        $validator = $this->makeValidator(['flag' => 'true'], ['flag' => 'required|boolean:strict']);
        $this->assertTrue($validator->fails());
    }

    public function testNullableBoolean()
    {
        // 测试 nullable boolean 组合 - null 值应该通过
        $validator = $this->makeValidator(['flag' => null], ['flag' => 'nullable|boolean']);
        $this->assertFalse($validator->fails());

        // 测试 nullable boolean 组合 - 有效的 boolean 值应该通过
        $validator = $this->makeValidator(['flag' => true], ['flag' => 'nullable|boolean']);
        $this->assertFalse($validator->fails());

        $validator = $this->makeValidator(['flag' => false], ['flag' => 'nullable|boolean']);
        $this->assertFalse($validator->fails());

        $validator = $this->makeValidator(['flag' => 1], ['flag' => 'nullable|boolean']);
        $this->assertFalse($validator->fails());

        $validator = $this->makeValidator(['flag' => '0'], ['flag' => 'nullable|boolean']);
        $this->assertFalse($validator->fails());

        // 测试 nullable boolean 组合 - 无效值应该失败
        $validator = $this->makeValidator(['flag' => 'invalid'], ['flag' => 'nullable|boolean']);
        $this->assertTrue($validator->fails());

        $validator = $this->makeValidator(['flag' => []], ['flag' => 'nullable|boolean']);
        $this->assertTrue($validator->fails());
    }

    public function testNullableBooleanStrict()
    {
        // 测试 nullable boolean:strict 组合 - null 值应该通过
        $validator = $this->makeValidator(['flag' => null], ['flag' => 'nullable|boolean:strict']);
        $this->assertFalse($validator->fails());

        // 测试 nullable boolean:strict 组合 - 只有真正的 boolean 值应该通过
        $validator = $this->makeValidator(['flag' => true], ['flag' => 'nullable|boolean:strict']);
        $this->assertFalse($validator->fails());

        $validator = $this->makeValidator(['flag' => false], ['flag' => 'nullable|boolean:strict']);
        $this->assertFalse($validator->fails());

        // 测试 nullable boolean:strict 组合 - 非严格 boolean 值应该失败
        $validator = $this->makeValidator(['flag' => 1], ['flag' => 'nullable|boolean:strict']);
        $this->assertTrue($validator->fails());

        $validator = $this->makeValidator(['flag' => '1'], ['flag' => 'nullable|boolean:strict']);
        $this->assertTrue($validator->fails());

        $validator = $this->makeValidator(['flag' => 'true'], ['flag' => 'nullable|boolean:strict']);
        $this->assertTrue($validator->fails());
    }

    public function testArray()
    {
        $rules = [
            'info' => 'array',
            'info.*.id' => 'required|integer',
            'info.*.name' => 'required',
        ];
        $data = [
            'info' => [
                [
                    'id' => 1,
                    'name' => 'kk',
                ],
                [
                    'id' => 2,
                    'name' => 'kk2',
                ],
            ],
        ];
        $validator = $this->makeValidator($data, $rules);
        $this->assertFalse($validator->fails());
        $this->assertEquals($data, $validator->validated());
    }

    public function testArray2()
    {
        $rules = [
            'info' => 'array',
            'info.id' => 'required|integer',
            'info.name' => 'required|string',
        ];

        $validator = $this->makeValidator([
            'info' => [
                'id' => 1,
                'name' => 'kk',
            ],
        ], $rules);
        $this->assertFalse($validator->fails());
        $this->assertEquals([
            'info' => [
                'id' => 1,
                'name' => 'kk',
            ],
        ], $validator->validated());
    }

    public function testArray3()
    {
        $rules = [
            'info' => 'array',
            'info.*.class' => 'array',
            'info.*.class.*.id' => 'required|integer',
            'info.*.class.*.name' => 'required|string',
        ];

        $data = [
            'info' => [
                [
                    'class' => [
                        [
                            'id' => 1,
                            'name' => 'kk',
                        ],
                    ],
                ],
            ],
        ];

        $validator = $this->makeValidator($data, $rules);
        $this->assertFalse($validator->fails());
        $this->assertEquals($data, $validator->validated());
    }

    protected function makeValidator(array $data, array $rules, array $messages = [], array $customAttributes = [])
    {
        $factory = new ValidatorFactory($this->getTranslator(), $this->getContainer());
        $factory->resolver(static function ($translator, $data, $rules, $messages, $customAttributes) {
            return new HyperfValidator($translator, $data, $rules, $messages, $customAttributes);
        });
        return $factory->make($data, $rules, $messages, $customAttributes);
    }

    protected function getTranslator()
    {
        return new Translator(new ArrayLoader(), 'en');
    }

    protected function getContainer()
    {
        return Mockery::mock(ContainerInterface::class);
    }
}
