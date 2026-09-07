<?php

namespace App\Livewire\Admin\CatalogPlatform;

use Illuminate\Support\Facades\Schema;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Throwable;

#[Layout('layouts::admin')]
class SchemaExplorer extends Component
{
    #[Url]
    public string $search = '';

    #[Url(as: 'table')]
    public ?string $selectedTable = null;

    public function selectTable(string $table): void
    {
        $this->selectedTable = $table;
    }

    public function render()
    {
        $allTables = collect(Schema::getTables())
            ->map(fn (array $table) => [
                'name' => (string) ($table['name'] ?? ''),
                'schema' => $table['schema'] ?? null,
                'size' => $table['size'] ?? null,
                'comment' => $table['comment'] ?? null,
            ])
            ->filter(fn (array $table) => $table['name'] !== '')
            ->sortBy('name')
            ->values();

        $tables = $allTables
            ->when($this->search !== '', fn ($tables) => $tables->filter(
                fn (array $table) => str_contains(strtolower($table['name']), strtolower($this->search)),
            ))
            ->values();

        $selected = $this->selectedTable;
        if (! $selected || ! $allTables->contains(fn (array $table) => $table['name'] === $selected)) {
            $selected = $tables->first()['name'] ?? $allTables->first()['name'] ?? null;
            $this->selectedTable = $selected;
        }

        $columns = [];
        $indexes = [];
        $foreignKeys = [];
        $error = null;

        if ($selected) {
            try {
                $columns = Schema::getColumns($selected);
                $indexes = Schema::getIndexes($selected);
                $foreignKeys = Schema::getForeignKeys($selected);
            } catch (Throwable $exception) {
                report($exception);
                $error = $exception->getMessage();
            }
        }

        return view('livewire.admin.catalog-platform.schema-explorer', [
            'tables' => $tables,
            'tableCount' => $allTables->count(),
            'selected' => $selected,
            'columns' => $columns,
            'indexes' => $indexes,
            'foreignKeys' => $foreignKeys,
            'introspectionError' => $error,
        ]);
    }
}
