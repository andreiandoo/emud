<?php

namespace App\Storefront;

use App\Models\Address;
use App\Models\User;
use Closure;
use Illuminate\Database\Eloquent\Builder;

/**
 * The delivery address and invoicing details a customer keeps on their account.
 *
 * They share the addresses table with the copies printed on orders and are told apart by
 * is_default. An order always keeps its own copy, so correcting a street here never rewrites
 * where an old parcel went or who an old invoice was made out to.
 *
 * A billing address is kept only when it differs from delivery. A person invoiced at the
 * address the parcel goes to — most customers — has one address, not two copies of it.
 */
class AddressBook
{
    /** Every field the account page and checkout bind an address form to. */
    public const FIELDS = [
        'first_name', 'last_name', 'company', 'vat_number', 'trade_register_number',
        'line_1', 'city', 'county', 'postal_code',
    ];

    public function shipping(User $user): ?Address
    {
        return $this->saved($user, 'shipping')->latest('id')->first();
    }

    public function billing(User $user): ?Address
    {
        return $this->saved($user, 'billing')->latest('id')->first();
    }

    /** @param  array<string, mixed>  $form */
    public function saveShipping(User $user, array $form): Address
    {
        return $this->store($user, 'shipping', self::shippingDetails($form));
    }

    /**
     * Details as built by billingDetails(), or null to invoice the person at the delivery
     * address — which removes any billing address kept until now.
     *
     * @param  array<string, mixed>|null  $details
     */
    public function saveBilling(User $user, ?array $details): ?Address
    {
        if ($details === null) {
            $this->saved($user, 'billing')->delete();

            return null;
        }

        return $this->store($user, 'billing', $details);
    }

    /**
     * An address as the flat strings a form binds to, empty when there is none yet.
     *
     * @return array<string, string>
     */
    public static function form(?Address $address): array
    {
        $form = [];

        foreach (self::FIELDS as $field) {
            $form[$field] = (string) ($address?->getAttribute($field) ?? '');
        }

        return $form;
    }

    /**
     * Rules for one address form. A person gives a name and an address; a company gives its
     * name, its tax code and its registered office instead of a name.
     *
     * The prefix is the property the form binds into ('billing' for billing.city), or '' for a
     * component that keeps the fields flat.
     *
     * @return array<string, list<string>>
     */
    public static function rules(string $prefix, string $kind = 'person'): array
    {
        $key = self::keyer($prefix);

        $place = [
            $key('line_1') => ['required', 'string', 'max:180'],
            $key('city') => ['required', 'string', 'max:80'],
            $key('county') => ['nullable', 'string', 'max:80'],
            $key('postal_code') => ['nullable', 'string', 'max:16'],
        ];

        if ($kind === 'company') {
            return [
                $key('company') => ['required', 'string', 'max:160'],
                // A Romanian tax code is 2 to 10 digits, written with RO in front when the firm
                // is registered for VAT.
                $key('vat_number') => ['required', 'string', 'max:20', 'regex:/^(RO)?\s*\d{2,10}$/i'],
                $key('trade_register_number') => ['nullable', 'string', 'max:40'],
                ...$place,
            ];
        }

        return [
            $key('first_name') => ['required', 'string', 'max:60'],
            $key('last_name') => ['required', 'string', 'max:60'],
            ...$place,
        ];
    }

    /** @return array<string, string> */
    public static function messages(string $prefix): array
    {
        $key = self::keyer($prefix);

        return [
            $key('first_name').'.required' => 'Scrie prenumele.',
            $key('last_name').'.required' => 'Scrie numele de familie.',
            $key('line_1').'.required' => 'Scrie strada și numărul.',
            $key('city').'.required' => 'Scrie localitatea.',
            $key('company').'.required' => 'Scrie denumirea firmei.',
            $key('vat_number').'.required' => 'Scrie codul fiscal (CUI).',
            $key('vat_number').'.regex' => 'Codul fiscal are între 2 și 10 cifre, cu sau fără RO în față.',
        ];
    }

    /**
     * @param  array<string, mixed>  $form
     * @return array<string, string|null>
     */
    public static function shippingDetails(array $form): array
    {
        return [
            'first_name' => self::clean($form['first_name'] ?? null) ?? '',
            'last_name' => self::clean($form['last_name'] ?? null) ?? '',
            ...self::place($form),
        ];
    }

    /**
     * What an invoice address is stored as. A firm is invoiced under its own name, tax code and
     * registered office; the name columns still take the person ordering, who is the contact on
     * the invoice and the one the shop calls about it.
     *
     * @param  array<string, mixed>  $form
     * @param  array{first_name: string, last_name: string}  $contact
     * @return array<string, string|null>
     */
    public static function billingDetails(string $kind, array $form, array $contact): array
    {
        if ($kind !== 'company') {
            return [
                'company' => null,
                'vat_number' => null,
                'trade_register_number' => null,
                ...self::shippingDetails($form),
            ];
        }

        $register = self::clean($form['trade_register_number'] ?? null);

        return [
            'company' => self::clean($form['company'] ?? null),
            // Stored the way it is printed: upper case, no spaces, so "ro 123" and "RO123" are
            // the same firm.
            'vat_number' => strtoupper((string) preg_replace('/\s+/', '', (string) ($form['vat_number'] ?? ''))),
            'trade_register_number' => $register === null ? null : strtoupper($register),
            'first_name' => self::clean($contact['first_name']) ?? '',
            'last_name' => self::clean($contact['last_name']) ?? '',
            ...self::place($form),
        ];
    }

    /**
     * @param  array<string, mixed>  $form
     * @return array<string, string|null>
     */
    private static function place(array $form): array
    {
        return [
            'line_1' => self::clean($form['line_1'] ?? null) ?? '',
            'city' => self::clean($form['city'] ?? null) ?? '',
            'county' => self::clean($form['county'] ?? null),
            'postal_code' => self::clean($form['postal_code'] ?? null),
            'country_code' => 'RO',
        ];
    }

    private static function clean(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    /** @return Closure(string): string */
    private static function keyer(string $prefix): Closure
    {
        return static fn (string $field): string => $prefix === '' ? $field : $prefix.'.'.$field;
    }

    private function saved(User $user, string $type): Builder
    {
        return Address::query()
            ->where('user_id', $user->id)
            ->where('type', $type)
            ->where('is_default', true);
    }

    /** @param  array<string, mixed>  $details */
    private function store(User $user, string $type, array $details): Address
    {
        return Address::query()->updateOrCreate(
            ['user_id' => $user->id, 'type' => $type, 'is_default' => true],
            $details,
        );
    }
}
