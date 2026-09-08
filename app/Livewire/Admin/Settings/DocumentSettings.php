<?php

namespace App\Livewire\Admin\Settings;

use App\Settings\StoreSettings;
use Livewire\Component;

/**
 * Numbering series for orders, invoices and proformas.
 *
 * The next number is stored rather than derived from a count, because a count would reuse a
 * number after a document is deleted, and reissuing a number already given to a customer is a
 * problem with the tax authority rather than a display bug.
 */
class DocumentSettings extends Component
{
    /** @var array<string, array{prefix: string, next: int}> */
    public array $series = [];

    public string $saved = '';

    /** @var array<string, string> */
    private const SERIES = [
        'order' => 'Comenzi',
        'invoice' => 'Facturi',
        'proforma' => 'Proforme',
    ];

    public function mount(StoreSettings $settings): void
    {
        $stored = $settings->array('document_series');

        foreach (self::SERIES as $key => $label) {
            $this->series[$key] = [
                'prefix' => (string) ($stored[$key]['prefix'] ?? strtoupper(substr($key, 0, 3))),
                'next' => (int) ($stored[$key]['next'] ?? 1),
            ];
        }
    }

    public function save(StoreSettings $settings): void
    {
        $this->validate([
            'series.*.prefix' => ['required', 'string', 'max:12', 'regex:/^[A-Z0-9-]+$/'],
            'series.*.next' => ['required', 'integer', 'min:1'],
        ], [
            'series.*.prefix.regex' => 'Prefixul poate conține doar majuscule, cifre și cratime.',
        ]);

        $settings->put('documents', ['document_series' => $this->series]);

        $this->saved = 'Seriile de documente au fost salvate.';
    }

    public function render()
    {
        return view('livewire.admin.settings.document-settings', ['labels' => self::SERIES]);
    }
}
