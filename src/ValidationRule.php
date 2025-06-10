<?php

declare(strict_types=1);
/**
 *  本文件属于KK馆版权所有。
 *  This file belong to KKGUAN.
 */

namespace KK\Validation;

use Closure;
use InvalidArgumentException;
use ReflectionException;
use ReflectionFunction;

use function serialize;

class ValidationRule
{
    public string $rule;

    /** @var static[] */
    protected static array $pool = [];

    protected function __construct(
        public string $name,
        public Closure $closure,
        public array $args = []
    ) {
        $this->rule = $this->name;
    }

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

    public function setRule(string $rule): static
    {
        $this->rule = $rule;
        return $this;
    }
}
