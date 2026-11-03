<?php
declare(strict_types=1);

require_once __DIR__ . '/PaymenterApiClient.php';

/**
 * Coordinates the local FragCity user row with the remote Paymenter identity.
 * Plaintext passwords exist only for the duration of the authenticated request.
 */
final class PaymenterAccountSyncService
{
    /**
     * @param PDO $pdo FragCity's main website database connection.
     * @param array<string,mixed> $user A freshly fetched row from FragCity users.
     * @return array{success:bool,user_id?:int,created?:bool,error?:string}
     */
    public static function sync(PDO $pdo, array $user, string $plainPassword): array
    {
        $steamId = (string) ($user['steamid64'] ?? '');
        $email = trim((string) ($user['email'] ?? ''));
        $phone = trim((string) ($user['phone'] ?? ''));

        if (!preg_match('/^[0-9]{17}$/D', $steamId)) {
            return ['success' => false, 'error' => 'invalid_steamid'];
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 100) {
            self::markFailed($pdo, $steamId, 'email_required');
            return ['success' => false, 'error' => 'email_required'];
        }
        if (!preg_match('/^09[0-9]{9}$/D', $phone)) {
            self::markFailed($pdo, $steamId, 'phone_required');
            return ['success' => false, 'error' => 'phone_required'];
        }
        if (!self::isStrongPassword($plainPassword)) {
            self::markFailed($pdo, $steamId, 'password_policy');
            return ['success' => false, 'error' => 'password_policy'];
        }

        $username = trim((string) ($user['username'] ?? ''));
        if ($username === '') {
            $username = $steamId;
        }

        try {
            $client = new PaymenterApiClient();
            $remote = $client->syncUser([
                'steamid64' => $steamId,
                'email' => $email,
                'phone' => $phone,
                'first_name' => mb_substr($username, 0, 255),
                'last_name' => '',
                'password' => $plainPassword,
            ]);

            $statement = $pdo->prepare(
                "UPDATE users
                 SET paymenter_user_id = ?,
                     paymenter_sync_status = 'synced',
                     paymenter_synced_at = NOW(),
                     paymenter_sync_error = NULL
                 WHERE steamid64 = ?
                 LIMIT 1"
            );
            $statement->execute([$remote['user_id'], $steamId]);

            if ($statement->rowCount() < 1) {
                // MySQL may report zero changed rows for identical values. Verify
                // the link still exists before treating the operation as failed.
                $verify = $pdo->prepare(
                    'SELECT paymenter_user_id FROM users WHERE steamid64 = ? LIMIT 1'
                );
                $verify->execute([$steamId]);
                $storedId = $verify->fetchColumn();
                if ((int) $storedId !== (int) $remote['user_id']) {
                    throw new RuntimeException('local_paymenter_link_not_saved');
                }
            }

            return [
                'success' => true,
                'user_id' => (int) $remote['user_id'],
                'created' => (bool) $remote['created'],
            ];
        } catch (Throwable $e) {
            $error = self::safeErrorCode($e->getMessage());
            self::markFailed($pdo, $steamId, $error);

            error_log(
                '[FragCity] Paymenter account sync failed for SteamID hash='
                . substr(hash('sha256', $steamId), 0, 16)
                . '; error=' . $error . '.'
            );

            return ['success' => false, 'error' => $error];
        }
    }

    public static function markPending(PDO $pdo, string $steamId): void
    {
        $statement = $pdo->prepare(
            "UPDATE users
             SET paymenter_sync_status = 'pending',
                 paymenter_sync_error = NULL
             WHERE steamid64 = ?
             LIMIT 1"
        );
        $statement->execute([$steamId]);
    }

    public static function isStrongPassword(string $password): bool
    {
        if (strlen($password) < 8 || strlen($password) > 255) {
            return false;
        }

        return preg_match('/\p{L}/u', $password) === 1
            && preg_match('/[0-9]/', $password) === 1;
    }

    private static function markFailed(PDO $pdo, string $steamId, string $error): void
    {
        try {
            $statement = $pdo->prepare(
                "UPDATE users
                 SET paymenter_sync_status = 'failed',
                     paymenter_sync_error = ?
                 WHERE steamid64 = ?
                 LIMIT 1"
            );
            $statement->execute([substr($error, 0, 64), $steamId]);
        } catch (Throwable $e) {
            error_log('[FragCity] Could not persist Paymenter sync status; SQLSTATE=' . (string) $e->getCode() . '.');
        }
    }

    private static function safeErrorCode(string $message): string
    {
        $allowed = [
            'paymenter_unreachable',
            'paymenter_invalid_response',
            'bridge_not_configured',
            'source_ip_not_allowed',
            'invalid_signature_headers',
            'invalid_signature',
            'request_replayed',
            'invalid_json_body',
            'validation_failed',
            'linked_paymenter_user_missing',
            'email_already_linked_to_another_paymenter_user',
            'paymenter_user_already_linked_to_another_steam_account',
            'email_already_in_use',
            'account_sync_failed',
        ];

        return in_array($message, $allowed, true)
            ? $message
            : 'paymenter_sync_failed';
    }
}
