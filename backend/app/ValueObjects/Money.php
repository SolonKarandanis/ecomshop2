<?php

namespace App\ValueObjects;

use InvalidArgumentException;
use JsonSerializable;
use Stringable;

/**
 * An amount of money in whole cents, so prices are never held in a PHP float.
 * Immutable: every operation returns a new Money. Rounding is half-up (away from
 * zero) to the cent, and happens only in fromDecimal() and addPercent().
 */
final class Money implements JsonSerializable, Stringable
{
    private function __construct(
        private readonly int $cents,
        private readonly string $currency,
    ) {}

    public static function fromCents(int $cents, ?string $currency = null): self
    {
        return new self($cents, $currency ?? self::defaultCurrency());
    }

    /**
     * Parses '19.99', 19.99 or 20. A float is read as its shortest decimal form
     * (19.99, not 19.989999…), so it rounds the way the number reads.
     */
    public static function fromDecimal(string|int|float $amount, ?string $currency = null): self
    {
        return new self(self::parseScaled(self::decimalString($amount), 2), $currency ?? self::defaultCurrency());
    }

    public static function zero(?string $currency = null): self
    {
        return self::fromCents(0, $currency);
    }

    public function cents(): int
    {
        return $this->cents;
    }

    public function currency(): string
    {
        return $this->currency;
    }

    public function add(Money $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->cents + $other->cents, $this->currency);
    }

    public function multiply(int $quantity): self
    {
        return new self($this->cents * $quantity, $this->currency);
    }

    /** E.g. addPercent(15) on 899.50 gives 1034.43 (1034.425 rounded half-up). */
    public function addPercent(string|int|float $percent): self
    {
        // The percentage to 4 decimal places, as an integer: 15 → 150000.
        $scaledPercent = self::parseScaled(self::decimalString($percent), 4);

        return new self($this->cents + self::divideRoundingHalfUp($this->cents * $scaledPercent, 1_000_000), $this->currency);
    }

    public function equals(Money $other): bool
    {
        return $this->cents === $other->cents && $this->currency === $other->currency;
    }

    public function isZero(): bool
    {
        return $this->cents === 0;
    }

    /** '19.99', for decimal(10,2) columns. */
    public function toDecimal(): string
    {
        $sign = $this->cents < 0 ? '-' : '';
        $absolute = abs($this->cents);

        return sprintf('%s%d.%02d', $sign, intdiv($absolute, 100), $absolute % 100);
    }

    /** For JSON responses, which have always sent prices as numbers. */
    public function toFloat(): float
    {
        return $this->cents / 100;
    }

    public function __toString(): string
    {
        return $this->toDecimal();
    }

    public function jsonSerialize(): float
    {
        return $this->toFloat();
    }

    private static function defaultCurrency(): string
    {
        return (string) config('app.currency');
    }

    private function assertSameCurrency(Money $other): void
    {
        if ($this->currency !== $other->currency) {
            throw new InvalidArgumentException("Can't combine {$this->currency} and {$other->currency} amounts.");
        }
    }

    private static function decimalString(string|int|float $amount): string
    {
        if (is_float($amount)) {
            // (string) gives the shortest form that reads back as the same float: 19.99 → '19.99'.
            $string = (string) $amount;

            // Very large or small floats come out as '1.0E-5'.
            return str_contains($string, 'E') ? sprintf('%.10F', $amount) : $string;
        }

        return trim((string) $amount);
    }

    /** '19.995' at scale 2 → 2000: the number times 10^scale, rounded half-up. */
    private static function parseScaled(string $decimal, int $scale): int
    {
        if (! preg_match('/^(-?)(\d*)(?:\.(\d*))?$/', $decimal, $parts) || ($parts[2] === '' && ($parts[3] ?? '') === '')) {
            throw new InvalidArgumentException("'{$decimal}' isn't a decimal amount.");
        }

        [, $sign, $whole, $fraction] = $parts + [3 => ''];
        $kept = str_pad(substr($fraction, 0, $scale), $scale, '0');
        $scaled = (int) ($whole.$kept);

        // Half-up: the first dropped digit decides.
        if (($fraction[$scale] ?? '0') >= '5') {
            $scaled++;
        }

        return $sign === '-' ? -$scaled : $scaled;
    }

    private static function divideRoundingHalfUp(int $numerator, int $denominator): int
    {
        $quotient = intdiv(2 * abs($numerator) + $denominator, 2 * $denominator);

        return $numerator < 0 ? -$quotient : $quotient;
    }
}
