<?php
declare(strict_types=1);

namespace App\Service;

use App\Repository\UserRepository;
use App\Security\Auth;

final class AuthService
{
    public function __construct(private UserRepository $users) {}

    /**
     * @return array{access_token:string,user:array}
     */
    public function login(string $email, string $password): array
    {
        $u = $this->users->findForLoginByEmail($email);

        if (!$u || empty($u['password_hash']) || !password_verify($password, (string)$u['password_hash'])) {
            // Ne pas révéler si l'email existe
            throw new \RuntimeException('Identifiants invalides', 401);
        }

        $token = Auth::issueAccessToken((int)$u['id'], (string)$u['email']);

        return [
            'access_token' => $token,
            'user' => [
                'id' => (int)$u['id'],
                'full_name' => (string)($u['full_name'] ?? ''),
                'email' => (string)$u['email'],
                'role' => (string)($u['role'] ?? ''),
                'rating' => $u['rating'] ?? null,
            ],
        ];
    }
}
