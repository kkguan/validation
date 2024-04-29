<?php

namespace KK\Validation\Adapter;

use Hyperf\Contract\TranslatorInterface;
use Hyperf\Contract\ValidatorInterface;
use Hyperf\Collection\Arr;
use Hyperf\Contract\MessageBag as MessageBagContract;
use Hyperf\Support\Fluent;
use Hyperf\Support\MessageBag;
use Hyperf\Stringable\Str;
use Hyperf\Validation\Concerns;
use Hyperf\Validation\Contract\PresenceVerifierInterface;
use Hyperf\Validation\ValidationException;
use Hyperf\Validation\ValidationRuleParser;
use KK\Validation\Validator;
use Psr\Container\ContainerInterface;
use RuntimeException;

use function Hyperf\Collection\data_get;
use function Hyperf\Collection\collect;

class HyperfValidator implements ValidatorInterface
{
    use Concerns\FormatsMessages;
    use Concerns\ValidatesAttributes;

    /**
     * The array of custom error messages.
     *
     * @var array
     */
    public $customMessages = [];

    /**
     * The array of custom attribute names.
     *
     * @var array
     */
    public $customAttributes = [];

    /**
     * The array of fallback error messages.
     *
     * @var array
     */
    public $fallbackMessages = [];

    /**
     * The data under validation.
     *
     * @var array
     */
    protected $data;

    /**
     * The rules to be applied to the data.
     *
     * @var array
     */
    protected $rules;

    /**
     * All of the registered "after" callbacks.
     *
     * @var array
     */
    protected $after = [];

    /**
     * The failed validation rules.
     *
     * @var array
     */
    protected $failedRules = [];

    /**
     * The cached data for the "distinct" rule.
     *
     * @var array
     */
    protected $distinctValues = [];

    /**
     * The validation rules that may be applied to files.
     *
     * @var array
     */
    protected $fileRules = [
        'File', 'Image', 'Mimes', 'Mimetypes', 'Min',
        'Max', 'Size', 'Between', 'Dimensions',
    ];

    /**
     * The validation rules that imply the field is required.
     *
     * @var array
     */
    protected $implicitRules = [
        'Required', 'Filled', 'RequiredWith', 'RequiredWithAll', 'RequiredWithout',
        'RequiredWithoutAll', 'RequiredIf', 'RequiredUnless', 'Accepted', 'Present',
    ];

    /**
     * The validation rules which depend on other fields as parameters.
     *
     * @var array
     */
    protected $dependentRules = [
        'RequiredWith', 'RequiredWithAll', 'RequiredWithout', 'RequiredWithoutAll',
        'RequiredIf', 'RequiredUnless', 'Confirmed', 'Same', 'Different', 'Unique',
        'Before', 'After', 'BeforeOrEqual', 'AfterOrEqual', 'Gt', 'Lt', 'Gte', 'Lte',
    ];

    /**
     * The size related validation rules.
     *
     * @var array
     */
    protected $sizeRules = ['Size', 'Between', 'Min', 'Max', 'Gt', 'Lt', 'Gte', 'Lte'];

    /**
     * The numeric related validation rules.
     *
     * @var array
     */
    protected $numericRules = ['Numeric', 'Integer'];

    /**
     * The Translator implementation.
     *
     * @var TranslatorInterface
     */
    protected $translator;

    /**
     * The container instance.
     *
     * @var ContainerInterface
     */
    protected $container;

    /**
     * The message bag instance.
     *
     * @var MessageBagContract
     */
    protected $messages;

    /**
     * @var Validator
     */
    protected $validator;

    /**
     * The Presence Verifier implementation.
     *
     * @var \Hyperf\Validation\Contract\PresenceVerifierInterface
     */
    protected $presenceVerifier;

    public function __construct(
        TranslatorInterface $translator,
        array $data,
        array $rules,
        array $messages = [],
        array $customAttributes = []
    ) {
        $this->translator = $translator;
        $this->customMessages = $messages;
        $this->data = $data;
        $this->customAttributes = $customAttributes;

        $this->validator = new Validator($this->rules = $rules);
    }

    /**
     * Determine if the data passes the validation rules.
     */
    public function passes(): bool
    {
        $this->messages = new MessageBag();

        [$this->distinctValues, $this->failedRules] = [[], []];

        $this->validator->valid($this->data);
        foreach ($this->validator->errors() as $attribute => $errors) {
            foreach ($errors as $error) {
                [$rule, $parameters] = ValidationRuleParser::parse($error);
                $this->addFailure($attribute, $rule, $parameters);
            }
        }

        // Here we will spin through all of the "after" hooks on this validator and
        // fire them off. This gives the callbacks a chance to perform all kinds
        // of other validation that needs to get wrapped up in this operation.
        foreach ($this->after as $after) {
            call_user_func($after);
        }

        return $this->messages->isEmpty();
    }

