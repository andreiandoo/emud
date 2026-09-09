<?php

namespace App\Console\Commands;

use App\Models\CatalogPart;
use App\Models\Supplier;
use App\Models\SupplierProduct;
use Illuminate\Console\Command;

/**
 * Every switch that stops a supplier from producing canonical parts fails silently: promotion
 * that is off makes the job return without a word, create-parts that is off parks every product
 * on "awaiting_canonical_part", and a list field mapped without its delimiter turns a whole
 * comma-separated column into one nonsense reference. Today the only way to discover any of
 * that is to run the import and read the statuses afterwards.
 */
class CheckSupplierOnboarding extends Command
{
    protected $signature = 'suppliers:onboarding-check
        {supplier? : Codul furnizorului}
        {--all : Verifică toți furnizorii}';

    protected $description = 'Verifică, înainte de import, ce împiedică un furnizor să producă piese canonice.';

    /**
     * Feed column => the settings key that says how to split it. A list field mapped without
     * its delimiter is not an error the importer reports; it just yields one element holding
     * the entire raw string.
     */
    private const LIST_FIELDS = [
        'oe_numbers' => 'oe_numbers_delimiter',
        'iam_numbers' => 'iam_numbers_delimiter',
        'cross_references' => 'cross_references_delimiter',
        'supersessions' => 'supersessions_delimiter',
        'fitments' => 'fitments_delimiter',
        'images' => 'images_delimiter',
    ];

    public function handle(): int
    {
        $suppliers = $this->suppliers();

        if ($suppliers->isEmpty()) {
            $this->error('Niciun furnizor găsit. Dă un cod sau folosește --all.');

            return self::FAILURE;
        }

        $blocked = 0;

        foreach ($suppliers as $supplier) {
            $findings = $this->inspect($supplier);
            $failures = array_filter($findings, static fn (array $f): bool => $f[0] === 'BLOCHEAZĂ');

            $this->newLine();
            $this->line("<options=bold>{$supplier->code}</> — {$supplier->name}");
            $this->table(['', 'Verificare', 'Detaliu'], array_map(
                static fn (array $f): array => [
                    match ($f[0]) {
                        'BLOCHEAZĂ' => '<fg=red>✕</>',
                        'ATENȚIE' => '<fg=yellow>!</>',
                        default => '<fg=green>✓</>',
                    },
                    $f[1],
                    $f[2],
                ],
                $findings,
            ));

            if ($failures !== []) {
                $blocked++;
            }
        }

        $this->newLine();

        if ($blocked > 0) {
            $this->warn("{$blocked} furnizor(i) nu pot produce piese canonice în starea actuală.");

            return self::FAILURE;
        }

        $this->info('Toți furnizorii verificați pot produce piese canonice.');

        return self::SUCCESS;
    }

    /** @return \Illuminate\Support\Collection<int, Supplier> */
    private function suppliers()
    {
        $code = $this->argument('supplier');

        if ($code) {
            return Supplier::query()->where('code', $code)->get();
        }

        return $this->option('all') ? Supplier::query()->orderBy('code')->get() : collect();
    }

    /** @return list<array{0:string,1:string,2:string}> */
    private function inspect(Supplier $supplier): array
    {
        return [
            ...$this->rightsFindings($supplier),
            ...$this->switchFindings($supplier),
            ...$this->mappingFindings($supplier),
            ...$this->importedFindings($supplier),
        ];
    }

    /** @return list<array{0:string,1:string,2:string}> */
    private function rightsFindings(Supplier $supplier): array
    {
        $missing = array_keys(array_filter([
            'allow_internal_data' => ! $supplier->allow_internal_data,
            'allow_derived_data' => ! $supplier->allow_derived_data,
        ]));

        return [$missing === []
            ? ['OK', 'Drepturi de date', 'allow_internal_data și allow_derived_data sunt active']
            : ['BLOCHEAZĂ', 'Drepturi de date', 'Lipsesc: '.implode(', ', $missing).'. Produsele devin blocked_rights.'],
        ];
    }

