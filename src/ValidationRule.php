<?php

namespace KK\Validation;

use Closure;
use InvalidArgumentException;
use ReflectionException;
use ReflectionFunction;
use function serialize;

class ValidationRule
{
    /** @var static[] */
    protected static array $pool = [];

    public static function make(string $name, Closure $closure, array $args = []): static
    {
        try {
            $methodName = (new ReflectionFunction($closure))->getName();
        } catch (ReflectionException $exception) {
            throw new InvalidArgumentException('Invalid validation attribute closure');
        }
        $hash = $args === [] ? $methodName : $methodName . ':' . serialize($args);
        return static::$pool[$hash] ?? (static::$pool[$hash] = new static($name, $closure, $args));
    }

    protected function __construct(
        public string $name,
        public Closure $closure,
        public array $args = []
    ) {
    }
}
