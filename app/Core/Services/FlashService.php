<?php

namespace Flute\Core\Services;

use Flute\Core\Toast\ToastBuilder;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;

class FlashService implements FlashBagInterface
{
    public const SUCCESS_TYPE = 'success', ERROR_TYPE = 'error', WARNING_TYPE = 'warning', INFO_TYPE = 'info';

    private SessionService $session;

    private ?FlashBagInterface $flashBag = null;

    private ToastService $toastService;

    /**
     * FlashService constructor.
     *
     * @param SessionService $session The session service
     */
    public function __construct(SessionService $session, ToastService $toastService)
    {
        $this->session = $session;
        $this->toastService = $toastService;
    }

    public function toast(): ToastBuilder
    {
        return $this->toastService->toast();
    }

    /**
     * Adds a flash message for the given type.
     *
     * @param string $type    Message type
     * @param mixed  $message The flash message
     */
    public function add(string $type, $message)
    {
        $this->getFlashBagForWrite()->add($type, $message);

        return $this;
    }

    /**
     * Adds a success flash message.
     *
     * @param mixed  $message The flash message
     */
    public function success($message)
    {
        $this->getFlashBagForWrite()->add(self::SUCCESS_TYPE, $message);

        return $this;
    }

    /**
     * Adds a error flash message.
     *
     * @param mixed  $message The flash message
     */
    public function error($message)
    {
        $this->getFlashBagForWrite()->add(self::ERROR_TYPE, $message);

        return $this;
    }

    /**
     * Adds a warning flash message.
     *
     * @param mixed  $message The flash message
     */
    public function warning($message)
    {
        $this->getFlashBagForWrite()->add(self::WARNING_TYPE, $message);

        return $this;
    }

    /**
     * Adds a info flash message.
     *
     * @param mixed  $message The flash message
     */
    public function info($message)
    {
        $this->getFlashBagForWrite()->add(self::INFO_TYPE, $message);

        return $this;
    }

    /**
     * Registers one or more messages for a given type.
     *
     * @param string       $type     Message type
     * @param string|array $messages The flash messages
     */
    public function set(string $type, $messages)
    {
        $this->getFlashBagForWrite()->set($type, $messages);

        return $this;
    }

    /**
     * Gets flash messages for a given type.
     *
     * @param string $type    Message type
     * @param array  $default Default value if $type does not exist
     *
     * @return array The flash messages
     */
    public function peek(string $type, array $default = []): array
    {
        return $this->getFlashBagForRead()?->peek($type, $default) ?? $default;
    }

    /**
     * Gets all flash messages.
     *
     * @return array All flash messages
     */
    public function peekAll(): array
    {
        return $this->getFlashBagForRead()?->peekAll() ?? [];
    }

    /**
     * Gets and clears flash messages for a given type.
     *
     * @param string $type    Message type
     * @param array  $default Default value if $type does not exist
     *
     * @return array The flash messages
     */
    public function get(string $type, array $default = []): array
    {
        return $this->getFlashBagForRead()?->get($type, $default) ?? $default;
    }

    /**
     * Gets and clears all flash messages.
     *
     * @return array All flash messages
     */
    public function all(): array
    {
        return $this->getFlashBagForRead()?->all() ?? [];
    }

    /**
     * Sets all flash messages.
     *
     * @param array $messages The flash messages
     */
    public function setAll(array $messages)
    {
        $this->getFlashBagForWrite()->setAll($messages);
    }

    /**
     * Checks if flash messages exist for a given type.
     *
     * @param string $type Message type
     *
     * @return bool True if flash messages exist, false otherwise
     */
    public function has(string $type): bool
    {
        return $this->getFlashBagForRead()?->has($type) ?? false;
    }

    /**
     * Returns a list of all defined flash message types.
     *
     * @return array A list of flash message types
     */
    public function keys(): array
    {
        return $this->getFlashBagForRead()?->keys() ?? [];
    }

    /**
     * Returns the name of the flash bag.
     *
     * @return string The flash bag name
     */
    public function getName(): string
    {
        return $this->getFlashBagForRead()?->getName() ?? 'flashes';
    }

    /**
     * Initializes the flash bag.
     *
     * @param array $array The flash bag array
     */
    public function initialize(array &$array)
    {
        $this->getFlashBagForWrite()->initialize($array);
    }

    /**
     * Returns the storage key of the flash bag.
     *
     * @return string The flash bag storage key
     */
    public function getStorageKey(): string
    {
        return $this->getFlashBagForRead()?->getStorageKey() ?? '_sf2_flashes';
    }

    /**
     * Clears all flash messages.
     */
    public function clear(): mixed
    {
        return $this->getFlashBagForRead()?->clear() ?? [];
    }

    private function getFlashBagForRead(): ?FlashBagInterface
    {
        if (!$this->shouldReadSession()) {
            return null;
        }

        return $this->flashBag ??= $this->session->getFlashBag();
    }

    private function getFlashBagForWrite(): FlashBagInterface
    {
        return $this->flashBag ??= $this->session->getFlashBag();
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
