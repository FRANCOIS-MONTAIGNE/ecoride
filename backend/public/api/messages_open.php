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
$participantId = (int)($in['participant_id'] ?? 0);

if ($participantId <= 0) {
  http_response_code(400);
  echo json_encode(['ok'=>false,'error'=>'participant_id manquant']);
  exit;
}

// 1) Vérifie réservation et droits d'accès
$sql = "
SELECT
  tp.id AS participant_id,
  tp.status AS booking_status,
  tp.user_id AS passenger_id,
  t.id AS trip_id,
  t.driver_id,
  t.is_canceled
FROM trip_participants tp
JOIN trips t ON t.id = tp.trip_id
WHERE tp.id = :pid
LIMIT 1
";
$stmt = $pdo->prepare($sql);
$stmt->execute([':pid' => $participantId]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$row) {
  http_response_code(404);
  echo json_encode(['ok'=>false,'error'=>'Réservation introuvable']);
  exit;
}

if (strtolower((string)$row['booking_status']) !== 'accepted') {
  http_response_code(403);
  echo json_encode(['ok'=>false,'error'=>'Messagerie disponible uniquement si réservation acceptée']);
  exit;
}

if ((int)$row['is_canceled'] === 1) {
  http_response_code(403);
  echo json_encode(['ok'=>false,'error'=>'Trajet annulé : messagerie indisponible']);
  exit;
}

$driverId = (int)$row['driver_id'];
$passengerId = (int)$row['passenger_id'];
if ($uid !== $driverId && $uid !== $passengerId) {
  http_response_code(403);
  echo json_encode(['ok'=>false,'error'=>'Accès interdit']);
  exit;
}

// 2) Cherche conversation existante
$stmt = $pdo->prepare("SELECT id FROM conversations WHERE participant_id = :pid LIMIT 1");
$stmt->execute([':pid' => $participantId]);
$convId = (int)($stmt->fetchColumn() ?: 0);

if ($convId <= 0) {
  // 3) Crée nouvelle conversation
  $stmt = $pdo->prepare("
    INSERT INTO conversations (trip_id, participant_id, driver_id, passenger_id, last_message_at)
    VALUES (:trip_id, :pid, :driver_id, :passenger_id, NULL)
  ");
  $stmt->execute([
    ':trip_id' => (int)$row['trip_id'],
    ':pid' => $participantId,
    ':driver_id' => $driverId,
    ':passenger_id' => $passengerId
  ]);
  $convId = (int)$pdo->lastInsertId();
}

echo json_encode(['ok'=>true,'conversation_id'=>$convId], JSON_UNESCAPED_UNICODE);
