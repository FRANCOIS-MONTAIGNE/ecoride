<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../src/DB.php';
require_once __DIR__ . '/../../../src/Auth.php';

use App\DB\DB;
use App\Security\Auth;

Auth::cors(true);
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

  // Vérification des droits d'accès
  Auth::requireStaff($pdo);

  $sql = "SELECT id, full_name, email, role, credits_enregistres, credits_calcules, ecart
          FROM v_user_credit_balance
          ORDER BY id";
  $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

  echo json_encode(['ok' => true, 'items' => $rows], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
