<?php

declare(strict_types=1);

namespace App\Exceptions\Cbt;

use RuntimeException;

class DeviceTakeoverException extends RuntimeException
{
    public function __construct(string $message = 'Sesi ujian diambil alih oleh perangkat lain.')
    {
        parent::__construct($message);
    }
}
