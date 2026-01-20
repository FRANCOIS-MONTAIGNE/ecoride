<?php
declare(strict_types=1);

namespace App\Core;

final class Response
{
    public static function json(array $payload, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE);
        exit;
    }

    public static function ok(array $payload): void
    {
        self::json($payload, 200);
    }

    public static function created(array $payload): void
    {
        self::json($payload, 201);
    }

    public static function error(string $message, int $status = 400, array $extra = []): void
    {
        self::json(array_merge([
            'ok' => false,
            'error' => $message
        ], $extra), $status);
    }
}
