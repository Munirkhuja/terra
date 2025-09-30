<?php

namespace App\Traits;

use Illuminate\Support\Facades\Log;

trait JsonArrayTrait
{
    public function toArrayWithTrait($data)
    {
        // Если уже массив, вернуть как есть
        if (is_array($data)) {
            return $data;
        }

        // Если строка JSON, декодировать
        if (is_string($data)) {
            $decoded = json_decode($data, true);

            // Проверка на успешное декодирование
            if (json_last_error() === JSON_ERROR_NONE) {
                return $decoded;
            }
            Log::error("json_decode Error: " . json_last_error_msg(), [
                'code' => json_last_error(),
                'data' => $data,
            ]);
        }

        // Если не массив и не корректный JSON, вернуть пустой массив
        return [];
    }

    public function toJsonWithTrait($data): bool|string
    {
        if (is_string($data)) {
            json_decode($data, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $data;
            }
            Log::error("json_decode Error: " . json_last_error_msg(), [
                'code' => json_last_error(),
                'data' => $data,
            ]);
        }
        if (is_array($data)) {
            $encoded = json_encode($data, JSON_UNESCAPED_UNICODE | true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $encoded;
            }
            Log::error("json_encode Error: " . json_last_error_msg(), [
                'code' => json_last_error(),
                'data' => $data,
            ]);
        }

        return json_encode([]);
    }
}