    /** @return list<array{0:string,1:string,2:string}> */
    private function switchFindings(Supplier $supplier): array
    {
        $settings = $supplier->settings ?? [];
        $enabled = (bool) ($settings['technical_promotion_enabled'] ?? false);
        $createParts = (bool) ($settings['technical_promotion_create_parts'] ?? false);
        $findings = [];

        $findings[] = $enabled
            ? ['OK', 'Promovare tehnică', 'technical_promotion_enabled este activ']
            : ['BLOCHEAZĂ', 'Promovare tehnică', 'technical_promotion_enabled este oprit; jobul iese fără să facă nimic.'];

        // Augment-only is a legitimate choice for every supplier after the first: it links to
        // parts that already exist rather than inventing new identities. It is only fatal while
        // there is no canonical part in the catalogue to link to.
        if ($createParts) {
            $findings[] = ['OK', 'Creare de piese', 'technical_promotion_create_parts este activ; acest furnizor va crea piese canonice.'];
        } elseif (CatalogPart::query()->exists()) {
            $findings[] = ['ATENȚIE', 'Creare de piese', 'Mod augment-only: se leagă doar de piese existente, restul rămân awaiting_canonical_part.'];
        } else {
            $findings[] = ['BLOCHEAZĂ', 'Creare de piese', 'Catalogul de piese e gol și technical_promotion_create_parts e oprit; nimic nu se poate promova.'];
        }

        return $findings;
    }

    /** @return list<array{0:string,1:string,2:string}> */
    private function mappingFindings(Supplier $supplier): array
    {
        $mapping = $supplier->field_mapping ?? [];
        $settings = $supplier->settings ?? [];
        $findings = [];

        // Without these the mapper discards the row outright, before any of the rest matters.
        $essential = array_values(array_filter(['external_id', 'name'], static fn (string $k): bool => blank($mapping[$k] ?? null)));
        $findings[] = $essential === []
            ? ['OK', 'Câmpuri obligatorii', 'external_id și name sunt mapate']
            : ['BLOCHEAZĂ', 'Câmpuri obligatorii', 'Lipsesc din field_mapping: '.implode(', ', $essential).'. Fiecare rând e aruncat la import.'];

        $identity = array_values(array_filter(['brand', 'manufacturer_part_number'], static fn (string $k): bool => blank($mapping[$k] ?? null)));
        $findings[] = $identity === []
            ? ['OK', 'Identitate de piesă', 'brand și manufacturer_part_number sunt mapate']
            : ['BLOCHEAZĂ', 'Identitate de piesă', 'Lipsesc din field_mapping: '.implode(', ', $identity).'. Tot devine insufficient_identity.'];

        $enrichment = array_values(array_filter(
            ['ean', 'oe_numbers', 'iam_numbers', 'cross_references', 'supersessions', 'fitments', 'attributes'],
            static fn (string $k): bool => blank($mapping[$k] ?? null),
        ));
        $findings[] = $enrichment === []
            ? ['OK', 'Date de îmbogățire', 'Toate câmpurile tehnice sunt mapate']
            : ['ATENȚIE', 'Date de îmbogățire', 'Nemapate: '.implode(', ', $enrichment).'. Piesele se creează, dar fără ele.'];

        $undelimited = array_values(array_filter(
            array_keys(self::LIST_FIELDS),
            static fn (string $field): bool => filled($mapping[$field] ?? null) && blank($settings[self::LIST_FIELDS[$field]] ?? null),
        ));

        if ($undelimited !== []) {
            $findings[] = ['ATENȚIE', 'Delimitatori de listă', 'Mapate fără delimitator în settings: '.implode(', ', $undelimited).'. Dacă feed-ul nu e JSON, toată coloana devine o singură valoare.'];
        }

        return $findings;
    }

    /**
     * Only meaningful once a catalogue sync has run: before that there is nothing to count, and
     * saying "0 products have an identity" would read as a failure rather than as "not yet".
     *
     * @return list<array{0:string,1:string,2:string}>
     */
    private function importedFindings(Supplier $supplier): array
    {
        $total = SupplierProduct::query()->where('supplier_id', $supplier->id)->count();

        if ($total === 0) {
            return [['OK', 'Produse importate', 'Niciun import încă; rulează suppliers:sync '.$supplier->code.' --mode=catalog']];
        }

        $withIdentity = SupplierProduct::query()
            ->where('supplier_id', $supplier->id)
            ->whereNotNull('raw_brand')
            ->whereNotNull('manufacturer_part_number')
            ->count();

        $statuses = SupplierProduct::query()
            ->where('supplier_id', $supplier->id)
            ->selectRaw('technical_promotion_status, count(*) as n')
            ->groupBy('technical_promotion_status')
            ->pluck('n', 'technical_promotion_status')
            ->map(static fn (int $n, ?string $status): string => ($status ?: 'null').'='.number_format($n))
            ->implode(', ');

        $percent = $total > 0 ? round($withIdentity / $total * 100) : 0;

        return [
            [$withIdentity === 0 ? 'BLOCHEAZĂ' : ($percent < 50 ? 'ATENȚIE' : 'OK'),
                'Identitate în date',
                number_format($withIdentity).' din '.number_format($total)." produse au brand+MPN ({$percent}%)"],
            ['OK', 'Stare promovare', $statuses],
        ];
    }
}
