<?php
// backend/public/api/my_trips.php
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

// Gestion des requêtes OPTIONS pour CORS
if ($method === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// Seules les requêtes GET sont autorisées
if ($method !== 'GET') {
    http_response_code(405);
    echo json_encode([
        'ok'    => false,
        'error' => 'Méthode non autorisée',
    ]);
    exit;
}

// ================== Connexion DB ==================
try {
    $pdo = DB::pdo();
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'ok'      => false,
        'error'   => 'DB connection error',
        'details' => $e->getMessage(),
    ]);
    exit;
}

// ================== Utilisateur connecté ==================
try {
    $claims = Auth::requireAuth();  // JWT obligatoire
} catch (Throwable $e) {
    http_response_code(401);
    echo json_encode([
        'ok'    => false,
        'error' => 'Non authentifié',
    ]);
    exit;
}

$userId = (int) ($claims['sub'] ?? 0);
if ($userId <= 0) {
    http_response_code(401);
    echo json_encode([
        'ok'    => false,
        'error' => 'Token invalide',
    ]);
    exit;
}

try {
    // =====================================================
    // 1) Trajets où l'utilisateur est CONDUCTEUR
    // =====================================================
    $sqlDriver = "
        SELECT
            t.id,
            t.driver_id,
            t.origin_city,
            t.dest_city,
            t.price,
            t.total_seats,
            t.available_seats,
            DATE_FORMAT(t.departure_datetime, '%Y-%m-%d %H:%i:%s') AS date,
            DATE_FORMAT(t.arrival_datetime,   '%Y-%m-%d %H:%i:%s') AS arrival_datetime,
            t.note,
            t.note AS rating,
            t.eco,
            t.car,
            t.plate_display,
            t.smoker,
            t.pets,
            t.music,
            t.quiet,
            t.is_canceled,
            t.created_at,
            u.full_name AS driver_name
        FROM trips t
        LEFT JOIN users u ON u.id = t.driver_id
        WHERE t.driver_id = :uid
        ORDER BY t.departure_datetime DESC
    ";

    $stmtDriver = $pdo->prepare($sqlDriver);
    $stmtDriver->execute([':uid' => $userId]);
    $driverTrips = $stmtDriver->fetchAll(PDO::FETCH_ASSOC);

    // =====================================================
    // 2) Trajets où l'utilisateur est PASSAGER
    // =====================================================
    $sqlPassenger = "
        SELECT
            t.id,
            t.driver_id,
            t.origin_city,
            t.dest_city,
            t.price,
            t.total_seats,
            t.available_seats,
            DATE_FORMAT(t.departure_datetime, '%Y-%m-%d %H:%i:%s') AS date,
            DATE_FORMAT(t.arrival_datetime,   '%Y-%m-%d %H:%i:%s') AS arrival_datetime,
            t.note,
            t.note AS rating,
            t.eco,
            t.car,
            t.plate_display,
            t.smoker,
            t.pets,
            t.music,
            t.quiet,
            t.is_canceled,
            t.created_at,
            u.full_name AS driver_name,
            tp.seats AS booked_seats
        FROM trip_participants tp
        JOIN trips t ON t.id = tp.trip_id
        LEFT JOIN users u ON u.id = t.driver_id
        WHERE tp.user_id = :uid
        ORDER BY t.departure_datetime DESC
    ";

    $stmtPassenger = $pdo->prepare($sqlPassenger);
    $stmtPassenger->execute([':uid' => $userId]);
    $passengerTrips = $stmtPassenger->fetchAll(PDO::FETCH_ASSOC);

    // =====================================================
    // 3) Récupérer la liste des passagers pour TOUS ces trajets
    // =====================================================
    $tripIds = [];

    foreach ($driverTrips as $t) {
        $tripIds[] = (int) $t['id'];
    }
    foreach ($passengerTrips as $t) {
        $tripIds[] = (int) $t['id'];
    }

    $tripIds = array_values(array_unique($tripIds));
    $participantsByTrip = [];

    if (!empty($tripIds)) {
        $placeholders = implode(',', array_fill(0, count($tripIds), '?'));

        $sqlPart = "
            SELECT
                tp.trip_id,
                u.id        AS user_id,
                u.full_name AS full_name,
                u.email     AS email
            FROM trip_participants tp
            JOIN users u ON u.id = tp.user_id
            WHERE tp.trip_id IN ($placeholders)
        ";

        $stmtPart = $pdo->prepare($sqlPart);
        $stmtPart->execute($tripIds);
        $partRows = $stmtPart->fetchAll(PDO::FETCH_ASSOC);

        foreach ($partRows as $p) {
            $tid = (int) $p['trip_id'];
            if (!isset($participantsByTrip[$tid])) {
                $participantsByTrip[$tid] = [];
            }
            $participantsByTrip[$tid][] = [
                'id'        => (int) $p['user_id'],
                'name'      => $p['full_name'],
                'full_name' => $p['full_name'],
                'email'     => $p['email'],
            ];
        }
    }

    // Attacher participants à chaque trajet conducteur
    foreach ($driverTrips as &$t) {
        $tid = (int) $t['id'];
        $t['participants'] = $participantsByTrip[$tid] ?? [];
    }
    unset($t);

    // Attacher participants à chaque trajet passager
    foreach ($passengerTrips as &$t) {
        $tid = (int) $t['id'];
        $t['participants'] = $participantsByTrip[$tid] ?? [];
    }
    unset($t);

    // =====================================================
    // 4) Réponse JSON
    // =====================================================
    echo json_encode([
        'ok'              => true,
        'driver_trips'    => $driverTrips,
        'passenger_trips' => $passengerTrips,
    ]);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'ok'      => false,
        'error'   => 'DB error',
        'details' => $e->getMessage(),
    ]);
}
