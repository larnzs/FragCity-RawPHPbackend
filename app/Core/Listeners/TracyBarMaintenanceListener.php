<?php

namespace Flute\Core\Listeners;

use Tracy\Debugger;

class TracyBarMaintenanceListener
{
    public function handle(): void
    {
        $shouldHide = false;
        $debugIps = config('app.debug_ips') ?: [];
        $globalDebug = (bool) config('app.debug');
        $isDevelopment = is_development();

        if (is_installed() && config('app.maintenance_mode')) {
            if (!empty($debugIps)) {
                $shouldHide = !in_array(request()->ip(), $debugIps);
            } else {
                $shouldHide = !$globalDebug && !$isDevelopment;
            }
        } elseif (!empty($debugIps) && !$globalDebug && !$isDevelopment) {
            $shouldHide = !in_array(request()->ip(), $debugIps);
        }

        if ($shouldHide) {
            Debugger::$showBar = false;
        }
    }
}
