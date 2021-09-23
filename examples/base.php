<?php

require __DIR__ . '/../vendor/autoload.php';

use KK\Validation\ValidationErrorDumper;
use KK\Validation\ValidationException;
use KK\Validation\Validator;

$validator = new Validator([
    'type' => 'sometimes|required',
]);
try {
    $validator->validate(['type' => 'foo']);
} catch (ValidationException) {
    echo "Never here\n";
}
try {
    $validator->validate([]);
} catch (ValidationException) {
    echo "Never here\n";
}
try {
    $validator->validate(['type' => '']);
} catch (ValidationException $e) {
    // Attribute 'type' violates the following rules: required
    echo ValidationErrorDumper::dump($e->errors()) . "\n";
}

$validator = new Validator([
    'type' => 'required',
]);
try {
    $validator->validate([]);
} catch (ValidationException $e) {
    // Attribute 'type' violates the following rules: required
    echo ValidationErrorDumper::dump($e->errors()) . "\n";
}

$validator = new Validator([
    'type' => 'required|numeric',
    'foo.*.bar' => 'numeric|max:256'
]);
try {
    $validator->validate([
        'type' => 'xxx'
    ]);
} catch (ValidationException $e) {
    // Attribute 'type' violates the following rules: numeric
    echo ValidationErrorDumper::dump($e->errors()) . "\n";
}
try {
    $validator->validate([
        'type' => 1,
        'foo' => [['bar' => '1024']]
    ]);
} catch (ValidationException $e) {
    // Attribute 'foo.0.bar' violates the following rules: max:256
    echo ValidationErrorDumper::dump($e->errors()) . "\n";
}

$validator = new Validator([
    'a.*.b.*.c.*.d.*.e' => 'numeric'
]);
try {
    $validator->validate([
        'a' => [['b' => [['c' => [['d' => [['e' => 'xxx']]]]]]]]
    ]);
} catch (ValidationException $e) {
    // Attribute 'a.0.b.0.c.0.d.0.e' violates the following rules: numeric
    echo ValidationErrorDumper::dump($e->errors()) . "\n";
}

$validator = new Validator([
    '*' => 'numeric'
]);
try {
    $validator->validate(['0', '1', '2', '3']);
} catch (ValidationException) {
    echo "Never here\n";
}
try {
    $validator->validate(['0', 'x', 'y', 'z']);
} catch (ValidationException $e) {
    // Attribute '1' violates the following rules: numeric
    // Attribute '2' violates the following rules: numeric
    // Attribute '3' violates the following rules: numeric
    echo ValidationErrorDumper::dump($e->errors()) . "\n";
}

$validator = new Validator([
    'foo.*' => 'integer'
]);
try {
    $validator->validate(['foo' => ['0', '0.1', '0.2', '1']]);
} catch (ValidationException $e) {
    // Attribute 'foo.1' violates the following rules: integer
    // Attribute 'foo.2' violates the following rules: integer
    echo ValidationErrorDumper::dump($e->errors()) . "\n";
}
try {
    $validator->validate(['foo' => 'not array']);
} catch (ValidationException $e) {
    // Attribute 'foo' violates the following rules: array
    echo ValidationErrorDumper::dump($e->errors()) . "\n";
}

$validator = new Validator([
    '*.*.*' => 'integer'
]);
try {
    $validator->validate(['foo' => ['bar' => 'not array']]);
} catch (ValidationException $e) {
    // Attribute 'foo.bar' violates the following rules: array
    echo ValidationErrorDumper::dump($e->errors()) . "\n";
}
try {
    $validator->validate(['foo' => ['bar' => ['baz']]]);
} catch (ValidationException $e) {
    // Attribute 'foo.bar.0' violates the following rules: integer
    echo ValidationErrorDumper::dump($e->errors()) . "\n";
}
try {
    $validator->validate(['foo' => ['bar' => ['1']]]);
} catch (ValidationException) {
    echo "Never here\n";
}

$validator = new Validator([
    'foo' => 'string|max:255'
]);
try {
    $validator->validate(['foo' => []]);
} catch (ValidationException $e) {
    // Attribute 'foo' violates the following rules: string
    echo ValidationErrorDumper::dump($e->errors()) . "\n";
}
