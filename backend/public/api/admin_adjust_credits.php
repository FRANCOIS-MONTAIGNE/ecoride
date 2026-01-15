<?php
declare(strict_types=1);

require_once __DIR__ . '/../../src/DB.php';
require_once __DIR__ . '/../../src/Auth.php';

use App\DB\DB;
use App\Security\Auth;

Auth::cors(true);
header('Content-Type: application/json; charset=utf-8');

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method === 'OPTIONS') { http_response_code(204); exit; }
if ($method !== 'POST') {
  http_response_code(405);
  echo json_encode(['ok'=>false,'error'=>'Méthode non autorisée']);
  exit;
}

try {
  $pdo = DB::pdo();
  $actor = Auth::requireLogin($pdo, ['admin','employee']); // ✅
  $actorId = (int)$actor['id'];

  $in = json_decode(file_get_contents('php://input'), true) ?? [];
  $targetUserId = (int)($in['user_id'] ?? 0);
  $amount = (float)($in['amount'] ?? 0);
  $note = trim((string)($in['note'] ?? ''));

  if ($targetUserId <= 0 || abs($amount) < 0.01 || $note === '') {
    http_response_code(422);
    echo json_encode(['ok'=>false,'error'=>'user_id, amount, note requis (amount ≠ 0)']);
    exit;
  }

  $pdo->beginTransaction();

  // lock user
  $stmt = $pdo->prepare("SELECT credits FROM users WHERE id = :id FOR UPDATE");
  $stmt->execute([':id' => $targetUserId]);
  $current = $stmt->fetchColumn();
  if ($current === false) throw new RuntimeException("Utilisateur introuvable");

  // apply
  $pdo->prepare("UPDATE users SET credits = credits + :amt WHERE id = :id")
      ->execute([':amt' => $amount, ':id' => $targetUserId]);

  // trace
  $pdo->prepare("
    INSERT INTO credit_transactions (user_id, trip_id, participant_id, amount, reason, meta)
    VALUES (:uid, NULL, NULL, :amt, 'manual_adjustment', :meta)
  ")->execute([
    ':uid'  => $targetUserId,
    ':amt'  => $amount,
    ':meta' => json_encode(['by' => $actorId, 'note' => $note], JSON_UNESCAPED_UNICODE),
  ]);

  $pdo->commit();

  echo json_encode([
    'ok' => true,
    'new_balance' => round(((float)$current + $amount), 2)
  ]);
} catch (Throwable $e) {
  if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
  http_response_code(400);
  echo json_encode(['ok'=>false,'error'=>'Erreur', 'details'=>$e->getMessage()]);
}
