<?php
declare(strict_types=1);

ini_set('display_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . '/../../src/DB.php';
require_once __DIR__ . '/../../src/Auth.php';

use App\Security\Auth;
use App\DB\DB;

// CORS + JSON
Auth::cors(true);
header('Content-Type: application/json; charset=utf-8');

// Méthode
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method === 'OPTIONS') {
    http_response_code(204);
    exit;
}
if ($method !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Méthode non autorisée']);
    exit;
}

// Connexion DB
try {
    $pdo = DB::pdo();
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'ok'      => false,
        'error'   => 'DB error',
        'details' => $e->getMessage()
    ]);
    exit;
}

// Auth
$claims = Auth::requireAuth();
$userId = (int)($claims['sub'] ?? 0);

// Input
$in     = json_decode(file_get_contents('php://input'), true) ?? [];
$tripId = (int)($in['trip_id'] ?? 0);

if ($tripId <= 0) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => 'trip_id manquant']);
    exit;
}

try {
    // Vérifications avant démarrage du trajet 
    $stmt = $pdo->prepare("
        SELECT id, driver_id, status, is_canceled
        FROM trips
        WHERE id = :id
    ");
    $stmt->execute([':id' => $tripId]);
    $trip = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$trip) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'error' => 'Trajet introuvable']);
        exit;
    }

    if ((int)$trip['driver_id'] !== $userId) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'Vous n’êtes pas le conducteur']);
        exit;
    }

    if ((int)$trip['is_canceled'] === 1 || $trip['status'] === 'canceled') {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Trajet annulé']);
        exit;
    }

    if ($trip['status'] !== 'planned') {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Trajet déjà démarré ou terminé']);
        exit;
    }

    // Démarrage du trajet
    $stmt = $pdo->prepare("
        UPDATE trips
        SET status = 'ongoing',
            started_at = NOW()
        WHERE id = :id
    ");
    $stmt->execute([':id'  => $tripId]);

    echo json_encode([
        'ok'      => true,
        'message' => 'Trajet démarré',
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'ok'      => false,
        'error'   => 'DB error',
        'details' => $e->getMessage()
    ]);
}
