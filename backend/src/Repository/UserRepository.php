<?php
declare(strict_types=1);

namespace App\Repository;

use PDO;

final class UserRepository
{
    public function __construct(private PDO $pdo) {}

    public function findForLoginByEmail(string $email): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT id, full_name, email, password_hash, role, rating
            FROM users
            WHERE email = :email
            LIMIT 1
        ");
        $stmt->execute([':email' => $email]);

        $u = $stmt->fetch(PDO::FETCH_ASSOC);
        return $u ?: null;
    }
}
