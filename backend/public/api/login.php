<?php
// backend/public/api/login.php
declare(strict_types=1);

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . '/../../src/DB.php';
require_once __DIR__ . '/../../src/Auth.php';

use App\DB\DB;
use App\Security\Auth;

Auth::cors(true);
header('Content-Type: application/json; charset=utf-8');

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($method !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Méthode non autorisée']);
    exit;
}

$in = json_decode(file_get_contents('php://input'), true);
if (!is_array($in)) $in = [];

$email = trim(strtolower((string)($in['email'] ?? '')));
$pass  = (string)($in['password'] ?? '');

if ($email === '' || $pass === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Email et mot de passe requis']);
    exit;
}

try {
    $pdo = DB::pdo();

    $stmt = $pdo->prepare("
        SELECT id, full_name, email, password_hash, role, rating
        FROM users
        WHERE email = :email
        LIMIT 1
    ");
    $stmt->execute([':email' => $email]);
    $u = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$u || empty($u['password_hash']) || !password_verify($pass, $u['password_hash'])) {
        http_response_code(401);
        echo json_encode(['ok' => false, 'error' => 'Identifiants invalides']);
        exit;
    }

    $token = Auth::issueAccessToken((int)$u['id'], (string)$u['email']);

    echo json_encode([
        'ok' => true,
        'message' => 'Connecté.',
        'access_token' => $token,
        'user' => [
            'id' => (int)$u['id'],
            'full_name' => (string)($u['full_name'] ?? ''),
            'email' => (string)$u['email'],
            'role' => (string)($u['role'] ?? ''),
            'rating' => $u['rating'] ?? null,
        ],
    ]);
    exit;

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => 'Erreur serveur',
        'details' => $e->getMessage(),
    ]);
    exit;
}
