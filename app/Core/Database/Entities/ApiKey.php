<?php

namespace Flute\Core\Database\Entities;

use Cycle\ActiveRecord\ActiveRecord;
use Cycle\Annotated\Annotation\Column;
use Cycle\Annotated\Annotation\Entity;
use Cycle\Annotated\Annotation\Relation\ManyToMany;
use DateTimeImmutable;
use Cycle\ORM\Entity\Behavior;
use Throwable;

#[Entity]
#[Behavior\CreatedAt(
    field: 'createdAt',
    column: 'created_at'
)]
#[Behavior\UpdatedAt(
    field: 'updatedAt',
    column: 'updated_at'
)]
class ApiKey extends ActiveRecord
{
    private const HASH_PREFIX = 'sha256:';

    private const LAST_USED_TOUCH_TTL = 60;

    /** @var array<string,int> */
    private static array $lastUsedTouches = [];

    #[Column(type: "primary")]
    public int $id;

    #[Column(type: "string")]
    public string $key;

    #[Column(type: "string")]
    public string $name;

    #[Column(type: "datetime")]
    public DateTimeImmutable $createdAt;

    #[Column(type: "datetime", nullable: true)]
    public ?DateTimeImmutable $updatedAt = null;

    #[Column(type: "datetime", nullable: true)]
    public ?DateTimeImmutable $lastUsedAt = null;

    #[ManyToMany(target: Permission::class, through: ApiKeyPermission::class)]
    public array $permissions = [];

    public function getPermissions(): array
    {
        return array_map(function (Permission $permission) {
            return [
                'id' => $permission->id,
                'name' => $permission->name,
                'description' => $permission->desc
            ];
        }, $this->permissions);
    }

    public function addPermission(Permission $permission): void
    {
        if (!in_array($permission, $this->permissions, true)) {
            $this->permissions[] = $permission;
        }
    }

    public function hasPermissionByName(string $permissionName): bool
    {
        if ($permissionName !== 'admin.boss') {
            $isBoss = collect($this->permissions)->contains(fn(Permission $p) => $p->name === 'admin.boss');
            if ($isBoss) {
                return true;
            }
        }

        return collect($this->permissions)->contains(function (Permission $permission) use ($permissionName) {
            return $permission->name === $permissionName;
        });
    }

    public function hasPermission(Permission $permission): bool
    {
        return in_array($permission, $this->permissions, true);
    }

    public function removePermission(Permission $permission): void
    {
        $this->permissions = array_filter(
            $this->permissions,
            fn($p) => $p !== $permission
        );
    }

    public function updateLastUsed(int $throttleSeconds = self::LAST_USED_TOUCH_TTL): void
    {
        if ($throttleSeconds > 0 && $this->recentlyTouched($throttleSeconds)) {
            return;
        }

        $now = new DateTimeImmutable();
        $this->lastUsedAt = $now;
        $this->saveOrFail();
        $this->rememberTouch($now->getTimestamp(), $throttleSeconds);
    }

    public static function hashPlainKey(string $plainKey): string
    {
        return self::HASH_PREFIX . hash('sha256', $plainKey);
    }

    public static function findByPlainKey(string $plainKey, bool $migrateLegacy = false): ?self
    {
        if ($plainKey === '') {
            return null;
        }

        $apiKey = self::findOne(['key' => self::hashPlainKey($plainKey)]);
        if ($apiKey instanceof self) {
            return $apiKey;
        }

        $legacyHash = hash('sha256', $plainKey);
        $apiKey = self::findOne(['key' => $legacyHash]);
        if ($apiKey instanceof self) {
            if ($migrateLegacy) {
                $apiKey->key = self::hashPlainKey($plainKey);
                $apiKey->saveOrFail();
            }

            return $apiKey;
        }

        if (self::looksLikeStoredHash($plainKey)) {
            return null;
        }

        $apiKey = self::findOne(['key' => $plainKey]);
        if ($apiKey instanceof self && $migrateLegacy) {
            $apiKey->key = self::hashPlainKey($plainKey);
            $apiKey->saveOrFail();
        }

        return $apiKey instanceof self ? $apiKey : null;
    }

    public static function looksLikeStoredHash(string $value): bool
    {
        return str_starts_with($value, self::HASH_PREFIX) || preg_match('/^[a-f0-9]{64}$/i', $value) === 1;
    }

    private function recentlyTouched(int $throttleSeconds): bool
    {
        $now = time();

        if ($this->lastUsedAt && $now - $this->lastUsedAt->getTimestamp() < $throttleSeconds) {
            return true;
        }

        $key = $this->touchCacheKey();
        $lastTouched = self::$lastUsedTouches[$key] ?? 0;
        if ($lastTouched > 0 && $now - $lastTouched < $throttleSeconds) {
            return true;
        }

        if (!function_exists('cache')) {
            return false;
        }

        try {
            $cached = (int) cache()->get($key, 0);

            return $cached > 0 && $now - $cached < $throttleSeconds;
        } catch (Throwable) {
            return false;
        }
    }

    private function rememberTouch(int $timestamp, int $ttl): void
    {
        $key = $this->touchCacheKey();
        self::$lastUsedTouches[$key] = $timestamp;

        if (!function_exists('cache')) {
            return;
        }

        try {
            cache()->set($key, $timestamp, max(1, $ttl));
        } catch (Throwable) {
        }
    }

    private function touchCacheKey(): string
    {
        return 'api_key.last_used_touch.' . ( $this->id ?? md5($this->key) );
    }
}
