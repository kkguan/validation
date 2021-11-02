<?php

declare(strict_types=1);

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
        if (!extension_loaded('swow') && !extension_loaded('swoole')) {
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
