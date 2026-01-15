<?php
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
  echo json_encode(['ok' => false, 'success' => false, 'message' => 'Méthode non autorisée']);
  exit;
}

$in = json_decode(file_get_contents('php://input'), true) ?? [];

// champs frontend
$fullName = trim((string)($in['name'] ?? $in['full_name'] ?? ''));
$email    = trim(strtolower((string)($in['email'] ?? '')));
$pass     = (string)($in['password'] ?? '');

if ($fullName === '' || $email === '' || $pass === '') {
  http_response_code(400);
  echo json_encode(['ok' => false, 'success' => false, 'message' => 'Tous les champs sont requis.']);
  exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
  http_response_code(422);
  echo json_encode(['ok' => false, 'success' => false, 'message' => 'Email invalide.']);
  exit;
}

try {
  $pdo = DB::pdo(); // PDO instance

  // email déjà utilisé
  $chk = $pdo->prepare('SELECT id FROM users WHERE email = :e LIMIT 1');
  $chk->execute([':e' => $email]);
  if ($chk->fetch()) {
    http_response_code(409);
    echo json_encode(['ok' => false, 'success' => false, 'message' => 'Email déjà utilisé.']);
    exit;
  }

  $hash = password_hash($pass, PASSWORD_DEFAULT);

  // ✅ 20 crédits à la création
  $ins = $pdo->prepare('
    INSERT INTO users (full_name, email, password_hash, role, credits, created_at)
    VALUES (:n, :e, :h, :r, :c, NOW())
  ');
  $ins->execute([
    ':n' => $fullName,
    ':e' => $email,
    ':h' => $hash,
    ':r' => 'user',
    ':c' => 20,
  ]);

  echo json_encode([
    'ok' => true,
    'success' => true,
    'message' => 'Compte créé avec succès !'
  ]);

} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode([
    'ok' => false,
    'success' => false,
    'message' => 'Erreur serveur (DB)',
    'details' => $e->getMessage(),
  ]);
}
