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

$sql = "
SELECT
  c.id,
  c.trip_id,
  c.participant_id,
  c.driver_id,
  c.passenger_id,
  c.last_message_at,
  t.origin_city,
  t.dest_city,
  t.departure_datetime,
  u1.full_name AS driver_name,
  u2.full_name AS passenger_name,
  (SELECT COUNT(*) FROM messages m WHERE m.conversation_id = c.id AND m.is_read = 0 AND m.sender_id <> :uid) AS unread_count
FROM conversations c
JOIN trip_participants tp ON tp.id = c.participant_id
JOIN trips t ON t.id = c.trip_id
JOIN users u1 ON u1.id = c.driver_id
JOIN users u2 ON u2.id = c.passenger_id
WHERE (c.driver_id = :uid OR c.passenger_id = :uid)
  AND tp.status = 'accepted'
  AND t.is_canceled = 0
ORDER BY COALESCE(c.last_message_at, c.created_at) DESC
";
$stmt = $pdo->prepare($sql);
$stmt->execute([':uid' => $uid]);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode(['ok'=>true,'items'=>$items], JSON_UNESCAPED_UNICODE);
