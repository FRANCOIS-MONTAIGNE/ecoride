<?php
declare(strict_types=1);

ini_set('display_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . '/../../src/DB.php';
require_once __DIR__ . '/../../src/Auth.php';

use App\DB\DB;
use App\Security\Auth;

Auth::cors(true);
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode(['error' => 'Méthode non autorisée']);
  exit;
}

$in = json_decode(file_get_contents('php://input'), true);
if (!is_array($in)) $in = [];

$name = trim((string)($in['name'] ?? ''));
$email = trim((string)($in['email'] ?? ''));
$message = trim((string)($in['message'] ?? ''));

if ($name === '' || $email === '' || $message === '') {
  http_response_code(400);
  echo json_encode(['error' => 'Champs manquants']);
  exit;
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
  http_response_code(400);
  echo json_encode(['error' => 'Email invalide']);
  exit;
}
if (mb_strlen($message) < 5) {
  http_response_code(400);
  echo json_encode(['error' => 'Message trop court']);
  exit;
}

try {
  $pdo = DB::pdo();

  $stmt = $pdo->prepare("
    INSERT INTO contact_messages (name, email, message)
    VALUES (:name, :email, :message)
  ");
  $stmt->execute([
    ':name' => $name,
    ':email' => $email,
    ':message' => $message,
  ]);

  echo json_encode(['message' => 'Message envoyé ✅ Merci !']);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['error' => 'Erreur serveur']);
}
