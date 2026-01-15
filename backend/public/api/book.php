<?php
declare(strict_types=1);

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . '/../../src/DB.php';
require_once __DIR__ . '/../../src/Auth.php';

use App\Security\Auth;
use App\DB\DB;

Auth::cors();
header('Content-Type: application/json; charset=utf-8');

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method === 'OPTIONS') { http_response_code(204); exit; }
if ($method !== 'POST') {
  http_response_code(405);
  echo json_encode(['ok'=>false,'error'=>'Méthode non autorisée']);
  exit;
}

try { $pdo = DB::pdo(); }
catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>'DB connection error']);
  exit;
}

$claims = Auth::requireAuth();
$userId = (int)($claims['sub'] ?? 0);

$in = json_decode(file_get_contents('php://input'), true);
if (!is_array($in)) $in = [];

$tripId = (int)($in['trip_id'] ?? 0);
$seats  = (int)($in['seats'] ?? 1);

if ($tripId <= 0 || $seats <= 0) {
  http_response_code(422);
  echo json_encode(['ok'=>false,'error'=>'trip_id et seats doivent être > 0']);
  exit;
}

try {
  $pdo->beginTransaction();

  $stmt = $pdo->prepare("
    SELECT id, driver_id, available_seats, is_canceled
    FROM trips
    WHERE id = :id
    FOR UPDATE
  ");
  $stmt->execute([':id' => $tripId]);
  $trip = $stmt->fetch(PDO::FETCH_ASSOC);

  if (!$trip) {
    $pdo->rollBack();
    http_response_code(404);
    echo json_encode(['ok'=>false,'error'=>'Trajet introuvable']);
    exit;
  }

  if ((int)$trip['is_canceled'] === 1) {
    $pdo->rollBack();
    http_response_code(409);
    echo json_encode(['ok'=>false,'error'=>'Ce trajet est annulé']);
    exit;
  }

  if ((int)$trip['driver_id'] === $userId) {
    $pdo->rollBack();
    http_response_code(403);
    echo json_encode(['ok'=>false,'error'=>'Le conducteur ne peut pas réserver son propre trajet']);
    exit;
  }

  if ((int)$trip['available_seats'] < $seats) {
    $pdo->rollBack();
    http_response_code(409);
    echo json_encode(['ok'=>false,'error'=>'Plus assez de places disponibles']);
    exit;
  }

  // check existing reservation
  $stmt = $pdo->prepare("
    SELECT id
    FROM trip_participants
    WHERE trip_id = :trip_id
      AND user_id = :user_id
      AND status <> 'canceled'
    LIMIT 1
  ");
  $stmt->execute([':trip_id'=>$tripId, ':user_id'=>$userId]);
  if ($stmt->fetch()) {
    $pdo->rollBack();
    http_response_code(409);
    echo json_encode(['ok'=>false,'error'=>'Vous avez déjà une réservation pour ce trajet']);
    exit;
  }

  // insert reservation
  $stmt = $pdo->prepare("
    INSERT INTO trip_participants (trip_id, user_id, seats, status)
    VALUES (:trip_id, :user_id, :seats, 'accepted')
  ");
  $stmt->execute([':trip_id'=>$tripId, ':user_id'=>$userId, ':seats'=>$seats]);

  // update available seats
  $stmt = $pdo->prepare("
    UPDATE trips
    SET available_seats = available_seats - :seats
    WHERE id = :id
  ");
  $stmt->execute([':seats'=>$seats, ':id'=>$tripId]);

  $pdo->commit();

  http_response_code(201);
  echo json_encode(['ok'=>true,'message'=>'Réservation confirmée ✅']);

} catch (Throwable $e) {
  if ($pdo->inTransaction()) $pdo->rollBack();
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>'Erreur serveur','details'=>$e->getMessage()]);
}
