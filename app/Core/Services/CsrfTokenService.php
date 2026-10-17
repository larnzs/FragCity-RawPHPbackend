<?php

namespace Flute\Core\Services;

use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

class CsrfTokenService
{
    private CsrfTokenManagerInterface $csrfTokenManager;

    private string $defaultTokenId = 'flute_csrf';

    private const STATELESS_PREFIX = 'stateless:';

    private const STATELESS_TTL = 7200;

    public function __construct(CsrfTokenManagerInterface $csrfTokenManager)
    {
        $this->csrfTokenManager = $csrfTokenManager;
    }

    /**
     * Генерирует или получает существующий CSRF-токен.
     */
    public function getToken(?string $tokenId = null): string
    {
        if ($this->canUseStatelessToken()) {
            return $this->makeStatelessToken();
        }

        return $this->csrfTokenManager->getToken($tokenId ?: $this->defaultTokenId)->getValue();
    }

    /**
     * Refreshes the CSRF token by removing the old one.
     */
    public function refreshToken(?string $tokenId = null): void
    {
        $this->csrfTokenManager->removeToken($tokenId ?: $this->defaultTokenId);
    }

    /**
     * Проверяет валидность переданного CSRF-токена.
     */
    public function validateToken(string $token, bool $allowStateless = true): bool
    {
        if ($allowStateless && str_starts_with($token, self::STATELESS_PREFIX)) {
            return $this->validateStatelessToken($token);
        }

        $csrfToken = new CsrfToken($this->defaultTokenId, $token);

        return $this->csrfTokenManager->isTokenValid($csrfToken);
    }

    private function canUseStatelessToken(): bool
    {
        try {
            $request = request();

            return !$request->hasAuthenticationCookie() && session_status() !== PHP_SESSION_ACTIVE;
        } catch (\Throwable) {
            return false;
        }
    }

    private function makeStatelessToken(): string
    {
        $expires = time() + self::STATELESS_TTL;
        $nonce = bin2hex(random_bytes(12));
        $mac = hash_hmac('sha256', $expires . '|' . $nonce, $this->getStatelessSecret());

        return self::STATELESS_PREFIX . $expires . ':' . $nonce . ':' . $mac;
    }

    private function validateStatelessToken(string $token): bool
    {
        $payload = substr($token, strlen(self::STATELESS_PREFIX));
        $parts = explode(':', $payload);

        if (count($parts) !== 3) {
            return false;
        }

        [$expires, $nonce, $mac] = $parts;
        if (!ctype_digit($expires) || (int) $expires < time()) {
            return false;
        }

        if (!preg_match('/^[a-f0-9]{24}$/', $nonce) || !preg_match('/^[a-f0-9]{64}$/', $mac)) {
            return false;
        }

        $expected = hash_hmac('sha256', $expires . '|' . $nonce, $this->getStatelessSecret());

        return hash_equals($expected, $mac);
    }

    private function getStatelessSecret(): string
    {
        $appKey = (string) config('app.key');

        return $appKey !== '' ? $appKey : hash('sha256', BASE_PATH);
    }
}
