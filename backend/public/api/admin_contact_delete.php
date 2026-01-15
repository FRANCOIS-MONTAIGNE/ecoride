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

$id = (int)($in['id'] ?? 0);
if ($id <= 0) {
  http_response_code(400);
  echo json_encode(['error' => 'ID invalide']);
  exit;
}

try {
  $pdo = DB::pdo();

  // admin only
  Auth::requireLogin($pdo, ['admin']); 

  $stmt = $pdo->prepare("DELETE FROM contact_messages WHERE id = :id");
  $stmt->execute([':id' => $id]);

  echo json_encode(['message' => 'Supprimé ✅']);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['error' => 'Erreur serveur', 'details' => $e->getMessage()]);
}
