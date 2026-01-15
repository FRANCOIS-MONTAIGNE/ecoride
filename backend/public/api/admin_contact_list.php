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

try {
  $pdo = DB::pdo();

  // ✅ admin only endpoint
  Auth::requireAdmin($pdo);

  $stmt = $pdo->query("
    SELECT id, name, email, message, created_at, is_read
    FROM contact_messages
    ORDER BY created_at DESC
    LIMIT 200
  ");
  $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

  echo json_encode(['items' => $rows], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
  // ✅ IMPORTANT : Do not expose sensitive error details in production
  http_response_code(500);
  echo json_encode([
    'ok' => false,
    'error' => 'Erreur serveur',
    'details' => $e->getMessage()
  ]);
}
