<?php

namespace App\Livewire\Admin\Settings;

use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Shell for the settings screens.
 *
 * Everything used to sit on one page, which meant scrolling past payment credentials to change
 * a phone number. The tab lives in the URL so a specific screen can be linked to and reloading
 * does not throw the operator back to the first tab.
 */
#[Layout('layouts::admin')]
class SettingsPage extends Component
{
    /** @var array<string, string> */
    public const TABS = [
        'general' => 'General',
        'header' => 'Header',
        'company' => 'Date firmă',
        'contact' => 'Contact',
        'documents' => 'Serii documente',
        'social' => 'Rețele sociale',
        'commerce' => 'Plăți & livrare',
    ];

    #[Url(except: 'general')]
    public string $tab = 'general';

    public function mount(): void
    {
        if (! array_key_exists($this->tab, self::TABS)) {
            $this->tab = 'general';
        }
    }

    public function render()
    {
        return view('livewire.admin.settings.settings-page', ['tabs' => self::TABS]);
    }
}
