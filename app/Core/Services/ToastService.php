<?php

namespace Flute\Core\Services;

use Flute\Core\Toast\Toast;
use Flute\Core\Toast\ToastBuilder;

class ToastService
{
    private const TOASTS_KEY = '_flute_toasts';

    private SessionService $session;

    public function __construct(SessionService $session)
    {
        $this->session = $session;
    }

    /**
     * Возвращает экземпляр ToastBuilder для создания тоста.
     */
    public function toast(): ToastBuilder
    {
        return new ToastBuilder($this);
    }

    /**
     * Добавляет тост в сессию.
     */
    public function addToast(Toast $toast): void
    {
        $toasts = $this->session->get(self::TOASTS_KEY, []);
        $toasts[] = $toast;
        $this->session->set(self::TOASTS_KEY, $toasts);
    }

    /**
     * Получает и очищает все тосты из сессии.
     *
     * @return Toast[]
     */
    public function getToasts(): array
    {
        if (!$this->shouldReadSession()) {
            return [];
        }

        $toasts = $this->session->get(self::TOASTS_KEY, []);
        $this->session->remove(self::TOASTS_KEY);

        return $toasts;
    }

    private function shouldReadSession(): bool
    {
        if ($this->session->isStarted() || session_status() === PHP_SESSION_ACTIVE) {
            return true;
        }

        try {
            return request()->hasSessionCookie();
        } catch (\Throwable) {
            return false;
        }
    }
}
