<?php

declare(strict_types=1);

namespace App\Exceptions\Wallet;

use DomainException;

class FrozenWalletException extends DomainException
{
    public function __construct(string $message = 'Dompet siswa sedang dibekukan sementara.')
    {
        parent::__construct($message);
    }
}
