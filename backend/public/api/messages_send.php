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
if ($method !== 'POST') { http_response_code(405); echo json_encode(['ok'=>false,'error'=>'Méthode non autorisée']); exit; }

$pdo = DB::pdo();
$user = Auth::requireLogin($pdo, ['admin','employee','user']);
$uid = (int)($user['id'] ?? 0);

$in = json_decode(file_get_contents('php://input'), true) ?? [];
$convId = (int)($in['conversation_id'] ?? 0);
$body = trim((string)($in['body'] ?? ''));

if ($convId <= 0 || $body === '') {
  http_response_code(400);
  echo json_encode(['ok'=>false,'error'=>'conversation_id et body requis']);
  exit;
}
if (mb_strlen($body) > 2000) {
  http_response_code(400);
  echo json_encode(['ok'=>false,'error'=>'Message trop long (max 2000)']);
  exit;
}

// Check conversation access
$check = "
SELECT c.id
FROM conversations c
JOIN trip_participants tp ON tp.id = c.participant_id
JOIN trips t ON t.id = c.trip_id
WHERE c.id = :cid
  AND (c.driver_id = :uid OR c.passenger_id = :uid)
  AND tp.status = 'accepted'
  AND t.is_canceled = 0
LIMIT 1
";
$stmt = $pdo->prepare($check);
$stmt->execute([':cid'=>$convId, ':uid'=>$uid]);
if (!$stmt->fetchColumn()) {
  http_response_code(403);
  echo json_encode(['ok'=>false,'error'=>'Accès interdit']);
  exit;
}

// Insert message
$stmt = $pdo->prepare("
  INSERT INTO messages (conversation_id, sender_id, body, is_read)
  VALUES (:cid, :uid, :body, 0)
");
$stmt->execute([':cid'=>$convId, ':uid'=>$uid, ':body'=>$body]);

// Update conversation's last_message_at
$pdo->prepare("UPDATE conversations SET last_message_at = NOW() WHERE id = :cid")
    ->execute([':cid'=>$convId]);

echo json_encode(['ok'=>true], JSON_UNESCAPED_UNICODE);
