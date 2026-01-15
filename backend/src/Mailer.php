<?php
declare(strict_types=1);

namespace App\Utils;

final class Mailer
{
    public static function send(string $to, string $subject, string $message): bool
    {
        $logDir = __DIR__ . '/../logs';
        if (!is_dir($logDir)) {
            mkdir($logDir, 0777, true);
        }

        $log = sprintf(
            "[MAIL SIMULÉ]\nDate: %s\nÀ: %s\nSujet: %s\nMessage:\n%s\n----------------------\n\n",
            date('Y-m-d H:i:s'),
            $to,
            $subject,
            $message
        );

        file_put_contents($logDir . '/mails.log', $log, FILE_APPEND);

        return true;
    }
}
