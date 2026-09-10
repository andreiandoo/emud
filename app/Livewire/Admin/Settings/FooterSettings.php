<?php

namespace App\Livewire\Admin\Settings;

use App\Settings\StoreSettings;
use App\Storefront\FooterMenu;
use Livewire\Component;

/**
 * The parts of the footer that are words rather than data.
 *
 * The link columns build themselves from the categories, collections and pages that exist, so
 * there is nothing to configure there — only the sign-up copy, the short blurb and the payment
 * methods the shop wants to name.
 */
class FooterSettings extends Component
{
    /** What a Romanian shop realistically accepts; the list is fixed so the footer never shows a typo. */
    public const PAYMENT_METHODS = [
        'visa' => 'Visa',
        'mastercard' => 'Mastercard',
        'maestro' => 'Maestro',
        'paypal' => 'PayPal',
        'apple-pay' => 'Apple Pay',
        'google-pay' => 'Google Pay',
        'ramburs' => 'Ramburs la curier',
        'transfer' => 'Transfer bancar',
        'rate' => 'Plata în rate',
    ];

    public bool $footer_newsletter_enabled = true;

    public string $footer_newsletter_title = '';

    public string $footer_newsletter_text = '';

    public string $footer_about = '';

    public string $footer_note = '';

    /** @var list<string> */
    public array $footer_payment_methods = [];

    public string $saved = '';

    public function mount(StoreSettings $settings): void
    {
        $this->footer_newsletter_enabled = $settings->bool('footer_newsletter_enabled', true);
        $this->footer_newsletter_title = $settings->string('footer_newsletter_title');
        $this->footer_newsletter_text = $settings->string('footer_newsletter_text');
        $this->footer_about = $settings->string('footer_about');
        $this->footer_note = $settings->string('footer_note');
        $this->footer_payment_methods = array_values(array_intersect(
            array_map('strval', $settings->array('footer_payment_methods')),
            array_keys(self::PAYMENT_METHODS),
        ));
    }

    public function save(StoreSettings $settings): void
    {
        $data = $this->validate([
            'footer_newsletter_title' => ['nullable', 'string', 'max:80'],
            'footer_newsletter_text' => ['nullable', 'string', 'max:240'],
            'footer_about' => ['nullable', 'string', 'max:400'],
            'footer_note' => ['nullable', 'string', 'max:240'],
        ], [], [
            'footer_newsletter_title' => 'titlul de abonare',
            'footer_about' => 'textul de prezentare',
        ]);

        $settings->put('footer', [
            'footer_newsletter_enabled' => $this->footer_newsletter_enabled,
            'footer_newsletter_title' => $data['footer_newsletter_title'] ?: null,
            'footer_newsletter_text' => $data['footer_newsletter_text'] ?: null,
            'footer_about' => $data['footer_about'] ?: null,
            'footer_note' => $data['footer_note'] ?: null,
            // Filtered against the fixed list rather than trusted: the checkboxes are the only
            // intended source, and an unknown key would render as a blank pill.
            'footer_payment_methods' => array_values(array_intersect(
                $this->footer_payment_methods,
                array_keys(self::PAYMENT_METHODS),
            )),
        ]);

        // The columns are cached for fifteen minutes on every page, so an edit here has to drop
        // the cache or it looks like nothing happened.
        FooterMenu::forget();

        $this->saved = 'Setările de footer au fost salvate.';
    }

    public function render()
    {
        return view('livewire.admin.settings.footer-settings', ['methods' => self::PAYMENT_METHODS]);
    }
}
