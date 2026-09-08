<?php

namespace App\Livewire\Admin\Settings;

use App\Settings\StoreSettings;
use Livewire\Component;

/**
 * The legal identity of the company operating the shop.
 *
 * These appear on invoices and in the terms, so they are validated on the way in rather than
 * left as free text: a wrong CUI on a issued invoice is a correction with the tax authority,
 * not an edit.
 */
class CompanySettings extends Component
{
    public string $company_name = '';

    public string $company_vat_id = '';

    public string $company_registration_number = '';

    public bool $company_vat_payer = false;

    public string $company_bank_name = '';

    public string $company_iban = '';

    public string $company_address = '';

    public string $company_city = '';

    public string $company_county = '';

    public string $company_country = 'România';

    public string $saved = '';

    /** @var list<string> */
    private const KEYS = [
        'company_name', 'company_vat_id', 'company_registration_number', 'company_vat_payer',
        'company_bank_name', 'company_iban', 'company_address', 'company_city',
        'company_county', 'company_country',
    ];

    public function mount(StoreSettings $settings): void
    {
        foreach (self::KEYS as $key) {
            if ($key === 'company_vat_payer') {
                $this->company_vat_payer = $settings->bool($key);

                continue;
            }

            $this->{$key} = $settings->string($key, $key === 'company_country' ? 'România' : '');
        }
    }

    public function save(StoreSettings $settings): void
    {
        $data = $this->validate([
            'company_name' => ['required', 'string', 'max:180'],
            // RO CUI: optional RO prefix then up to ten digits. Checked here because it is
            // printed on documents that are hard to correct afterwards.
            'company_vat_id' => ['nullable', 'string', 'regex:/^(RO)?\d{2,10}$/i'],
            'company_registration_number' => ['nullable', 'string', 'regex:/^[JFC]\d{1,2}\/\d{1,7}\/\d{4}$/i'],
            'company_bank_name' => ['nullable', 'string', 'max:120'],
            'company_iban' => ['nullable', 'string', 'regex:/^[A-Z]{2}\d{2}[A-Z0-9]{11,30}$/i'],
            'company_address' => ['nullable', 'string', 'max:180'],
            'company_city' => ['nullable', 'string', 'max:96'],
            'company_county' => ['nullable', 'string', 'max:64'],
            'company_country' => ['required', 'string', 'max:64'],
        ], [
            'company_vat_id.regex' => 'CUI-ul trebuie să fie de forma RO12345678 sau 12345678.',
            'company_registration_number.regex' => 'Numărul de registru trebuie să fie de forma J12/345/2020.',
            'company_iban.regex' => 'IBAN-ul nu are un format valid.',
        ]);

        $settings->put('company', [
            ...array_map(fn (?string $value): ?string => $value === '' ? null : $value, $data),
            'company_vat_id' => $data['company_vat_id'] ? strtoupper($data['company_vat_id']) : null,
            'company_iban' => $data['company_iban'] ? strtoupper(str_replace(' ', '', $data['company_iban'])) : null,
            'company_vat_payer' => $this->company_vat_payer,
        ]);

        $this->saved = 'Datele firmei au fost salvate.';
    }

    public function render()
    {
        return view('livewire.admin.settings.company-settings');
    }
}
