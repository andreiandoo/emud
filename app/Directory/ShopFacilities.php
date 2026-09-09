<?php

namespace App\Directory;

/**
 * The fixed vocabularies a workshop listing is described with.
 *
 * Free text here would produce forty spellings of "card bancar" and make the filters useless,
 * which is the same reason the storefront picks category icons from a list. Anything stored
 * that is no longer offered is dropped on read rather than shown raw, so retiring an option
 * cannot leave an unlabelled chip on a public page.
 */
final class ShopFacilities
{
    /** @var array<string, string> */
    public const AMENITIES = [
        'waiting_room' => 'Sală de așteptare',
        'wifi' => 'Wi-Fi',
        'replacement_car' => 'Mașină de înlocuire',
        'pickup' => 'Ridicare și livrare auto',
        'towing' => 'Tractare',
        'card_payment_onsite' => 'Plată cu cardul la fața locului',
        'parking' => 'Parcare proprie',
        'wheelchair_access' => 'Acces persoane cu dizabilități',
        'evening_hours' => 'Program prelungit',
        'weekend_hours' => 'Deschis în weekend',
        'english_spoken' => 'Se vorbește engleză',
    ];

    /** @var array<string, string> */
    public const PAYMENT_METHODS = [
        'cash' => 'Numerar',
        'card' => 'Card bancar',
        'transfer' => 'Transfer bancar',
        'invoice' => 'Factură pe firmă',
        'leasing' => 'Decontare leasing',
        'insurance' => 'Decontare asigurare',
    ];

    /** @var array<string, string> */
    public const CERTIFICATIONS = [
        'rar_itp' => 'Stație ITP autorizată RAR',
        'rar_authorised' => 'Autorizat RAR',
        'bosch_service' => 'Bosch Car Service',
        'q_service' => 'Q-Service',
        'manufacturer_authorised' => 'Service autorizat de producător',
        'iso_9001' => 'ISO 9001',
        'ac_certified' => 'Certificat gaze fluorurate (climatizare)',
    ];

    /**
     * @param  iterable<mixed>|null  $stored
     * @param  array<string, string>  $vocabulary
     * @return array<string, string>
     */
    public static function labels(?iterable $stored, array $vocabulary): array
    {
        $labels = [];

        foreach ($stored ?? [] as $key) {
            if (is_string($key) && isset($vocabulary[$key])) {
                $labels[$key] = $vocabulary[$key];
            }
        }

        return $labels;
    }
}
