<?php

namespace App\Enums;

use App\Enums\Traits\HasEnumHelpers;

enum SourceEnum: string
{
    use HasEnumHelpers;

    case WEB = 'web';
    case MOBILE = 'mobile';
    case API = 'api';
    case ETC = 'etc';

    public function label(): string
    {
        return match ($this) {
            self::WEB => 'Веб',
            self::MOBILE => 'Мобильное приложение',
            self::API => 'API',
            self::ETC => 'Прочее',
        };
    }

}
