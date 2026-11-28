<?php

declare(strict_types=1);

namespace App\Exceptions\Wallet;

use DomainException;

class DailyLimitExceededException extends DomainException
{
    public function __construct(string $message = 'Belanja melebihi batas jajan harian siswa.')
    {
        parent::__construct($message);
    }
}
