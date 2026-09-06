<?php

namespace App\Suppliers\Transport;

use App\Models\Supplier;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class SftpSupplierFilesystemFactory
{
    public function build(Supplier $supplier): FilesystemAdapter
    {
        $credentials = $supplier->credentials ?? [];
        $settings = $supplier->settings ?? [];
        $host = trim((string) ($credentials['host'] ?? ''));
        $username = trim((string) ($credentials['username'] ?? ''));
        $fingerprint = trim((string) ($credentials['host_fingerprint'] ?? ''));

        if ($host === '' || $username === '') {
            throw new RuntimeException("SFTP supplier {$supplier->code} requires host and username credentials.");
        }

        if ($fingerprint === '' && ! (bool) ($settings['allow_unverified_sftp_host'] ?? false)) {
            throw new RuntimeException("SFTP supplier {$supplier->code} requires a host fingerprint unless allow_unverified_sftp_host is explicitly enabled.");
        }

        $config = [
            'driver' => 'sftp',
            'host' => $host,
            'username' => $username,
            'port' => max(1, (int) ($credentials['port'] ?? 22)),
            'root' => (string) ($settings['sftp_root'] ?? ''),
            'timeout' => max(1, (int) ($settings['sftp_timeout_seconds'] ?? 30)),
            'maxTries' => max(1, (int) ($settings['sftp_max_tries'] ?? 4)),
            'useAgent' => (bool) ($settings['sftp_use_agent'] ?? false),
            'throw' => true,
        ];

        foreach ([
            'password' => 'password',
            'private_key' => 'privateKey',
            'passphrase' => 'passphrase',
            'host_fingerprint' => 'hostFingerprint',
        ] as $credentialKey => $configKey) {
            if (filled($credentials[$credentialKey] ?? null)) {
                $config[$configKey] = $credentials[$credentialKey];
            }
        }

        if (! isset($config['password']) && ! isset($config['privateKey']) && ! $config['useAgent']) {
            throw new RuntimeException("SFTP supplier {$supplier->code} requires password, private key, or SSH agent authentication.");
        }

        return Storage::build($config);
    }
}
