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
    public const FREE_TRIAL_DURATION_SECONDS = 259200;
    public const PAID_DURATION_SECONDS = [604800, 1296000, 2592000, 5184000, 7776000];

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

        // vip_users is the source of truth for membership visibility. The paid
        // ledger and its write permissions are checkout capabilities; a missing
        // table, incomplete migration or insufficient grant must not hide a
        // user's existing VIP status or allow a payment that cannot be applied.
        $paidLedgerAvailable = false;
        try {
            // Probe schema and write grants using zero-row statements. The
            // SELECTs validate every column; INSERT...SELECT has no source table
            // and WHERE 1=0 guarantees that no row is inserted or trigger fired.
            $this->pdo->query(
                'SELECT order_ref, steamid64, account_id, sid, vip_level, vip_group,
                        duration_seconds, tier_price_rial, granted_at, expires
                 FROM fragcity_vip_paid_orders
                 LIMIT 0'
            );
            $this->pdo->exec(
                'INSERT INTO fragcity_vip_paid_orders
                    (order_ref, steamid64, account_id, sid, vip_level, vip_group,
                     duration_seconds, tier_price_rial, granted_at, expires)
                 SELECT
                    CAST(REPEAT(\'a\', 32) AS CHAR(32)),
                    CAST(REPEAT(\'0\', 17) AS CHAR(17)),
                    CAST(0 AS UNSIGNED),
                    CAST(0 AS UNSIGNED),
                    CAST(1 AS UNSIGNED),
                    CAST(\'BRONZE\' AS CHAR(16)),
                    CAST(604800 AS UNSIGNED),
                    CAST(0 AS UNSIGNED),
                    CAST(1 AS UNSIGNED),
                    CAST(604801 AS UNSIGNED)
                 WHERE 1 = 0'
            );
            $this->pdo->query(
                'SELECT `account_id`, `name`, `lastvisit`, `sid`, `group`, `expires`
                 FROM `vip_users`
                 LIMIT 0'
            );
            $this->pdo->exec(
                'INSERT INTO `vip_users`
                    (`account_id`, `name`, `lastvisit`, `sid`, `group`, `expires`)
                 SELECT
                    CAST(0 AS UNSIGNED),
                    CAST(\'FragCity\' AS CHAR(64)),
                    CAST(0 AS UNSIGNED),
                    CAST(0 AS UNSIGNED),
                    CAST(\'BRONZE\' AS CHAR(64)),
                    CAST(0 AS UNSIGNED)
                 WHERE 1 = 0'
            );
            $this->pdo->exec(
                'UPDATE `vip_users`
                 SET `name` = `name`, `lastvisit` = `lastvisit`,
                     `group` = `group`, `expires` = `expires`
                 WHERE 1 = 0'
            );
            $paidLedgerAvailable = true;
        } catch (PDOException $e) {
            error_log('[FragCity VIPCore] Paid checkout storage unavailable; SQLSTATE ' . $e->getCode());
        }

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
                'paid_purchase' => null,
                'paid_ledger_available' => $paidLedgerAvailable,
                'server_id' => $this->serverId,
            ];
        }

        $group = strtoupper(trim((string)($row['group'] ?? '')));
        $expires = (int)($row['expires'] ?? 0);
        $level = self::levelForGroup($group);
        $active = $level > 0 && ($expires === 0 || $expires > time());
        $paidPurchase = null;

        // A current membership is eligible for upgrade credit only when its
        // exact active VIPCore row is backed by a paid-order ledger entry.
        // Free Silver claims never create this paid ledger record.
        if ($active && $expires > 0 && $paidLedgerAvailable) {
            try {
                $paidStatement = $this->pdo->prepare(
                    'SELECT order_ref, vip_level, duration_seconds, granted_at, expires, tier_price_rial
                     FROM fragcity_vip_paid_orders
                     WHERE account_id = :account_id
                       AND sid = :server_id
                       AND vip_level = :vip_level
                       AND expires = :expires
                     ORDER BY granted_at DESC
                     LIMIT 1'
                );
                $paidStatement->execute([
                    ':account_id' => $accountId,
                    ':server_id' => $this->serverId,
                    ':vip_level' => $level,
                    ':expires' => $expires,
                ]);
                $paidRow = $paidStatement->fetch(PDO::FETCH_ASSOC);

                if (is_array($paidRow)
                    && preg_match('/^[a-f0-9]{32}$/D', (string)($paidRow['order_ref'] ?? ''))
                    && (int)($paidRow['duration_seconds'] ?? 0) > 0
                    && (int)($paidRow['expires'] ?? 0) === $expires) {
                    $paidPurchase = [
                        'order_ref' => (string)$paidRow['order_ref'],
                        'level' => (int)$paidRow['vip_level'],
                        'duration_seconds' => (int)$paidRow['duration_seconds'],
                        'tier_price_rial' => (int)($paidRow['tier_price_rial'] ?? 0) > 0
                            ? (int)$paidRow['tier_price_rial']
                            : null,
                        'granted_at' => (int)$paidRow['granted_at'],
                        'expires' => (int)$paidRow['expires'],
                    ];
                }
            } catch (PDOException $e) {
                $paidLedgerAvailable = false;
                $paidPurchase = null;
                error_log('[FragCity VIPCore] Paid-order ledger lookup failed; SQLSTATE ' . $e->getCode());
            }
        }

        return [
            'active' => $active,
            'level' => $active ? $level : 0,
            'group' => $active ? $group : null,
            'expires' => $active ? $expires : null,
            'paid_purchase' => $active ? $paidPurchase : null,
            'paid_ledger_available' => $paidLedgerAvailable,
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
     * Grant exactly one three-day Silver trial per SteamID64.
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

    /**
     * Grant a paid public-store membership exactly once per verified website order.
     * A paid upgrade preserves the existing expiry; a free-trial/legacy membership
     * can only be replaced by a full-price higher tier.
     */
    public function grantPaidMembership(
        string $orderRef,
        string $steamId64,
        string $playerName,
        int $level,
        int $durationSeconds,
        int $tierPriceRial,
        ?array $upgrade = null
    ): array {
        if (!preg_match('/^[a-f0-9]{32}$/D', $orderRef)) {
            throw new InvalidArgumentException('Invalid VIP payment order reference.');
        }

        if (!in_array($durationSeconds, self::PAID_DURATION_SECONDS, true)) {
            throw new InvalidArgumentException('Invalid paid VIP duration.');
        }

        if ($tierPriceRial < 1 || $tierPriceRial > 10000000000000 || $tierPriceRial % 10 !== 0) {
            throw new InvalidArgumentException('Invalid paid VIP tier price snapshot.');
        }

        if ($upgrade !== null) {
            $fromOrderRef = $upgrade['from_order_ref'] ?? null;
            $fromLevel = filter_var($upgrade['from_level'] ?? null, FILTER_VALIDATE_INT);
            $fromExpires = filter_var($upgrade['from_expires'] ?? null, FILTER_VALIDATE_INT);
            $upgradeDuration = filter_var($upgrade['duration_seconds'] ?? null, FILTER_VALIDATE_INT);

            if (!is_string($fromOrderRef)
                || !preg_match('/^[a-f0-9]{32}$/D', $fromOrderRef)
                || $fromLevel === false
                || !in_array((int)$fromLevel, [1, 2], true)
                || (int)$level <= (int)$fromLevel
                || $fromExpires === false
                || (int)$fromExpires < 1
                || $upgradeDuration === false
                || (int)$upgradeDuration !== $durationSeconds) {
                throw new InvalidArgumentException('Invalid paid VIP upgrade context.');
            }

            $upgrade = [
                'from_order_ref' => $fromOrderRef,
                'from_level' => (int)$fromLevel,
                'from_expires' => (int)$fromExpires,
                'duration_seconds' => (int)$upgradeDuration,
            ];
        }

        $accountId = self::accountIdFromSteamId64($steamId64);
        $group = self::groupForLevel($level);
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

        $findOrder = function () use ($orderRef): array|false {
            $statement = $this->pdo->prepare(
                'SELECT order_ref, steamid64, account_id, sid, vip_level,
                        vip_group, duration_seconds, tier_price_rial, granted_at, expires
                 FROM fragcity_vip_paid_orders
                 WHERE order_ref = :order_ref
                 LIMIT 1'
            );
            $statement->execute([':order_ref' => $orderRef]);
            return $statement->fetch(PDO::FETCH_ASSOC);
        };

        $formatExisting = function (array $row) use (
            $orderRef,
            $steamId64,
            $accountId,
            $level,
            $group,
            $durationSeconds,
            $tierPriceRial,
            $upgrade
        ): array {
            if ((string)($row['steamid64'] ?? '') !== $steamId64
                || (int)($row['account_id'] ?? -1) !== $accountId
                || (int)($row['sid'] ?? -1) !== $this->serverId
                || (int)($row['vip_level'] ?? 0) !== $level
                || strtoupper(trim((string)($row['vip_group'] ?? ''))) !== $group
                || (int)($row['duration_seconds'] ?? 0) !== $durationSeconds
                || ((int)($row['tier_price_rial'] ?? 0) > 0
                    && (int)$row['tier_price_rial'] !== $tierPriceRial)) {
                throw new RuntimeException('VIP paid-order reference does not match its original grant.');
            }

            return [
                'granted' => true,
                'already_granted' => true,
                'order_ref' => $orderRef,
                'account_id' => $accountId,
                'level' => $level,
                'group' => $group,
                'granted_at' => (int)$row['granted_at'],
                'expires' => (int)$row['expires'],
                'duration_seconds' => $durationSeconds,
                'tier_price_rial' => (int)($row['tier_price_rial'] ?? 0) > 0
                    ? (int)$row['tier_price_rial']
                    : $tierPriceRial,
                'upgraded' => $upgrade !== null,
                'server_id' => $this->serverId,
            ];
        };

        // Fast idempotent path for browser refreshes and repeated callbacks.
        $existingOrder = $findOrder();
        if (is_array($existingOrder)) {
            return $formatExisting($existingOrder);
        }

        $now = time();
        $expires = $now + $durationSeconds;
        $wasUpgrade = $upgrade !== null;
        $this->pdo->beginTransaction();

        try {
            // Lock the live VIP row so two separate paid orders cannot race.
            $existingMembership = $this->pdo->prepare(
                'SELECT `group`, `expires`
                 FROM `vip_users`
                 WHERE `account_id` = :account_id
                   AND `sid` = :server_id
                 LIMIT 1 FOR UPDATE'
            );
            $existingMembership->execute([
                ':account_id' => $accountId,
                ':server_id' => $this->serverId,
            ]);
            $membership = $existingMembership->fetch(PDO::FETCH_ASSOC);

            $currentLevel = 0;
            $currentExpires = 0;
            $currentActive = false;
            if (is_array($membership)) {
                $currentGroup = strtoupper(trim((string)($membership['group'] ?? '')));
                $currentLevel = self::levelForGroup($currentGroup);
                $currentExpires = (int)($membership['expires'] ?? 0);
                $currentActive = $currentLevel > 0
                    && ($currentExpires === 0 || $currentExpires > $now);
            }

            if ($upgrade !== null) {
                if (!$currentActive
                    || $currentLevel !== $upgrade['from_level']
                    || $currentExpires !== $upgrade['from_expires']
                    || $level <= $currentLevel) {
                    throw new VipCoreMembershipConflict('membership_upgrade_source_changed');
                }

                $sourceOrder = $this->pdo->prepare(
                    'SELECT order_ref, duration_seconds
                     FROM fragcity_vip_paid_orders
                     WHERE order_ref = :order_ref
                       AND account_id = :account_id
                       AND sid = :server_id
                       AND vip_level = :vip_level
                       AND expires = :expires
                     LIMIT 1 FOR UPDATE'
                );
                $sourceOrder->execute([
                    ':order_ref' => $upgrade['from_order_ref'],
                    ':account_id' => $accountId,
                    ':server_id' => $this->serverId,
                    ':vip_level' => $currentLevel,
                    ':expires' => $currentExpires,
                ]);
                $source = $sourceOrder->fetch(PDO::FETCH_ASSOC);

                if (!is_array($source)
                    || (int)($source['duration_seconds'] ?? 0) !== $upgrade['duration_seconds']
                    || $upgrade['duration_seconds'] !== $durationSeconds) {
                    throw new VipCoreMembershipConflict('membership_upgrade_source_changed');
                }

                // Upgrade applies immediately but never discards unused paid time.
                $expires = $currentExpires;
            } elseif ($currentActive) {
                if ($level < $currentLevel) {
                    throw new VipCoreMembershipConflict('membership_already_active');
                }

                // An active paid membership must use the discounted upgrade path.
                // A non-paid active membership (trial, test grant or legacy/manual
                // grant) may be converted at the same tier or replaced by a higher
                // tier after charging the selected catalogue price in full.
                $paidCurrent = $this->pdo->prepare(
                    'SELECT order_ref
                     FROM fragcity_vip_paid_orders
                     WHERE account_id = :account_id
                       AND sid = :server_id
                       AND vip_level = :vip_level
                       AND expires = :expires
                     ORDER BY granted_at DESC
                     LIMIT 1 FOR UPDATE'
                );
                $paidCurrent->execute([
                    ':account_id' => $accountId,
                    ':server_id' => $this->serverId,
                    ':vip_level' => $currentLevel,
                    ':expires' => $currentExpires,
                ]);
                if ($paidCurrent->fetch(PDO::FETCH_ASSOC) !== false) {
                    throw new VipCoreMembershipConflict('paid_membership_upgrade_required');
                }
            }

            // The order ledger and VIPCore upsert share one transaction.
            $ledgerInsert = $this->pdo->prepare(
                'INSERT INTO fragcity_vip_paid_orders
                    (order_ref, steamid64, account_id, sid, vip_level,
                     vip_group, duration_seconds, tier_price_rial, granted_at, expires)
                 VALUES
                    (:order_ref, :steamid64, :account_id, :server_id, :vip_level,
                     :vip_group, :duration_seconds, :tier_price_rial, :granted_at, :expires)'
            );
            $ledgerInsert->execute([
                ':order_ref' => $orderRef,
                ':steamid64' => $steamId64,
                ':account_id' => $accountId,
                ':server_id' => $this->serverId,
                ':vip_level' => $level,
                ':vip_group' => $group,
                ':duration_seconds' => $durationSeconds,
                ':tier_price_rial' => $tierPriceRial,
                ':granted_at' => $now,
                ':expires' => $expires,
            ]);

            // Match VIPCore's INSERT / ON DUPLICATE KEY semantics.
            $membershipInsert = $this->pdo->prepare(
                'INSERT INTO `vip_users`
                    (`account_id`, `name`, `lastvisit`,
                     `sid`, `group`, `expires`)
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
                 WHERE `account_id` = :account_id
                   AND `sid` = :server_id
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
                throw new RuntimeException('VIPCore paid membership read-after-write verification failed.');
            }

            $this->pdo->commit();
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            // A retry of the same verified order returns the original grant.
            if ($e instanceof PDOException) {
                $driverCode = (int)($e->errorInfo[1] ?? 0);
                if ((string)$e->getCode() === '23000' && $driverCode === 1062) {
                    $committedOrder = $findOrder();
                    if (is_array($committedOrder)) {
                        return $formatExisting($committedOrder);
                    }
                }
            }
            throw $e;
        }

        return [
            'granted' => true,
            'already_granted' => false,
            'order_ref' => $orderRef,
            'account_id' => $accountId,
            'level' => $level,
            'group' => $group,
            'granted_at' => $now,
            'expires' => $expires,
            'duration_seconds' => $durationSeconds,
            'upgraded' => $wasUpgrade,
            'server_id' => $this->serverId,
        ];
    }

}
