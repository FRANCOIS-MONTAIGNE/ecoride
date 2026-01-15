<?php
// backend/public/api/admin_create_employee.php
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

try {
    $pdo = DB::getConnection();  
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'ok'     => false,
        'error'  => 'DB error',
        'details'=> $e->getMessage(),
    ]);
    exit;
}

//  Exige un utilisateur connecté ET admin
$admin = Auth::requireLogin($pdo, ['admin']); 

// lecture body JSON
$in = json_decode(file_get_contents('php://input'), true) ?? [];

$email    = trim(strtolower($in['email']    ?? ''));
$password = trim((string)($in['password'] ?? ''));
$name     = trim($in['name']     ?? '');

if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 6) {
    http_response_code(422);
    echo json_encode([
        'ok'    => false,
        'error' => 'Email ou mot de passe invalide (mot de passe ≥ 6 caractères)',
    ]);
    exit;
}

try {
    // vérif email unique
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = :email LIMIT 1");
    $stmt->execute([':email' => $email]);
    if ($stmt->fetch()) {
        http_response_code(409);
        echo json_encode(['ok' => false, 'error' => 'Email déjà utilisé']);
        exit;
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);

    // created_at a déjà CURRENT_TIMESTAMP par défaut
    $stmt = $pdo->prepare("
        INSERT INTO users (email, password_hash, full_name, role)
        VALUES (:email, :pwd, :full_name, 'employee')
    ");

    $stmt->execute([
        ':email'     => $email,
        ':pwd'       => $hash,
        ':full_name' => $name !== '' ? $name : $email,
    ]);

    echo json_encode([
  'ok' => true,
  'message' => 'Compte employé créé',
  'employee_id' => $employeeId,
]);

} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'ok'     => false,
        'error'  => 'DB error',
        'details'=> $e->getMessage(),
    ]);
}
