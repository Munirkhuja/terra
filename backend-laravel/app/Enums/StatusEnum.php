<?php

namespace App\Enums;

use App\Enums\Traits\HasEnumHelpers;

enum StatusEnum: string
{
    use HasEnumHelpers;

    case PROCESSING = 'processing';
    case SUCCESS = 'success';
    case FAILED = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::PROCESSING => 'В обработке',
            self::SUCCESS => 'Успешно',
            self::FAILED => 'Ошибка',
        };
    }
    public function toString(): ?string
    {
        return $this->label();
    }
    public function getColor(): ?string
    {
        return match ($this) {
            self::PROCESSING => 'info',
            self::SUCCESS => 'gray',
            self::FAILED => 'success',
        };
    }
}
