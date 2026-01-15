<?php
// backend/src/Auth.php
declare(strict_types=1);

namespace App\Security;

final class Auth
{
    /** Récupère la config globale (jwt, cors, etc.) */
    private static function config(): array
    {
        static $cfg = null;
        if ($cfg === null) {
            // charger une seule fois le fichier de config PHP 
            $cfg = require __DIR__ . '/config.php';
        }
        return $cfg;
    }

    /** Gestion des en-têtes CORS */
    public static function cors(bool $withCredentials = false): void
    {
        $cfg     = self::config();
        $allowed = $cfg['cors_allowed'] ?? [];
        $origin  = $_SERVER['HTTP_ORIGIN'] ?? '';

        if (in_array($origin, $allowed, true)) {
            header("Access-Control-Allow-Origin: $origin");
        }

        header("Access-Control-Allow-Methods: GET,POST,PUT,PATCH,DELETE,OPTIONS");
        header("Access-Control-Allow-Headers: Content-Type, Authorization");
        if ($withCredentials) {
            header("Access-Control-Allow-Credentials: true");
        }
    }

    /* ---------------- JWT utils ---------------- */

    private static function b64url_encode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private static function b64url_decode(string $data): string
    {
        $remainder = strlen($data) % 4;
        if ($remainder) {
            $data .= str_repeat('=', 4 - $remainder);
        }
        return base64_decode(strtr($data, '-_', '+/')) ?: '';
    }

    /** Génère un token d'accès JWT */
    public static function issueAccessToken(int $userId, string $email): string
    {
        $cfg    = self::config();
        $secret = $cfg['jwt_secret'];
        $iss    = $cfg['jwt_iss'] ?? 'ecoride';
        $aud    = $cfg['jwt_aud'] ?? 'ecoride-front';
        $ttl    = $cfg['access_ttl'] ?? (15 * 60);

        $now = time();
        $payload = [
            'sub'   => $userId,
            'email' => $email,
            'iat'   => $now,
            'exp'   => $now + $ttl,
            'iss'   => $iss,
            'aud'   => $aud,
        ];

        $header = ['alg' => 'HS256', 'typ' => 'JWT'];
        $h = self::b64url_encode(json_encode($header,  JSON_UNESCAPED_UNICODE));
        $p = self::b64url_encode(json_encode($payload, JSON_UNESCAPED_UNICODE));
        $sig = hash_hmac('sha256', "$h.$p", $secret, true);
        $s = self::b64url_encode($sig);

        return "$h.$p.$s";
    }

/** Lit le header Authorization: Bearer ... (robuste Apache/XAMPP/Windows) */
private static function getAuthorizationHeader(): ?string
{
    // 0) REDIRECT_HTTP_AUTHORIZATION (XAMPP, etc.)
    if (!empty($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
        return $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
    }

    // 1) $_SERVER['HTTP_AUTHORIZATION'] (Apache, Nginx, etc.)
    if (!empty($_SERVER['HTTP_AUTHORIZATION'])) {
        return $_SERVER['HTTP_AUTHORIZATION'];
    }

    // 2) getallheaders()
    if (function_exists('getallheaders')) {
        $headers = getallheaders();
        if (is_array($headers)) {
            foreach ($headers as $k => $v) {
                if (strtolower($k) === 'authorization' && is_string($v) && $v !== '') {
                    return $v;
                }
            }
        }
    }

    // 3) apache_request_headers()
    if (function_exists('apache_request_headers')) {
        $headers = apache_request_headers();
        if (is_array($headers)) {
            $headers = array_change_key_case($headers, CASE_LOWER);
            if (!empty($headers['authorization'])) return $headers['authorization'];
        }
    }

    return null;
}



    /** Vérifie et retourne le payload du token */
    private static function verifyToken(string $token): array
    {
        $cfg    = self::config();
        $secret = $cfg['jwt_secret'];

        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            throw new \RuntimeException('Token JWT invalide');
        }

        [$h64, $p64, $s64] = $parts;
        $payloadJson = self::b64url_decode($p64);
        $payload = json_decode($payloadJson, true);
        if (!is_array($payload)) {
            throw new \RuntimeException('Payload JWT invalide');
        }

        $sig      = self::b64url_decode($s64);
        $expected = hash_hmac('sha256', "$h64.$p64", $secret, true);
        if (!hash_equals($expected, $sig)) {
            throw new \RuntimeException('Signature JWT invalide');
        }

        if (!empty($payload['exp']) && time() > (int) $payload['exp']) {
            throw new \RuntimeException('Token expiré');
        }

        return $payload;
    }

