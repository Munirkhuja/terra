<?php

namespace App\Enums\Traits;

trait HasEnumHelpers
{
    public static function values(): array
    {
        return array_map(fn(self $e) => $e->value, self::cases());
    }

    public static function labels(): array
    {
        $result = [];
        foreach (self::cases() as $case) {
            $result[$case->value] = method_exists($case, 'label')
                ? $case->label()
                : $case->value;
        }
        return $result;
    }
}
