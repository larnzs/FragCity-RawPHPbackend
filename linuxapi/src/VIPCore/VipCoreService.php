<?php
declare(strict_types=1);

/** Narrow API-owned adapter for the existing VIPCore vip_users table. */
final class VipCoreMembershipConflict extends RuntimeException
{
}

final class VipCoreFreeTrialAlreadyClaimed extends RuntimeException
{
}

final class VipCoreService
{
    public const FREE_TRIAL_GROUP = 'SILVER';
    public const FREE_TRIAL_DURATION_SECONDS = 604800;

    private const GROUPS = [
        'BRONZE' => 1,
        'SILVER' => 2,
        'GOLD' => 3,
        'PLATINUM' => 4,
        'EMERALD' => 5,
        'DIAMOND' => 6,
    ];

    private const TEST_GROUPS = [
        1 => 'BRONZE',
        2 => 'SILVER',
        3 => 'GOLD',
    ];

    public function __construct(
        private readonly PDO $pdo,
        private readonly int $serverId,
        private readonly int $testDurationSeconds
    ) {
        if ($serverId < 0 || $serverId > 2147483647) {
            throw new InvalidArgumentException('Invalid VIPCore ServerId.');
        }
        if ($testDurationSeconds < 300 || $testDurationSeconds > 86400) {
            throw new InvalidArgumentException('Invalid VIP test duration.');
        }
    }

    public static function accountIdFromSteamId64(string $steamId64): int
    {
        if (!preg_match('/^[0-9]{17}$/D', $steamId64)) {
            throw new InvalidArgumentException('Invalid SteamID64.');
        }

        $accountId = (int)$steamId64 - 76561197960265728;
        if ($accountId < 0 || $accountId > 4294967295) {
            throw new InvalidArgumentException('SteamID64 is outside the Steam account ID range.');
        }

        return $accountId;
    }

    public static function groupForLevel(int $level): string
    {
        if (!isset(self::TEST_GROUPS[$level])) {
            throw new InvalidArgumentException('Only public VIP tiers 1-3 can be granted in test mode.');
        }

        return self::TEST_GROUPS[$level];
    }

    /**
     * Temporary no-payment grants are fail-closed unless an exact SteamID64
     * is explicitly listed in the private Linux runtime configuration.
     */
    public static function isTestGrantAllowed(string $steamId64, array $allowedSteamIds): bool
    {
        if (!preg_match('/^[0-9]{17}$/D', $steamId64)) {
            return false;
        }

        foreach ($allowedSteamIds as $allowedSteamId) {
            if (is_string($allowedSteamId)
                && preg_match('/^[0-9]{17}$/D', $allowedSteamId)
                && hash_equals($allowedSteamId, $steamId64)) {
                return true;
            }
        }

        return false;
    }

    public static function levelForGroup(string $group): int
    {
        return self::GROUPS[strtoupper(trim($group))] ?? 0;
    }

    public static function isRealMembershipGroup(string $group): bool
    {
        // Bronze is a dummy/test tier; Silver and above are real memberships.
        return self::levelForGroup($group) >= 2;
    }

    public function ping(): array
    {
        $count = (int)$this->pdo->query('SELECT COUNT(*) FROM `vip_users`')->fetchColumn();

        return [
            'database' => 'ok',
            'table' => 'vip_users',
            'row_count' => $count,
            'server_id' => $this->serverId,
        ];
    }

