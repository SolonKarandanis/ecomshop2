<?php

use App\ValueObjects\Money;

it('parses decimal strings, floats and integers to exact cents', function (string|int|float $amount, int $cents) {
    expect(Money::fromDecimal($amount)->cents())->toBe($cents);
})->with([
    "'19.99'" => ['19.99', 1999],
    '19.99 (float)' => [19.99, 1999],
    "'0.29'" => ['0.29', 29],
    '0.29 (float)' => [0.29, 29],
    '8.2 (float)' => [8.2, 820],
    '0' => [0, 0],
    "'25'" => ['25', 2500],
    "'.5'" => ['.5', 50],
    "'-4.35'" => ['-4.35', -435],
    "'1.005' rounds half-up" => ['1.005', 101],
    '1.005 (float) rounds as it reads' => [1.005, 101],
    "'1.004' rounds down" => ['1.004', 100],
    "'-1.005' rounds away from zero" => ['-1.005', -101],
]);

it('rejects amounts that are not decimals', function (string $amount) {
    Money::fromDecimal($amount);
})->throws(InvalidArgumentException::class)->with(['', 'abc', '1,50', '1.2.3', '-']);

it('formats cents as a two-decimal string and a float', function (int $cents, string $decimal, float $float) {
    $money = Money::fromCents($cents);

    expect($money->toDecimal())->toBe($decimal)
        ->and((string) $money)->toBe($decimal)
        ->and($money->toFloat())->toBe($float)
        ->and(json_encode(['price' => $money]))->toBe(json_encode(['price' => $float]));
})->with([
    [1999, '19.99', 19.99],
    [5, '0.05', 0.05],
    [0, '0.00', 0.0],
    [-435, '-4.35', -4.35],
    [-5, '-0.05', -0.05],
]);

it('adds and multiplies without float drift', function () {
    $price = Money::fromDecimal('0.10');

    expect($price->add(Money::fromDecimal('0.20'))->toDecimal())->toBe('0.30')
        ->and($price->multiply(3)->toDecimal())->toBe('0.30')
        ->and(Money::fromDecimal('4.35')->multiply(3)->toDecimal())->toBe('13.05');
});

it('subtracts, going below zero when the other amount is larger', function (string $amount, string $other, string $result) {
    expect(Money::fromDecimal($amount)->subtract(Money::fromDecimal($other))->toDecimal())->toBe($result);
})->with([
    '44.98 - 5.00' => ['44.98', '5.00', '39.98'],
    '0.30 - 0.10' => ['0.30', '0.10', '0.20'],
    '5.00 - 5.00' => ['5.00', '5.00', '0.00'],
    '5.00 - 7.25' => ['5.00', '7.25', '-2.25'],
]);

it('adds a percentage rounded half-up to the cent', function (string $amount, string|int|float $percent, string $result) {
    expect(Money::fromDecimal($amount)->addPercent($percent)->toDecimal())->toBe($result);
})->with([
    '899.50 + 15%' => ['899.50', 15, '1034.43'],
    '10.00 + 12.5%' => ['10.00', 12.5, '11.25'],
    '19.99 + 10%' => ['19.99', '10', '21.99'],
    '0.01 + 50%' => ['0.01', 50, '0.02'],
    '100.00 - 33.3333%' => ['100.00', '-33.3333', '66.67'],
]);

it('is immutable', function () {
    $price = Money::fromDecimal('10.00');
    $price->add(Money::fromDecimal('5.00'));
    $price->subtract(Money::fromDecimal('5.00'));
    $price->multiply(4);
    $price->addPercent(20);

    expect($price->toDecimal())->toBe('10.00');
});

it('compares by amount and currency', function () {
    expect(Money::fromDecimal('1.50')->equals(Money::fromCents(150)))->toBeTrue()
        ->and(Money::fromDecimal('1.50')->equals(Money::fromCents(151)))->toBeFalse()
        ->and(Money::fromCents(150, 'eur')->equals(Money::fromCents(150, 'usd')))->toBeFalse()
        ->and(Money::zero()->isZero())->toBeTrue();
});

it('uses the app currency by default', function () {
    config(['app.currency' => 'gbp']);

    expect(Money::fromDecimal('1.00')->currency())->toBe('gbp');
});

it('refuses to add or subtract amounts in different currencies', function (string $operation) {
    Money::fromCents(100, 'eur')->{$operation}(Money::fromCents(100, 'usd'));
})->throws(InvalidArgumentException::class)->with(['add', 'subtract']);
