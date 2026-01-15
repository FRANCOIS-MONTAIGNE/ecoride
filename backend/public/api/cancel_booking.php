<?php
// backend/public/api/cancel_booking.php
declare(strict_types=1);

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . '/../../src/DB.php';
require_once __DIR__ . '/../../src/Auth.php';

use App\Security\Auth;
use App\DB\DB;

// CORS
Auth::cors();
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
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>'DB connection error']);
  exit;
}

// Auth
$claims = Auth::requireAuth();
$userId = (int)($claims['sub'] ?? 0);

// Input
$data = json_decode(file_get_contents('php://input'), true);
if (!is_array($data)) $data = [];

$tripId = (int)($data['trip_id'] ?? 0);
if ($tripId <= 0) {
  http_response_code(422);
  echo json_encode(['ok'=>false,'error'=>'Paramètre trip_id manquant ou invalide']);
  exit;
}

try {
  $pdo->beginTransaction();

  //  Vérifier l'existence d'une réservation active
  $stmt = $pdo->prepare("
    SELECT id, seats
    FROM trip_participants
    WHERE trip_id = :trip_id
      AND user_id = :user_id
      AND status <> 'canceled'
    LIMIT 1
    FOR UPDATE
  ");
  $stmt->execute([':trip_id'=>$tripId, ':user_id'=>$userId]);
  $booking = $stmt->fetch(PDO::FETCH_ASSOC);

  if (!$booking) {
    $pdo->rollBack();
    http_response_code(404);
    echo json_encode(['ok'=>false,'error'=>'Aucune réservation active pour ce trajet']);
    exit;
  }

  $seats = max(1, (int)$booking['seats']);

  // 1) Annuler la réservation
  $stmt = $pdo->prepare("
    UPDATE trip_participants
    SET status = 'canceled'
    WHERE id = :id
  ");
  $stmt->execute([':id' => (int)$booking['id']]);

  // 2) Restaurer les places disponibles du trajet
  $stmt = $pdo->prepare("
    UPDATE trips
    SET available_seats = available_seats + :seats
    WHERE id = :trip_id
  ");
  $stmt->execute([':seats'=>$seats, ':trip_id'=>$tripId]);

  $pdo->commit();

  http_response_code(200);
  echo json_encode(['ok'=>true,'message'=>'Réservation annulée ✅']);

} catch (Throwable $e) {
  if ($pdo->inTransaction()) $pdo->rollBack();
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>'Erreur serveur','details'=>$e->getMessage()]);
}
