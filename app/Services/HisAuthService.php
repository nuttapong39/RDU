<?php

namespace App\Services;

use App\Database\RduDatabaseConnectionFactory;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class HisAuthService
{
    public function __construct(
        private DatabaseConfigService $configService,
        private RduDatabaseConnectionFactory $connectionFactory
    ) {}

    /**
     * Authenticate against HOSxP's officer table.
     * On success, creates/syncs a local User record for Laravel's session.
     * Returns null on failure (wrong credentials or HOSxP unavailable).
     */
    public function attempt(string $username, string $password): ?User
    {
        if (! $this->isAvailable()) {
            return null;
        }

        try {
            $connection = $this->connectionFactory->make();

            $officer = $connection->table('officer')
                ->where('officer_login_name', $username)
                ->select(['officer_login_name', 'officer_login_password_md5', 'officer_login_password', 'officer_name'])
                ->first();
        } catch (\Exception $e) {
            return null;
        }

        if (! $officer || ! $this->verifyPassword($password, $officer)) {
            return null;
        }


        // Sync HOSxP officer → local users table (Laravel auth needs a local record)
        return User::updateOrCreate(
            ['username' => $username],
            [
                'name'     => $officer->officer_name ?? $username,
                'email'    => $username . '@rdu.local',
                'password' => Hash::make(uniqid('', true)), // random — login always goes through HOSxP
            ]
        );
    }

    public function isAvailable(): bool
    {
        return $this->configService->hasConfig();
    }

    private function verifyPassword(string $input, object $officer): bool
    {
        // HOSxP stores MD5 in officer_login_password_md5 (primary)
        if (! empty($officer->officer_login_password_md5)) {
            return hash_equals(
                strtolower((string) $officer->officer_login_password_md5),
                md5($input)
            );
        }

        // Fallback: some versions store in officer_login_password (plain or MD5)
        if (! empty($officer->officer_login_password)) {
            // Try MD5 match first, then plain text
            return hash_equals(md5($input), strtolower((string) $officer->officer_login_password))
                || hash_equals((string) $officer->officer_login_password, $input);
        }

        return false;
    }
}
