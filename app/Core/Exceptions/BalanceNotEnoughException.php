<?php

namespace Flute\Core\Exceptions;

use Exception;

class BalanceNotEnoughException extends Exception
{
    protected float $need = 0.0;

    public function setNeededSum(float $amount): BalanceNotEnoughException
    {
        $this->need = max(0.0, $amount);

        return $this;
    }

    public function getNeededSum(): float
    {
        return $this->need;
    }
}
