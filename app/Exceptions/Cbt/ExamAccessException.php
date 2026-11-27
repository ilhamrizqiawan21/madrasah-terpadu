<?php

declare(strict_types=1);

namespace App\Exceptions\Cbt;

use RuntimeException;

class ExamAccessException extends RuntimeException
{
    public function __construct(
        public readonly string $field,
        string $message,
    ) {
        parent::__construct($message);
    }
}
