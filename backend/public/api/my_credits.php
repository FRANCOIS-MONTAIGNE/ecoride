<?php
declare(strict_types=1);

require_once __DIR__ . '/../../src/DB.php';
require_once __DIR__ . '/../../src/Auth.php';

use App\DB\DB;
use App\Security\Auth;

Auth::cors();
header('Content-Type: application/json; charset=utf-8');

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method === 'OPTIONS') { http_response_code(204); exit; }
if ($method !== 'GET') {
  http_response_code(405);
  echo json_encode(['ok' => false, 'error' => 'Méthode non autorisée']);
  exit;
}

try {
  $pdo = DB::pdo();
  $claims = Auth::requireAuth();
  $userId = (int)($claims['sub'] ?? 0);

  $stmt = $pdo->prepare("SELECT credits FROM users WHERE id = :id");
  $stmt->execute([':id' => $userId]);
  $credits = $stmt->fetchColumn();

  echo json_encode(['ok' => true, 'credits' => (float)$credits]);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'Erreur serveur', 'details' => $e->getMessage()]);
}
