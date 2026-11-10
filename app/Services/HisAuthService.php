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


        return $this->syncLocalUser($username, $officer->officer_name ?? null);
    }

    /**
     * HOSxP officer → local users table (Laravel auth needs a local record).
     *
     * The local password is a random, unknowable value set ONCE, when the account is first taken over from HOSxP
     * (login always goes through HOSxP). It must not change on later logins: Laravel's "remember me" cookie is tied
     * to the current password hash, so re-randomising it on every login signed the user out of every other browser.
     */
    public function syncLocalUser(string $username, ?string $name): User
    {
        $user = User::firstOrNew(['username' => $username]);
        $user->name = $name ?: $username;

        $syncedEmail = $username . '@rdu.local';
        if ($user->email !== $syncedEmail) {
            // New account, or a local one (known password) being taken over by HOSxP — first time only.
            $user->email    = $syncedEmail;
            $user->password = Hash::make(uniqid('', true));
        }

        $user->save();

        return $user;
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
