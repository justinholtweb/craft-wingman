<?php

declare(strict_types=1);

use JustinHoltWeb\Wingman\Gql\ModelQueries;
use JustinHoltWeb\Wingman\Gql\ModelResolver;
use JustinHoltWeb\Wingman\Gql\ModelTypes;

enum WingmanTestStatus: string
{
    case Paid = 'paid';
}

it('names queries after the plural handle, never colliding with the single query', function (string $handle, string $plural) {
    expect(ModelQueries::plural($handle))->toBe($plural);
})->with([
    ['Customer', 'Customers'],
    ['OrderLine', 'OrderLines'],
    ['Sheep', 'SheepList'],
    ['WingmanInvoice', 'WingmanInvoices'],
]);

it('scopes each model to its own schema component', function () {
    expect(ModelResolver::component('Customer'))->toBe('wingman.Customer')
        ->and(ModelTypes::typeName('Customer'))->toBe('WingmanCustomer');
});

it('turns model values into GraphQL scalars', function () {
    expect(ModelTypes::scalar(null))->toBeNull()
        ->and(ModelTypes::scalar(12))->toBe(12)
        ->and(ModelTypes::scalar(true))->toBeTrue()
        ->and(ModelTypes::scalar(new DateTimeImmutable('2026-11-01T10:00:00+00:00')))->toBe('2026-11-01T10:00:00+00:00')
        ->and(ModelTypes::scalar(WingmanTestStatus::Paid))->toBe('paid')
        ->and(ModelTypes::scalar(['a' => 1]))->toBe('{"a":1}')
        ->and(ModelTypes::scalar(new stdClass))->toBeNull();
});
