<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use JustinHoltWeb\Wingman\Elements\WingmanElement;
use JustinHoltWeb\Wingman\Fields\WingmanField;
use JustinHoltWeb\Wingman\Registry\GeneratedClassLoader;
use JustinHoltWeb\Wingman\Registry\ModelRegistry;

class WingmanTestWidget extends Model {}

it('accepts only PascalCase identifier handles', function (string $handle, bool $valid) {
    expect(ModelRegistry::isValidHandle($handle))->toBe($valid);
})->with([
    ['Customer', true],
    ['Order2', true],
    ['customer', false],
    ['Customer;phpinfo()', false],
    ['Customer\\Evil', false],
    ['Cust omer', false],
    ['', false],
    [str_repeat('A', 65), false],
]);

it('round-trips handles through generated class names', function () {
    expect(ModelRegistry::elementClass('Customer'))->toBe('JustinHoltWeb\\Wingman\\Generated\\Elements\\Customer')
        ->and(ModelRegistry::fieldClass('Customer'))->toBe('JustinHoltWeb\\Wingman\\Generated\\Fields\\CustomerField')
        ->and(ModelRegistry::handleFromElementClass('\\JustinHoltWeb\\Wingman\\Generated\\Elements\\Customer'))->toBe('Customer')
        ->and(ModelRegistry::handleFromFieldClass('JustinHoltWeb\\Wingman\\Generated\\Fields\\CustomerField'))->toBe('Customer')
        ->and(ModelRegistry::handleFromElementClass('App\\Models\\Customer'))->toBeNull()
        ->and(ModelRegistry::handleFromFieldClass('JustinHoltWeb\\Wingman\\Generated\\Fields\\Customer'))->toBeNull()
        ->and(ModelRegistry::handleFromElementClass('JustinHoltWeb\\Wingman\\Generated\\Elements\\Sub\\Customer'))->toBeNull();
});

it('refuses classes that are not Eloquent models', function () {
    new ModelRegistry()->add('Thing', stdClass::class);
})->throws(InvalidArgumentException::class);

it('refuses invalid handles', function () {
    new ModelRegistry()->add('bad handle', WingmanTestWidget::class);
})->throws(InvalidArgumentException::class);

it('mints element and field classes for registered handles only', function () {
    $registry = new ModelRegistry;
    $registry->add('Widget', WingmanTestWidget::class);
    $loader = new GeneratedClassLoader($registry);

    $loader->load(ModelRegistry::elementClass('Gadget'));
    expect(class_exists(ModelRegistry::elementClass('Gadget'), false))->toBeFalse();

    $loader->load(ModelRegistry::elementClass('Widget'));
    $loader->load(ModelRegistry::fieldClass('Widget'));

    expect(is_subclass_of(ModelRegistry::elementClass('Widget'), WingmanElement::class))->toBeTrue()
        ->and(is_subclass_of(ModelRegistry::fieldClass('Widget'), WingmanField::class))->toBeTrue()
        ->and(new ReflectionClass(ModelRegistry::elementClass('Widget'))->isFinal())->toBeTrue();
});
