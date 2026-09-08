<?php

namespace App\Livewire\Admin\Settings;

use App\Settings\StoreSettings;
use Livewire\Component;

class ContactSettings extends Component
{
    public string $contact_email = '';

    public string $contact_phone = '';

    /**
     * One row per weekday, so the shop can say "closed on Sunday" rather than forcing the owner
     * to write a sentence that no template can read back.
     *
     * @var array<int, array{day: string, from: string, to: string, closed: bool}>
     */
    public array $opening_hours = [];

    public string $saved = '';

    private const DAYS = ['Luni', 'Marți', 'Miercuri', 'Joi', 'Vineri', 'Sâmbătă', 'Duminică'];

    public function mount(StoreSettings $settings): void
    {
        $this->contact_email = $settings->string('contact_email');
        $this->contact_phone = $settings->string('contact_phone');

        $stored = collect($settings->array('opening_hours'))->keyBy('day');

        $this->opening_hours = collect(self::DAYS)->map(fn (string $day): array => [
            'day' => $day,
            'from' => (string) ($stored[$day]['from'] ?? '09:00'),
            'to' => (string) ($stored[$day]['to'] ?? '18:00'),
            'closed' => (bool) ($stored[$day]['closed'] ?? in_array($day, ['Sâmbătă', 'Duminică'], true)),
        ])->all();
    }

    public function save(StoreSettings $settings): void
    {
        $data = $this->validate([
            'contact_email' => ['required', 'email', 'max:255'],
            'contact_phone' => ['required', 'string', 'max:32'],
            'opening_hours.*.from' => ['nullable', 'date_format:H:i'],
            'opening_hours.*.to' => ['nullable', 'date_format:H:i'],
        ], [
            'opening_hours.*.from.date_format' => 'Orele trebuie scrise ca 09:00.',
            'opening_hours.*.to.date_format' => 'Orele trebuie scrise ca 09:00.',
        ]);

        $settings->put('contact', [
            'contact_email' => $data['contact_email'],
            'contact_phone' => $data['contact_phone'],
            // Closed days keep no hours: storing 09:00–18:00 alongside "closed" leaves two
            // answers to the same question for whatever reads it next.
            'opening_hours' => collect($this->opening_hours)->map(fn (array $row): array => [
                'day' => $row['day'],
                'closed' => (bool) $row['closed'],
                'from' => $row['closed'] ? null : $row['from'],
                'to' => $row['closed'] ? null : $row['to'],
            ])->values()->all(),
        ]);

        $this->saved = 'Datele de contact au fost salvate.';
    }

    public function render()
    {
        return view('livewire.admin.settings.contact-settings');
    }
}
