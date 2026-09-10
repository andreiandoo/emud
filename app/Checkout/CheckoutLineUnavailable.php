<?php

namespace App\Checkout;

use RuntimeException;

/**
 * A product in the basket that no supplier can fulfil at the moment of ordering.
 *
 * Thrown before anything is written, so the cart stays intact and the customer can remove
 * the line and order the rest. The message is shown to the customer as-is.
 */
final class CheckoutLineUnavailable extends RuntimeException
{
    /** @param list<string> $productNames */
    public static function for(array $productNames): self
    {
        $names = implode(', ', array_map(static fn (string $name): string => "„{$name}”", $productNames));

        return new self(count($productNames) === 1
            ? "produsul {$names} nu mai este disponibil la niciun furnizor în acest moment. Scoate-l din coș și poți finaliza restul comenzii."
            : "produsele {$names} nu mai sunt disponibile la niciun furnizor în acest moment. Scoate-le din coș și poți finaliza restul comenzii.");
    }
}
