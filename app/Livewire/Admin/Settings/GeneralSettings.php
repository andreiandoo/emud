<?php

namespace App\Livewire\Admin\Settings;

use App\Settings\StoreSettings;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithFileUploads;

class GeneralSettings extends Component
{
    use WithFileUploads;

    public string $site_title = '';

    public string $site_tagline = '';

    public string $site_description = '';

    public string $default_currency = 'RON';

    public string $timezone = 'Europe/Bucharest';

    public mixed $logoUpload = null;

    public mixed $faviconUpload = null;

    public string $saved = '';

    public function mount(StoreSettings $settings): void
    {
        $this->site_title = $settings->string('site_title', config('app.name'));
        $this->site_tagline = $settings->string('site_tagline');
        $this->site_description = $settings->string('site_description');
        $this->default_currency = $settings->string('default_currency', config('emud.catalog.default_currency', 'RON'));
        $this->timezone = $settings->string('timezone', 'Europe/Bucharest');
    }

    public function save(StoreSettings $settings): void
    {
        $data = $this->validate([
            'site_title' => ['required', 'string', 'max:120'],
            'site_tagline' => ['nullable', 'string', 'max:180'],
            // Kept under the length search engines actually render, so the owner sees the limit
            // here rather than discovering a truncated snippet later.
            'site_description' => ['nullable', 'string', 'max:160'],
            'default_currency' => ['required', 'string', Rule::in(array_keys(config('emud.catalog.currencies', ['RON' => 'RON'])))],
            'timezone' => ['required', 'timezone'],
            'logoUpload' => ['nullable', 'image', 'max:2048'],
            // No SVG for the favicon: it is served to every visitor on every page, and an SVG
            // can carry script.
            'faviconUpload' => ['nullable', 'image', 'mimes:png,ico', 'max:512'],
        ]);

        $values = [
            'site_title' => $data['site_title'],
            'site_tagline' => $data['site_tagline'] ?: null,
            'site_description' => $data['site_description'] ?: null,
            'default_currency' => strtoupper($data['default_currency']),
            'timezone' => $data['timezone'],
        ];

        if ($this->logoUpload) {
            $values['logo_path'] = $this->logoUpload->store('branding', 'public');
        }

        if ($this->faviconUpload) {
            $values['favicon_path'] = $this->faviconUpload->store('branding', 'public');
        }

        $settings->put('general', $values);

        $this->reset(['logoUpload', 'faviconUpload']);
        $this->saved = 'Setările generale au fost salvate.';
    }

    public function render(StoreSettings $settings)
    {
        return view('livewire.admin.settings.general-settings', [
            'logoPath' => $settings->string('logo_path'),
            'faviconPath' => $settings->string('favicon_path'),
        ]);
    }
}
