<?php
// backend/public/api/login.php
declare(strict_types=1);

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . '/../../src/DB.php';
require_once __DIR__ . '/../../src/Auth.php';

require_once __DIR__ . '/../../src/Core/Response.php';
require_once __DIR__ . '/../../src/Repository/UserRepository.php';
require_once __DIR__ . '/../../src/Service/AuthService.php';

use App\DB\DB;
use App\Security\Auth;
use App\Core\Response;
use App\Repository\UserRepository;
use App\Service\AuthService;

Auth::cors(true);

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method === 'OPTIONS') {
    http_response_code(204);
    exit;
}
if ($method !== 'POST') {
    Response::error('Méthode non autorisée', 405);
}

$in = json_decode(file_get_contents('php://input'), true);
if (!is_array($in)) $in = [];

$email = trim(strtolower((string)($in['email'] ?? '')));
$pass  = (string)($in['password'] ?? '');

if ($email === '' || $pass === '') {
    Response::error('Email et mot de passe requis', 400);
}

try {
    $pdo = DB::pdo();

    $users = new UserRepository($pdo);
    $authService = new AuthService($users);

    $result = $authService->login($email, $pass);

    Response::ok([
        'ok' => true,
        'message' => 'Connecté.',
        'access_token' => $result['access_token'],
        'user' => $result['user'],
    ]);

} catch (Throwable $e) {
    $code = (int)$e->getCode();

    if ($code === 401) {
        Response::error('Identifiants invalides', 401);
    }

    Response::error('Erreur serveur', 500, ['details' => $e->getMessage()]);
}
