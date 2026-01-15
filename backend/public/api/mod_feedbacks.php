<?php
declare(strict_types=1);

require_once __DIR__ . '/../../src/DB.php';
require_once __DIR__ . '/../../src/Auth.php';

use App\DB\DB;
use App\Security\Auth;

Auth::cors(true);
header('Content-Type: application/json; charset=utf-8');

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// Preflight
if ($method === 'OPTIONS') {
  http_response_code(204);
  exit;
}

// Autoriser GET  et ou POST 
if ($method !== 'GET' && $method !== 'POST') {
  http_response_code(405);
  echo json_encode(['ok' => false, 'error' => 'Méthode non autorisée']);
  exit;
}

try {
  $pdo = DB::pdo();

  // ✅ Autoriser admin + employee (modération des feedbacks)
  $user = Auth::requireLogin($pdo, ['admin', 'employee']);

  // Récupérer le statut à filtrer (GET ou POST) 
  $status = $_GET['status'] ?? null;

  if ($method === 'POST' && !$status) {
    $in = json_decode(file_get_contents('php://input'), true);
    if (is_array($in) && isset($in['status'])) {
      $status = (string)$in['status'];
    }
  }

  $status = $status ?: 'pending';
  $allowed = ['pending', 'ok', 'issue'];
  if (!in_array($status, $allowed, true)) $status = 'pending';

  $sql = "
    SELECT
      f.id,
      f.trip_id,
      f.user_id,
      u.full_name AS user_name,
      u.email AS user_email,
      f.status,
      f.comment,
      f.created_at,
      f.moderated_by,
      m.full_name AS moderated_by_name,
      f.moderated_at
    FROM trip_feedbacks f
    JOIN users u ON u.id = f.user_id
    LEFT JOIN users m ON m.id = f.moderated_by
    WHERE f.status = :status
    ORDER BY f.created_at DESC
    LIMIT 200
  ";

  $stmt = $pdo->prepare($sql);
  $stmt->execute([':status' => $status]);
  $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

  echo json_encode(['ok' => true, 'feedbacks' => $rows], JSON_UNESCAPED_UNICODE);
  exit;

} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode([
    'ok' => false,
    'error' => 'Erreur serveur',
    // Pour débogage uniquement(commente z en production)
    'details' => $e->getMessage(),
  ], JSON_UNESCAPED_UNICODE);
  exit;
}
