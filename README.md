# 验证器

## 简介

- 兼容 Hyperf/Laravel Validation 规则
- 部分场景可获得约 500 倍性能提升
- 验证器可多次复用不同数据，无状态设计
- 规则可全局复用
- 智能合并验证规则

## 安装

### 环境要求

- PHP >= 8.0   
- mbstring 扩展   
- ctype 扩展   

### 安装命令

```bash
composer require kkgroup/validation
```

## 使用

### 如何在 Hyperf 框架中使用

因为并没有适配所有规则，所以大表单验证中，最好还是按需使用，不要全部替换。

#### 局部替换

只需要在我们的 `FormRequest` 中添加对应的 `validator` 方法，即可使用。

```php
<?php

declare(strict_types=1);

namespace App\Request;

use Hyperf\Validation\Request\FormRequest;
use Hyperf\Validation\ValidatorFactory;
use KK\Validation\Adapter\HyperfValidator;

class EventSaveRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'title' => 'required|max:64',
            'summary' => 'required|max:512',
            'type' => 'required|in:1,2,3,4',
            'data' => 'array',
            'data.*.name' => 'required',
            'data.*.desc' => 'required',
            'data.*.type' => 'required',
        ];
    }

    public function validator(ValidatorFactory $factory)
    {
        return new HyperfValidator(
            $factory->getTranslator(),
            $this->validationData(),
            $this->getRules(),
            $this->messages(),
            $this->attributes()
        );
    }
}

```

#### 全部替换

```php
<?php

declare(strict_types=1);

namespace App\Listener;

use Hyperf\Event\Annotation\Listener;
use Hyperf\Event\Contract\ListenerInterface;
use Hyperf\Validation\Contract\ValidatorFactoryInterface;
use Hyperf\Validation\Event\ValidatorFactoryResolved;
use KK\Validation\Adapter\HyperfValidator;
use Psr\Container\ContainerInterface;

#[Listener]
class ValidatorFactoryResolvedListener implements ListenerInterface
{

    private ContainerInterface $container;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    public function listen(): array
    {
        return [
            ValidatorFactoryResolved::class,
        ];
    }

    public function process(object $event)
    {
        /** @var ValidatorFactoryInterface $validatorFactory */
        $validatorFactory = $event->validatorFactory;
        $validatorFactory->resolver(static function ($translator, $data, $rules, $messages, $customAttributes) {
            return new HyperfValidator($translator, $data, $rules, $messages, $customAttributes);
        });
    }
}
```

## 待办

- 暂不支持转义 `.`, `*` 等关键符 (好做但是暂时还没需求)
- 规则没有全部适配
- 多语言支持 (或许该库只应该实现核心部分, 其它的可以在上层做)
