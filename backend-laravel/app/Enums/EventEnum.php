<?php

namespace App\Enums;

use App\Enums\Traits\HasEnumHelpers;

enum EventEnum: string
{
    use HasEnumHelpers;
    case GET_COORDINATE = 'get_coordinate';

    public function label(): string
    {
        return match ($this) {
            self::GET_COORDINATE => 'Получить координаты',
        };
    }
}
