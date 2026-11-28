<?php

declare(strict_types=1);

namespace App\Exceptions\Wallet;

use DomainException;

class InsufficientBalanceException extends DomainException
{
    public function __construct(string $message = 'Saldo dompet siswa tidak mencukupi.')
    {
        parent::__construct($message);
    }
}