    /**
     * Set the IoC container instance.
     */
    public function setContainer(ContainerInterface $container)
    {
        $this->container = $container;
    }

    public function getMessageBag(): MessageBagContract
    {
        return $this->messages();
    }

    public function validate(): array
    {
        if ($this->fails()) {
            throw new ValidationException($this);
        }

        return $this->validated();
    }

    public function validated(): array
    {
        if ($this->invalid()) {
            throw new ValidationException($this);
        }

        $results = [];

        $missingValue = Str::random(10);

        foreach (array_keys($this->getRules()) as $key) {
            $value = data_get($this->getData(), $key, $missingValue);

            if ($value !== $missingValue) {
                Arr::set($results, $key, $value);
            }
        }

        return $results;
    }

    /**
     * Returns the data which was invalid.
     */
    public function invalid(): array
    {
        if (! $this->messages) {
            $this->passes();
        }

        return array_intersect_key(
            $this->data,
            $this->attributesThatHaveMessages()
        );
    }

    public function fails(): bool
    {
        return ! $this->passes();
    }

    public function failed(): array
    {
        return $this->failedRules;
    }

    /**
     * Get the message container for the validator.
     *
     * @return MessageBag
     */
    public function messages()
    {
        if (! $this->messages) {
            $this->passes();
        }

        return $this->messages;
    }

    public function sometimes($attribute, $rules, callable $callback)
    {
        $payload = new Fluent($this->getData());

        if (call_user_func($callback, $payload)) {
            foreach ((array) $attribute as $key) {
                $this->addRules([$key => $rules]);
            }
        }

        return $this;
    }

    public function after($callback)
    {
        $this->after[] = function () use ($callback) {
            return call_user_func_array($callback, [$this]);
        };

        return $this;
    }

    public function errors(): MessageBagContract
    {
        return $this->messages();
    }

    /**
     * Add a failed rule and error message to the collection.
     */
    public function addFailure(string $attribute, string $rule, array $parameters = [])
    {
        if (! $this->messages) {
            $this->passes();
        }

        $this->messages->add($attribute, $this->makeReplacements(
            $this->getMessage($attribute, $rule),
            $attribute,
            $rule,
            $parameters
        ));

        $this->failedRules[$attribute][$rule] = $parameters;
    }

    /**
     * Set the Presence Verifier implementation.
     */
    public function setPresenceVerifier(PresenceVerifierInterface $presenceVerifier)
    {
        $this->presenceVerifier = $presenceVerifier;
    }

    /**
     * Get the Presence Verifier implementation.
     *
     * @throws \RuntimeException
     */
    public function getPresenceVerifier(): PresenceVerifierInterface
    {
        if (! isset($this->presenceVerifier)) {
            throw new RuntimeException('Presence verifier has not been set.');
        }

        return $this->presenceVerifier;
    }

    /**
     * Determine if the given attribute has a rule in the given set.
     *
     * @param array|string $rules
     */
    public function hasRule(string $attribute, $rules): bool
    {
        return ! is_null($this->getRule($attribute, $rules));
    }

    /**
     * Get the displayable name of the attribute.
     */
    public function getDisplayableAttribute(string $attribute): string
    {
        return $attribute;
    }

    /**
     * Get the data under validation.
     */
    public function getData(): array
    {
        return $this->data;
    }

    /**
     * Parse the given rules and merge them into current rules.
     */
    public function addRules(array $rules)
    {
        $rules = array_merge_recursive(
            $this->rules,
            $rules
        );

        $this->validator = new Validator($this->rules = $rules);
    }

    /**
     * Get the validation rules.
     */
    public function getRules(): array
    {
        return $this->rules;
    }

    /**
     * FIXME: Bad performance
     * Get a rule and its parameters for a given attribute.
     *
     * @param array|string $rules
     */
    protected function getRule(string $attribute, $rules): ?array
    {
        $pairs = $this->validator->getValidationPairs();
        foreach ($pairs as $pair) {
            if (in_array($attribute, $pair->patternParts)) {
                foreach ($pair->ruleset->getRules() as $rule) {
                    [$rule, $parameters] = ValidationRuleParser::parse($rule->name);

                    if (in_array($rule, $rules)) {
                        return [$rule, $parameters];
                    }
                }
            }
        }

        return null;
    }

    /**
     * Get the value of a given attribute.
     */
    protected function getValue(string $attribute)
    {
        return Arr::get($this->data, $attribute);
    }

    /**
     * Generate an array of all attributes that have messages.
     */
    protected function attributesThatHaveMessages(): array
    {
        return collect($this->messages()->toArray())->map(function ($message, $key) {
            return explode('.', $key)[0];
        })->unique()->flip()->all();
    }
}
