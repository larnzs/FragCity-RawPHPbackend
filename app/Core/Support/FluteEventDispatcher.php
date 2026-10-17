<?php

namespace Flute\Core\Support;

use Closure;
use Laravel\SerializableClosure\SerializableClosure;
use ReflectionFunction;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Throwable;

class FluteEventDispatcher extends EventDispatcher
{
    public $deferredListeners = [];

    private $deferredListenersKey = 'flute.deferred_listeners';

    private bool $isDirty = false;

    private static array $closureIdCache = [];

    private array $registeredInDispatcher = [];

    public function __construct()
    {
        parent::__construct();
        $this->initializeDeferredListeners();
    }

    public function addDeferredSubscriber($subscriber)
    {
        $events = $subscriber->getSubscribedEvents();

        foreach ($events as $eventName => $listener) {
            $this->addDeferredListener($eventName, [$subscriber, $listener]);
        }
    }

    public function addDeferredListener($eventName, $listener, $priority = 0)
    {
        $listenerId = $this->getListenerId($listener);

        if (!isset($this->deferredListeners[$eventName])) {
            $this->deferredListeners[$eventName] = [];
        }

        if (!isset($this->deferredListeners[$eventName][$listenerId])) {
            $this->deferredListeners[$eventName][$listenerId] = [
                'listener' => $listener,
                'priority' => $priority,
                'id' => $listenerId,
            ];
            $this->isDirty = true;
        }

        $dispatcherKey = $eventName . '::' . $listenerId;

        if (!isset($this->registeredInDispatcher[$dispatcherKey]) && is_callable($listener)) {
            $this->addListener($eventName, $listener, $priority);
            $this->registeredInDispatcher[$dispatcherKey] = true;
        }
    }

    public function removeDeferredListener($eventName, $listener)
    {
        $listenerId = $this->getListenerId($listener);

        if (isset($this->deferredListeners[$eventName][$listenerId])) {
            unset($this->deferredListeners[$eventName][$listenerId]);

            if (empty($this->deferredListeners[$eventName])) {
                unset($this->deferredListeners[$eventName]);
            }

            $this->isDirty = true;
        }

        $dispatcherKey = $eventName . '::' . $listenerId;
        unset($this->registeredInDispatcher[$dispatcherKey]);

        $this->removeListener($eventName, $listener);
    }

    public function saveDeferredListenersToCache()
    {
        if (!$this->isDirty) {
            return;
        }

        try {
            if (function_exists('cache')) {
                cache()->set($this->deferredListenersKey, $this->deferredListeners, 3600);
            }
            $this->isDirty = false;
        } catch (Throwable) {
            $this->isDirty = true;
        }
    }

    private function initializeDeferredListeners()
    {
        try {
            $deferredListeners = function_exists('cache') ? cache()->get($this->deferredListenersKey, []) : [];
        } catch (Throwable) {
            $deferredListeners = [];
        }

        if (!is_array($deferredListeners)) {
            $deferredListeners = [];
        }

        foreach ($deferredListeners as $eventName => $listeners) {
            foreach ($listeners as $key => $listenerData) {
                $listener = $listenerData['listener'];
                if ($listener instanceof SerializableClosure) {
                    $listener = $listener->getClosure();
                }

                $listenerId = $listenerData['id'] ?? $this->getListenerId($listener);
                $dispatcherKey = $eventName . '::' . $listenerId;

                if (is_callable($listener)) {
                    $this->addListener($eventName, $listener, $listenerData['priority']);
                    $this->registeredInDispatcher[$dispatcherKey] = true;
                }

                if (isset($listenerData['id']) && is_object($listener)) {
                    self::$closureIdCache[spl_object_id($listener)] = $listenerData['id'];
                }
            }
        }

        $this->deferredListeners = $deferredListeners;
    }

    private function getListenerId($listener)
    {
        if ($listener instanceof Closure) {
            return $this->getClosureId($listener);
        }

        if ($listener instanceof SerializableClosure) {
            return $this->getClosureId($listener->getClosure());
        }

        if (is_array($listener)) {
            if (is_object($listener[0])) {
                return get_class($listener[0]) . '::' . $listener[1];
            }

            return $listener[0] . '::' . $listener[1];
        }

        if (is_object($listener)) {
            return $listener::class;
        }

        return $listener;
    }

    private function getClosureId($closure)
    {
        $objectId = spl_object_id($closure);

        if (isset(self::$closureIdCache[$objectId])) {
            return self::$closureIdCache[$objectId];
        }

        $ref = new ReflectionFunction($closure);
        $id = md5($ref->getFileName() . ':' . $ref->getStartLine() . ':' . $ref->getEndLine());

        self::$closureIdCache[$objectId] = $id;

        return $id;
    }
}