    /**
     * Exige un token d'accès valide.
     * - lit le header Authorization
     * - vérifie le token
     * - renvoie le payload (avec sub = user id)
     */
    public static function requireAuth(): array
    {
        $hdr = self::getAuthorizationHeader();
        if (!$hdr || stripos($hdr, 'bearer ') !== 0) {
            http_response_code(401);
            echo json_encode(['ok' => false, 'error' => 'Token manquant']);
            exit;
        }

        $token = trim(substr($hdr, 7));

        try {
            return self::verifyToken($token);
        } catch (\Throwable $e) {
            http_response_code(401);
            echo json_encode(['ok' => false, 'error' => 'Token invalide ou expiré']);
            exit;
        }
    }

    /** Cookie de refresh très simple (optionnel) */
    public static function setRefreshCookie(int $userId): void
    {
        $cfg = self::config();
        $ttl = $cfg['refresh_ttl'] ?? (14 * 24 * 3600);

        $token = bin2hex(random_bytes(16)); // token aléatoire simple
        setcookie(
            'refresh_token',
            $token,
            [
                'expires'  => time() + $ttl,
                'path'     => '/',
                'secure'   => $cfg['cookie_secure'] ?? false,
                'httponly' => true,
                'samesite' => $cfg['cookie_samesite'] ?? 'Lax',
            ]
        );
    }

        /**
     * Exige un utilisateur connecté.
     * - vérifie le JWT
     * - utilise la connexion PDO pour charger l'utilisateur
     * - vérifie le rôle si $allowedRoles est fourni
     *
     * @param \PDO        $pdo          connexion PDO
     * @param array|null  $allowedRoles ex: ['admin']
     * @return array user info
     */
    public static function requireLogin(\PDO $pdo, ?array $allowedRoles = null): array
    {
        // Vérification du token et récupération du user id
        $claims = self::requireAuth();
        $userId = (int)($claims['sub'] ?? 0);

        if ($userId <= 0) {
            http_response_code(401);
            echo json_encode(['ok' => false, 'error' => 'Utilisateur non authentifié']);
            exit;
        }

        try {
            $stmt = $pdo->prepare("
                SELECT id, full_name, email, rating, role
                FROM users
                WHERE id = :id
                LIMIT 1
            ");
            $stmt->execute([':id' => $userId]);
            $user = $stmt->fetch(\PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode([
                'ok'      => false,
                'message' => 'Erreur DB',
                'details' => $e->getMessage(),
            ]);
            exit;
        }

        if (!$user) {
            http_response_code(401);
            echo json_encode(['ok' => false, 'error' => 'Utilisateur introuvable']);
            exit;
        }

        // Vérification du rôle si nécessaire
        if ($allowedRoles) {
            $allowedRoles = array_map('strtolower', $allowedRoles);
            $userRole = strtolower((string)($user['role'] ?? ''));

            if (!$userRole || !in_array($userRole, $allowedRoles, true)) {
                http_response_code(403);
                echo json_encode(['ok' => false, 'error' => 'Accès réservé']);
                exit;
            }
        }

        return $user;
    }

public static function requireAdmin(\PDO $pdo): array
{
    return self::requireLogin($pdo, ['admin']);
}

public static function requireStaff(\PDO $pdo): array
{
    return self::requireLogin($pdo, ['admin', 'employee']);
}
}