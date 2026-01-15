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
if ($method !== 'GET') { http_response_code(405); echo json_encode(['ok'=>false,'error'=>'Méthode non autorisée']); exit; }

$pdo = DB::pdo();
$user = Auth::requireLogin($pdo, ['admin','employee','user']);
$uid = (int)($user['id'] ?? 0);

$convId = (int)($_GET['conversation_id'] ?? 0);
if ($convId <= 0) {
  http_response_code(400);
  echo json_encode(['ok'=>false,'error'=>'conversation_id manquant']);
  exit;
}

// Vérification des droits d'accès à la conversation
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

// Récupération des messages
$stmt = $pdo->prepare("
  SELECT id, conversation_id, sender_id, body, created_at, is_read
  FROM messages
  WHERE conversation_id = :cid
  ORDER BY created_at ASC, id ASC
  LIMIT 300
");
$stmt->execute([':cid'=>$convId]);
$messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Marquer les messages comme lus
$stmt = $pdo->prepare("
  UPDATE messages
  SET is_read = 1
  WHERE conversation_id = :cid AND sender_id <> :uid
");
$stmt->execute([':cid'=>$convId, ':uid'=>$uid]);

echo json_encode(['ok'=>true,'messages'=>$messages], JSON_UNESCAPED_UNICODE);
