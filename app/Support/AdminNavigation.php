<?php

namespace App\Support;

/**
 * The back-office menu, grouped by the job being done rather than by the table behind it.
 *
 * The flat list this replaces had twenty-two entries in one column, which meant the operator had
 * to know the system's internals to find anything: "Surse catalog" and "Produse magazin" sat
 * next to each other despite belonging to different jobs on different days.
 *
 * Groups are ordered by how often they are opened, not alphabetically.
 */
class AdminNavigation
{
    /**
     * @return list<array{label: string, items: list<array{route: string, label: string, icon: string}>}>
     */
    public static function groups(): array
    {
        return [
            [
                'label' => 'Magazin',
                'items' => [
                    ['route' => 'admin.dashboard', 'label' => 'Panou general', 'icon' => 'grid'],
                    ['route' => 'admin.orders.index', 'label' => 'Comenzi', 'icon' => 'receipt'],
                    ['route' => 'admin.returns.index', 'label' => 'Retururi', 'icon' => 'undo'],
                    ['route' => 'admin.customers.index', 'label' => 'Clienți', 'icon' => 'users'],
                ],
            ],
            [
                'label' => 'Catalog magazin',
                'items' => [
                    ['route' => 'admin.products.index', 'label' => 'Produse', 'icon' => 'box'],
                    ['route' => 'admin.categories.index', 'label' => 'Categorii', 'icon' => 'tree'],
                    ['route' => 'admin.attributes.index', 'label' => 'Filtre & atribute', 'icon' => 'sliders'],
                    ['route' => 'admin.brands.index', 'label' => 'Branduri', 'icon' => 'tag'],
                    ['route' => 'admin.media.index', 'label' => 'Bibliotecă media', 'icon' => 'image'],
                ],
            ],
            [
                'label' => 'Catalog tehnic',
                'items' => [
                    ['route' => 'admin.catalog-platform.explorer', 'label' => 'Explorer', 'icon' => 'search'],
                    ['route' => 'admin.vehicles.index', 'label' => 'Compatibilitate auto', 'icon' => 'car'],
                    ['route' => 'admin.catalog-platform.quality', 'label' => 'Calitate & acoperire', 'icon' => 'chart'],
                    ['route' => 'admin.catalog-platform.conflicts', 'label' => 'Conflicte & QA', 'icon' => 'alert'],
                    ['route' => 'admin.catalog-platform.unresolved-relations', 'label' => 'Relații nerezolvate', 'icon' => 'link'],
                ],
            ],
            [
                'label' => 'Date & surse',
                'items' => [
                    ['route' => 'admin.catalog-platform.sources', 'label' => 'Surse catalog', 'icon' => 'download'],
                    ['route' => 'admin.catalog-platform.imports', 'label' => 'Importuri', 'icon' => 'history'],
                    ['route' => 'admin.catalog-platform.schema', 'label' => 'Structură bază de date', 'icon' => 'database'],
                    ['route' => 'admin.catalog-platform.api', 'label' => 'Catalog API', 'icon' => 'key'],
                ],
            ],
            [
                'label' => 'Furnizori',
                'items' => [
                    ['route' => 'admin.suppliers.index', 'label' => 'Furnizori', 'icon' => 'truck'],
                    ['route' => 'admin.suppliers.sync-runs', 'label' => 'Sincronizări', 'icon' => 'refresh'],
                    ['route' => 'admin.catalog-platform.supplier-matching', 'label' => 'Potrivire cu catalogul', 'icon' => 'shuffle'],
                ],
            ],
            [
                'label' => 'Conținut',
                'items' => [
                    ['route' => 'admin.pages.index', 'label' => 'Pagini statice', 'icon' => 'file'],
                    ['route' => 'admin.articles.index', 'label' => 'Articole & ghiduri', 'icon' => 'book'],
                    ['route' => 'admin.service-shops.index', 'label' => 'Service auto', 'icon' => 'wrench'],
                ],
            ],
        ];
    }

    /**
     * Kept out of the groups above and pinned to the bottom of the sidebar, the way settings
     * behave in every tool an operator already uses.
     *
     * @return list<array{route: string, label: string, icon: string}>
     */
    public static function footerItems(): array
    {
        return [
            ['route' => 'admin.commerce.settings', 'label' => 'Plăți & livrare', 'icon' => 'settings'],
        ];
    }

    /** Every route the menu points at, for asserting none of them is dead. @return list<string> */
    public static function routeNames(): array
    {
        $routes = array_column(self::footerItems(), 'route');

        foreach (self::groups() as $group) {
            $routes = [...$routes, ...array_column($group['items'], 'route')];
        }

        return $routes;
    }
}
