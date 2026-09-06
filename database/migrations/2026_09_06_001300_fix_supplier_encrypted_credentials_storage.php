<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql' || $this->postgresDataType() !== 'jsonb') {
            return;
        }

        $legacyRows = DB::select('SELECT id, credentials::text AS credentials FROM suppliers WHERE credentials IS NOT NULL');

        DB::statement('ALTER TABLE suppliers ALTER COLUMN credentials TYPE TEXT USING credentials::text');

        foreach ($legacyRows as $row) {
            $plain = json_decode((string) $row->credentials, true);
            if (! is_array($plain)) {
                // A JSON string may contain ciphertext from a previous manual
                // workaround. Preserve it when it is already decryptable.
                if (is_string($plain) && $this->isEncryptedValue($plain)) {
                    DB::table('suppliers')->where('id', $row->id)->update(['credentials' => $plain]);
                }

                continue;
            }

            DB::table('suppliers')->where('id', $row->id)->update([
                'credentials' => Crypt::encryptString(json_encode($plain, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)),
            ]);
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql' || $this->postgresDataType() !== 'text') {
            return;
        }

        $encryptedRows = DB::table('suppliers')
            ->select(['id', 'credentials'])
            ->whereNotNull('credentials')
            ->get();
        $plainRows = [];

        foreach ($encryptedRows as $row) {
            try {
                $decoded = json_decode(Crypt::decryptString((string) $row->credentials), true, flags: JSON_THROW_ON_ERROR);
            } catch (Throwable) {
                continue;
            }

            if (is_array($decoded)) {
                $plainRows[(int) $row->id] = $decoded;
            }
        }

        // JSONB cannot hold Laravel ciphertext directly. Reverting this migration
        // necessarily restores the historical plaintext-JSON storage semantics.
        DB::table('suppliers')->update(['credentials' => null]);
        DB::statement('ALTER TABLE suppliers ALTER COLUMN credentials TYPE JSONB USING credentials::jsonb');

        foreach ($plainRows as $id => $credentials) {
            DB::update(
                'UPDATE suppliers SET credentials = CAST(? AS jsonb) WHERE id = ?',
                [json_encode($credentials, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), $id],
            );
        }
    }

    private function postgresDataType(): ?string
    {
        return DB::table('information_schema.columns')
            ->where('table_schema', 'public')
            ->where('table_name', 'suppliers')
            ->where('column_name', 'credentials')
            ->value('data_type');
    }

    private function isEncryptedValue(string $value): bool
    {
        try {
            Crypt::decryptString($value);

            return true;
        } catch (Throwable) {
            return false;
        }
    }
};