    public function getStatus(string $steamId64): array
    {
        $accountId = self::accountIdFromSteamId64($steamId64);
        $statement = $this->pdo->prepare(
            'SELECT `group`, `expires`
             FROM `vip_users`
             WHERE `account_id` = :account_id AND `sid` = :server_id
             LIMIT 1'
        );
        $statement->execute([
            ':account_id' => $accountId,
            ':server_id' => $this->serverId,
        ]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        if (!is_array($row)) {
            return [
                'active' => false,
                'level' => 0,
                'group' => null,
                'expires' => null,
                'server_id' => $this->serverId,
            ];
        }

        $group = strtoupper(trim((string)($row['group'] ?? '')));
        $expires = (int)($row['expires'] ?? 0);
        $level = self::levelForGroup($group);
        $active = $level > 0 && ($expires === 0 || $expires > time());

        return [
            'active' => $active,
            'level' => $active ? $level : 0,
            'group' => $active ? $group : null,
            'expires' => $active ? $expires : null,
            'server_id' => $this->serverId,
        ];
    }

    /**
     * Read the one-time Silver trial state. The claim ledger is deliberately
     * separate from vip_users because VIPCore deletes expired membership rows.
     */
    public function getFreeTrialStatus(string $steamId64): array
    {
        $accountId = self::accountIdFromSteamId64($steamId64);

        $claimStatement = $this->pdo->prepare(
            'SELECT `vip_group`, `claimed_at`, `expires`
             FROM `fragcity_vip_free_trial_claims`
             WHERE `steamid64` = :steamid64
             LIMIT 1'
        );
        $claimStatement->execute([':steamid64' => $steamId64]);
        $claim = $claimStatement->fetch(PDO::FETCH_ASSOC);

        $membershipStatement = $this->pdo->prepare(
            'SELECT `group`, `expires`
             FROM `vip_users`
             WHERE `account_id` = :account_id AND `sid` = :server_id
             LIMIT 1'
        );
        $membershipStatement->execute([
            ':account_id' => $accountId,
            ':server_id' => $this->serverId,
        ]);
        $membership = $membershipStatement->fetch(PDO::FETCH_ASSOC);

        $now = time();
        $membershipGroup = is_array($membership)
            ? strtoupper(trim((string)($membership['group'] ?? '')))
            : '';
        $membershipExpires = is_array($membership) ? (int)($membership['expires'] ?? 0) : null;
        // Bronze is a test-only dummy tier and does not count as a real membership.
        $hasActiveMembership = self::isRealMembershipGroup($membershipGroup)
            && ($membershipExpires === 0 || ($membershipExpires !== null && $membershipExpires > $now));
        $claimed = is_array($claim);

        return [
            'available' => !$claimed && !$hasActiveMembership,
            'claimed' => $claimed,
            'blocked_by_active_membership' => !$claimed && $hasActiveMembership,
            'claimed_at' => $claimed ? (int)$claim['claimed_at'] : null,
            'expires' => $claimed ? (int)$claim['expires'] : null,
            'group' => $claimed
                ? (string)$claim['vip_group']
                : ($hasActiveMembership ? $membershipGroup : null),
            'server_id' => $this->serverId,
        ];
    }

    /**
     * Grant exactly one seven-day Silver trial per SteamID64.
     * The unique claim ledger and vip_users upsert are committed atomically.
     */
    public function grantFreeSilverTrial(string $steamId64, string $playerName): array
    {
        $accountId = self::accountIdFromSteamId64($steamId64);
        $group = self::FREE_TRIAL_GROUP;
        $playerName = trim($playerName);

        if ($playerName === ''
            || preg_match('//u', $playerName) !== 1
            || preg_match('/[\\x00-\\x1F\\x7F]/', $playerName)) {
            throw new InvalidArgumentException('Invalid player name.');
        }

        $nameLength = function_exists('mb_strlen')
            ? mb_strlen($playerName, 'UTF-8')
            : strlen($playerName);
        if ($nameLength > 64) {
            $playerName = function_exists('mb_substr')
                ? mb_substr($playerName, 0, 64, 'UTF-8')
                : substr($playerName, 0, 64);
        }

        $now = time();
        $expires = $now + self::FREE_TRIAL_DURATION_SECONDS;

        $this->pdo->beginTransaction();
        try {
            $existingClaim = $this->pdo->prepare(
                'SELECT `steamid64`
                 FROM `fragcity_vip_free_trial_claims`
                 WHERE `steamid64` = :steamid64
                 LIMIT 1 FOR UPDATE'
            );
            $existingClaim->execute([':steamid64' => $steamId64]);
            if ($existingClaim->fetch(PDO::FETCH_ASSOC) !== false) {
                throw new VipCoreFreeTrialAlreadyClaimed('free_trial_already_claimed');
            }

            $existingMembership = $this->pdo->prepare(
                'SELECT `group`, `expires`
                 FROM `vip_users`
                 WHERE `account_id` = :account_id AND `sid` = :server_id
                 LIMIT 1 FOR UPDATE'
            );
            $existingMembership->execute([
                ':account_id' => $accountId,
                ':server_id' => $this->serverId,
            ]);
            $membership = $existingMembership->fetch(PDO::FETCH_ASSOC);

            if (is_array($membership)) {
                $currentGroup = strtoupper(trim((string)($membership['group'] ?? '')));
                $currentExpires = (int)($membership['expires'] ?? 0);
                // Ignore Bronze test records; Silver and above are real memberships.
                if (self::isRealMembershipGroup($currentGroup)
                    && ($currentExpires === 0 || $currentExpires > $now)) {
                    throw new VipCoreMembershipConflict('membership_already_active');
                }
            }

            // The SteamID64 primary key makes the free trial globally one-time,
            // even after VIPCore has purged the expired vip_users row.
            $claimInsert = $this->pdo->prepare(
                'INSERT INTO `fragcity_vip_free_trial_claims`
                    (`steamid64`, `account_id`, `sid`, `vip_group`, `claimed_at`, `expires`)
                 VALUES
                    (:steamid64, :account_id, :server_id, :vip_group, :claimed_at, :expires)'
            );
            $claimInsert->execute([
                ':steamid64' => $steamId64,
                ':account_id' => $accountId,
                ':server_id' => $this->serverId,
                ':vip_group' => $group,
                ':claimed_at' => $now,
                ':expires' => $expires,
            ]);

            // Keep the exact INSERT/ON DUPLICATE KEY semantics used by VIPCore.
            $membershipInsert = $this->pdo->prepare(
                'INSERT INTO `vip_users`
                    (`account_id`, `name`, `lastvisit`, `sid`, `group`, `expires`)
                 VALUES
                    (:account_id, :name, :lastvisit, :server_id, :vip_group, :expires)
                 ON DUPLICATE KEY UPDATE
                    `name` = VALUES(`name`),
                    `lastvisit` = VALUES(`lastvisit`),
                    `group` = VALUES(`group`),
                    `expires` = VALUES(`expires`)'
            );
            $membershipInsert->execute([
                ':account_id' => $accountId,
                ':name' => $playerName,
                ':lastvisit' => $now,
                ':server_id' => $this->serverId,
                ':vip_group' => $group,
                ':expires' => $expires,
            ]);

            $verify = $this->pdo->prepare(
                'SELECT `group`, `expires`
                 FROM `vip_users`
                 WHERE `account_id` = :account_id AND `sid` = :server_id
                 LIMIT 1'
            );
            $verify->execute([
                ':account_id' => $accountId,
                ':server_id' => $this->serverId,
            ]);
            $saved = $verify->fetch(PDO::FETCH_ASSOC);

            if (!is_array($saved)
                || strtoupper(trim((string)($saved['group'] ?? ''))) !== $group
                || (int)($saved['expires'] ?? 0) !== $expires) {
                throw new RuntimeException('VIPCore read-after-write verification failed.');
            }

            $this->pdo->commit();
        } catch (PDOException $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            $driverCode = (int)($e->errorInfo[1] ?? 0);
            if ((string)$e->getCode() === '23000' && $driverCode === 1062) {
                throw new VipCoreFreeTrialAlreadyClaimed('free_trial_already_claimed', 0, $e);
            }
            throw $e;
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }

        return [
            'granted' => true,
            'account_id' => $accountId,
            'level' => 2,
            'group' => $group,
            'claimed_at' => $now,
            'expires' => $expires,
            'duration_seconds' => self::FREE_TRIAL_DURATION_SECONDS,
            'server_id' => $this->serverId,
        ];
    }

    public function grantTestMembership(string $steamId64, string $playerName, int $level): array
    {
        $accountId = self::accountIdFromSteamId64($steamId64);
        $group = self::groupForLevel($level);
        $playerName = trim($playerName);

        if ($playerName === ''
            || preg_match('//u', $playerName) !== 1
            || preg_match('/[\x00-\x1F\x7F]/', $playerName)) {
            throw new InvalidArgumentException('Invalid player name.');
        }

        $nameLength = function_exists('mb_strlen')
            ? mb_strlen($playerName, 'UTF-8')
            : strlen($playerName);
        if ($nameLength > 64) {
            $playerName = function_exists('mb_substr')
                ? mb_substr($playerName, 0, 64, 'UTF-8')
                : substr($playerName, 0, 64);
        }

        $now = time();
        $expires = $now + $this->testDurationSeconds;

        $this->pdo->beginTransaction();
        try {
            $existingStatement = $this->pdo->prepare(
                'SELECT `group`, `expires`
                 FROM `vip_users`
                 WHERE `account_id` = :account_id AND `sid` = :server_id
                 LIMIT 1 FOR UPDATE'
            );
            $existingStatement->execute([
                ':account_id' => $accountId,
                ':server_id' => $this->serverId,
            ]);
            $existing = $existingStatement->fetch(PDO::FETCH_ASSOC);

            if (is_array($existing)) {
                $existingExpires = (int)($existing['expires'] ?? 0);
                if ($existingExpires === 0 || $existingExpires > $now) {
                    throw new VipCoreMembershipConflict('membership_already_active');
                }
            }

            // Match VIPCore's own upsert semantics.
            $insert = $this->pdo->prepare(
                'INSERT INTO `vip_users`
                    (`account_id`, `name`, `lastvisit`, `sid`, `group`, `expires`)
                 VALUES
                    (:account_id, :name, :lastvisit, :server_id, :vip_group, :expires)
                 ON DUPLICATE KEY UPDATE
                    `name` = VALUES(`name`),
                    `lastvisit` = VALUES(`lastvisit`),
                    `group` = VALUES(`group`),
                    `expires` = VALUES(`expires`)'
            );
            $insert->execute([
                ':account_id' => $accountId,
                ':name' => $playerName,
                ':lastvisit' => $now,
                ':server_id' => $this->serverId,
                ':vip_group' => $group,
                ':expires' => $expires,
            ]);

            $verify = $this->pdo->prepare(
                'SELECT `group`, `expires`
                 FROM `vip_users`
                 WHERE `account_id` = :account_id AND `sid` = :server_id
                 LIMIT 1'
            );
            $verify->execute([
                ':account_id' => $accountId,
                ':server_id' => $this->serverId,
            ]);
            $saved = $verify->fetch(PDO::FETCH_ASSOC);

            if (!is_array($saved)
                || strtoupper((string)($saved['group'] ?? '')) !== $group
                || (int)($saved['expires'] ?? 0) !== $expires) {
                throw new RuntimeException('VIPCore read-after-write verification failed.');
            }

            $this->pdo->commit();
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }

        return [
            'granted' => true,
            'account_id' => $accountId,
            'level' => $level,
            'group' => $group,
            'expires' => $expires,
            'duration_seconds' => $this->testDurationSeconds,
            'server_id' => $this->serverId,
        ];
    }
}
