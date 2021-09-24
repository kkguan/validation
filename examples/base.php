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
try {
    $validator->validate(['foo' => null]);
} catch (ValidationException $e) {
    // Attribute 'foo' violates the following rules: string
    echo ValidationErrorDumper::dump($e->errors()) . "\n";
}

$validator = new Validator([
    'foo' => 'required|max:255'
]);
try {
    $validator->validate(['foo' => null]);
} catch (ValidationException $e) {
    // Attribute 'foo' violates the following rules: required
    echo ValidationErrorDumper::dump($e->errors()) . "\n";
}
try {
    $validator->validate(['foo' => 256]);
} catch (ValidationException $e) {
    // Attribute 'foo' violates the following rules: max:255
    echo ValidationErrorDumper::dump($e->errors()) . "\n";
}

$validator = new Validator([
    'foo' => 'max:255'
]);
try {
    $validator->validate(['foo' => null]);
} catch (ValidationException $e) {
    echo "Never here\n";
}

$validator = new Validator([
    'foo' => 'min:1|max:255'
]);
try {
    $validator->validate(['foo' => null]);
} catch (ValidationException $e) {
    // Attribute 'foo' violates the following rules: min:1
    echo ValidationErrorDumper::dump($e->errors()) . "\n";
}
try {
    $validator->validate(['foo' => '256.00']);
} catch (ValidationException $e) {
    // Attribute 'foo' violates the following rules: max:255
    echo ValidationErrorDumper::dump($e->errors()) . "\n";
}
try {
    $validator->validate(['foo' => []]);
} catch (ValidationException $e) {
    // Attribute 'foo' violates the following rules: min:1
    echo ValidationErrorDumper::dump($e->errors()) . "\n";
}

$validator = new Validator([
    'foo' => 'in:1, 2, 3'
]);
try {
    $validator->validate(['foo' => null]);
} catch (ValidationException $e) {
    // Attribute 'foo' violates the following rules: in:1, 2, 3
    echo ValidationErrorDumper::dump($e->errors()) . "\n";
}
try {
    $validator->validate(['foo' => 1]);
} catch (ValidationException $e) {
    echo "Never here\n";
}
try {
    $validator->validate(['foo' => []]);
} catch (ValidationException $e) {
    echo "Never here\n";
}
try {
    $validator->validate(['foo' => [123]]);
} catch (ValidationException $e) {
    // Attribute 'foo' violates the following rules: in:1, 2, 3
    echo ValidationErrorDumper::dump($e->errors()) . "\n";
}
try {
    $validator->validate(['foo' => [1, '3', 2]]);
} catch (ValidationException $e) {
    echo "Never here\n";
}
try {
    $validator->validate(['foo' => [2, 3, 4]]);
} catch (ValidationException $e) {
    // Attribute 'foo' violates the following rules: in:1, 2, 3
    echo ValidationErrorDumper::dump($e->errors()) . "\n";
}

$validator = new Validator([
    'foo' => 'required|array|in:9, 8, 7, 6, 5, 4, 3' // in map
]);
try {
    $validator->validate(['foo' => []]);
} catch (ValidationException $e) {
    // Attribute 'foo' violates the following rules: required
    echo ValidationErrorDumper::dump($e->errors()) . "\n";
}
try {
    $validator->validate(['foo' => [0, 1, 2]]);
} catch (ValidationException $e) {
    // Attribute 'foo' violates the following rules: in:9, 8, 7, 6, 5, 4, 3
    echo ValidationErrorDumper::dump($e->errors()) . "\n";
}

$validator = new Validator([
    'foo' => 'alpha'
]);
try {
    $validator->validate(['foo' => '1']);
} catch (ValidationException $e) {
    // Attribute 'foo' violates the following rules: alpha
    echo ValidationErrorDumper::dump($e->errors()) . "\n";
}
try {
    $validator->validate(['foo' => 'xyz']);
} catch (ValidationException $e) {
    echo "Never here\n";
}

$validator = new Validator([
    'foo' => 'alpha_num'
]);
try {
    $validator->validate(['foo' => 'xyz123']);
} catch (ValidationException $e) {
    echo "Never here\n";
}
try {
    $validator->validate(['foo' => 'xyz_123-v4']);
} catch (ValidationException $e) {
    // Attribute 'foo' violates the following rules: alpha_num
    echo ValidationErrorDumper::dump($e->errors()) . "\n";
}

$validator = new Validator([
    'foo' => 'alpha_dash'
]);
try {
    $validator->validate(['foo' => 'xyz_123-v4']);
} catch (ValidationException $e) {
    echo "Never here\n";
}
try {
    $validator->validate(['foo' => 'xyz 123 %']);
} catch (ValidationException $e) {
    // Attribute 'foo' violates the following rules: alpha_dash
    echo ValidationErrorDumper::dump($e->errors()) . "\n";
}
