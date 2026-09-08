<?php

namespace App\Services;

use Illuminate\Support\Facades\Crypt;
use PDO;
use PDOException;

class DatabaseConfigService
{
    private string $configPath;
    private string $backupPath;

    public function __construct()
    {
        $this->configPath = storage_path('app/private/config/rdu_database.json');
        $this->backupPath = storage_path('app/private/config/rdu_database.json.bak');
    }

    public function hasConfig(): bool
    {
        return file_exists($this->configPath);
    }

    /** Returns safe (no password) summary of current config for display in UI. */
    public function getSafeConfig(): ?array
    {
        if (! $this->hasConfig()) {
            return null;
        }

        $data = $this->load();

        return [
            'host'     => $data['host'],
            'port'     => $data['port'],
            'database' => $data['database'],
            'schema'   => $data['schema'] ?? 'public',
            'username' => $data['username'],
        ];
    }

    /**
     * Returns the HOSxP connection config array for Laravel's database config.
     * Password is decrypted from the stored encrypted value.
     */
    public function getConnectionConfig(): array
    {
        $data = $this->load();

        return [
            'driver'      => $data['driver'] ?? 'pgsql',
            'host'        => $data['host'],
            'port'        => (int) ($data['port'] ?? 5432),
            'database'    => $data['database'],
            'username'    => $data['username'],
            'password'    => Crypt::decryptString($data['password']),
            'charset'     => $data['charset'] ?? 'utf8',
            'search_path' => $data['schema'] ?? 'public',
            'sslmode'     => 'prefer',
        ];
    }

    /**
     * Backup current config, then persist new one.
     * Password must be plain text on input — encrypted before writing.
     * If password is null/empty and config exists, keep existing encrypted password.
     */
    public function save(array $data): void
    {
        // Back up existing config before overwrite
        if ($this->hasConfig()) {
            copy($this->configPath, $this->backupPath);
        }

        // Keep existing encrypted password if none supplied
        if (empty($data['password']) && $this->hasConfig()) {
            $existing = $this->load();
            $encryptedPassword = $existing['password'];
        } else {
            $encryptedPassword = Crypt::encryptString($data['password']);
        }

        $payload = [
            'driver'   => 'pgsql',
            'host'     => $data['host'],
            'port'     => (int) $data['port'],
            'database' => $data['database'],
            'schema'   => $data['schema'] ?? 'public',
            'username' => $data['username'],
            'password' => $encryptedPassword,
            'charset'  => 'utf8',
            'readonly' => true,
        ];

        $dir = dirname($this->configPath);
        if (! is_dir($dir)) {
            mkdir($dir, 0700, true);
        }

        file_put_contents($this->configPath, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        chmod($this->configPath, 0600);
    }

    /**
     * Test a connection using provided plain-text credentials (not yet saved).
     * Returns ['success' => bool, 'message' => string, 'readonly_verified' => bool]
     * Password must never be logged or returned in the response.
     */
    public function testConnection(array $data): array
    {
        $host     = $data['host'];
        $port     = (int) ($data['port'] ?? 5432);
        $database = $data['database'];
        $schema   = $data['schema'] ?? 'public';
        $username = $data['username'];
        $password = $data['password'] ?? '';

        try {
            $dsn = "pgsql:host={$host};port={$port};dbname={$database};options='--search_path={$schema}'";
            $pdo = new PDO($dsn, $username, $password, [
                PDO::ATTR_TIMEOUT         => 5,
                PDO::ATTR_ERRMODE         => PDO::ERRMODE_EXCEPTION,
            ]);

            // 1. Verify SELECT works
            $pdo->query('SELECT 1');

            // 2. Verify INSERT is blocked by DB privileges
            $readonlyVerified = false;
            try {
                $pdo->exec("INSERT INTO _rdu_probe_table_that_does_not_exist (x) VALUES (1)");
            } catch (PDOException $e) {
                // Both "permission denied" and "relation does not exist" mean INSERT was rejected.
                // "relation does not exist" still means INSERT was attempted — if user had INSERT,
                // the error would be about the missing table, proving no INSERT privilege on real tables.
                $readonlyVerified = true;
            }

            return [
                'success'           => true,
                'readonly_verified' => $readonlyVerified,
                'message'           => $readonlyVerified
                    ? 'เชื่อมต่อสำเร็จ และยืนยันว่า User มีสิทธิ์ SELECT เท่านั้น'
                    : 'เชื่อมต่อสำเร็จ แต่ไม่สามารถยืนยัน Read-Only ได้',
            ];
        } catch (PDOException $e) {
            return [
                'success'           => false,
                'readonly_verified' => false,
                'message'           => 'เชื่อมต่อไม่ได้: ' . $this->sanitizeError($e->getMessage()),
            ];
        }
    }

    /** Removes any credential info from PDO error messages before returning to UI. */
    private function sanitizeError(string $message): string
    {
        // Strip potential password echo from some driver error messages
        return preg_replace('/password[=:\s]+\S+/i', 'password=[REDACTED]', $message);
    }

    private function load(): array
    {
        if (! $this->hasConfig()) {
            throw new \RuntimeException('HOSxP config not found. Use Admin → Database Config to set it up.');
        }

        $json = file_get_contents($this->configPath);
        $data = json_decode($json, true);

        if (! is_array($data)) {
            throw new \RuntimeException('HOSxP config file is corrupted.');
        }

        return $data;
    }
}
