<?php
// backend/public/api/trips.php
declare(strict_types=1);

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . '/../../src/DB.php';
require_once __DIR__ . '/../../src/Auth.php';

use App\Security\Auth;
use App\DB\DB;

// CORS (GET public, POST avec token)
Auth::cors();
header('Content-Type: application/json; charset=utf-8');

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// Préflight CORS
if ($method === 'OPTIONS') {
    http_response_code(204);
    exit;
}

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

/**
 * GET /api/trips.php
 * Liste les trajets (avec nom du conducteur + participants)
 * + filtres avancés : eco, price_max, duration_max, rating_min
 */
if ($method === 'GET') {

    $from = trim($_GET['from'] ?? '');
    $to   = trim($_GET['to']   ?? '');
    $date = trim($_GET['date'] ?? ''); // YYYY-MM-DD

    // Filtres avancés
    $eco         = isset($_GET['eco']) && $_GET['eco'] === '1';
    $priceMax    = isset($_GET['price_max']) && $_GET['price_max'] !== '' ? (int) $_GET['price_max'] : null;
    $durationMax = isset($_GET['duration_max']) && $_GET['duration_max'] !== '' ? (int) $_GET['duration_max'] : null;
    $ratingMin   = isset($_GET['rating_min']) && $_GET['rating_min'] !== '' ? (float) $_GET['rating_min'] : null;

    // Sélection des trajets
    $sql = "
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
            COALESCE(u.rating, 0) AS rating
        FROM trips t
        LEFT JOIN users u ON u.id = t.driver_id
        WHERE t.is_canceled = 0
          AND t.available_seats > 0
    ";

    $params = [];

    if ($from !== '') {
        $sql .= " AND t.origin_city = :from";
        $params[':from'] = $from;
    }
    if ($to !== '') {
        $sql .= " AND t.dest_city = :to";
        $params[':to'] = $to;
    }
    if ($date !== '') {
        $sql .= " AND DATE(t.departure_datetime) = :d";
        $params[':d'] = $date;
    }

    if ($eco) {
        $sql .= " AND t.eco = 1";
    }

    if ($priceMax !== null && $priceMax > 0) {
        $sql .= " AND t.price <= :price_max";
        $params[':price_max'] = $priceMax;
    }

    if ($durationMax !== null && $durationMax > 0) {
        // durée en minutes entre départ et arrivée
        $sql .= " AND TIMESTAMPDIFF(MINUTE, t.departure_datetime, t.arrival_datetime) <= :duration_max";
        $params[':duration_max'] = $durationMax;
    }

    if ($ratingMin !== null && $ratingMin > 0) {
        // ici on utilise la note du conducteur (u.rating)
        $sql .= " AND COALESCE(u.rating,0) >= :rating_min";
        $params[':rating_min'] = $ratingMin;
    }

    $sql .= " ORDER BY t.departure_datetime ASC";

    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // ============= Récupération des passagers par trajet =============
$tripIds = array_column($rows, 'id');
$participantsByTrip = [];

if (!empty($tripIds)) {
    $placeholders = implode(',', array_fill(0, count($tripIds), '?'));

    $sqlPart = "
        SELECT
            tp.trip_id,
            tp.seats,
            tp.status,
            u.id        AS user_id,
            u.full_name AS full_name,
            u.email     AS email
        FROM trip_participants tp
        JOIN users u ON u.id = tp.user_id
        WHERE tp.trip_id IN ($placeholders)
          AND tp.status <> 'canceled'
    ";

    $stmtPart = $pdo->prepare($sqlPart);
    $stmtPart->execute($tripIds);
    $partRows = $stmtPart->fetchAll(PDO::FETCH_ASSOC);

    foreach ($partRows as $p) {
        $tripId = (int)$p['trip_id'];

        if (!isset($participantsByTrip[$tripId])) {
            $participantsByTrip[$tripId] = [];
        }

        $participantsByTrip[$tripId][] = [
            'id'           => (int)$p['user_id'],
            'name'         => $p['full_name'],
            'full_name'    => $p['full_name'],
            'email'        => $p['email'],
            'seats' => (int)$p['seats'],
            'status'       => $p['status'],
        ];
    }
}

// on attache les participants à chaque trajet
foreach ($rows as &$trip) {
    $id = (int) $trip['id'];
    $trip['participants'] = $participantsByTrip[$id] ?? [];
}
unset($trip);
// =========== FIN participants ===========

      
// Si aucun résultat, calcul de la première date suivante disponible
        $nextDate = null;
        if (empty($rows) && $date !== '') {
            $sqlNext = "
                SELECT DATE(MIN(t.departure_datetime)) AS next_date
                FROM trips t
                WHERE t.is_canceled = 0
                  AND t.available_seats > 0
                  AND DATE(t.departure_datetime) > :d
            ";
            $stmtNext = $pdo->prepare($sqlNext);
            $stmtNext->execute([':d' => $date]);
            $rowNext = $stmtNext->fetch(PDO::FETCH_ASSOC);
            if ($rowNext && !empty($rowNext['next_date'])) {
                $nextDate = $rowNext['next_date'];
            }
        }

        echo json_encode([
            'ok'        => true,
            'trips'     => $rows,
            'next_date' => $nextDate,
        ]);
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode([
            'ok'      => false,
            'error'   => 'DB error',
            'details' => $e->getMessage(),
        ]);
    }

    exit;
}

/**
 * POST /api/trips.php
 * Création d’un trajet (par le conducteur connecté)
 */
if ($method === 'POST') {

    // récup ID utilisateur depuis le token (obligatoire)
    $claims = Auth::requireAuth();
    $userId = (int) ($claims['sub'] ?? 0);

    $in = json_decode(file_get_contents('php://input'), true) ?? [];

    $origin = trim($in['origin_city'] ?? '');
    $dest   = trim($in['dest_city']   ?? '');
    $price  = (int) ($in['price']     ?? 0);
    $seats  = (int) ($in['seats']     ?? 0);

    // le JS envoie déjà "YYYY-MM-DD HH:MM:SS"
    $departRaw = trim($in['departure'] ?? '');

    $car   = trim($in['car']   ?? '');
    $plate = trim($in['plate'] ?? '');
    $note  = trim($in['note']  ?? '');

    // options booléennes
    $eco    = !empty($in['eco'])    ? 1 : 0;
    $smoker = !empty($in['smoker']) ? 1 : 0;
    $pets   = !empty($in['pets'])   ? 1 : 0;
    $music  = !empty($in['music'])  ? 1 : 0;
    $quiet  = !empty($in['quiet'])  ? 1 : 0;

    // validation simple
    if ($origin === '' || $dest === '' || $price <= 0 || $seats <= 0 || $departRaw === '') {
        http_response_code(422);
        echo json_encode([
            'ok'    => false,
            'error' => 'Champs requis : origin_city, dest_city, price>0, seats>0, departure',
        ]);
        exit;
    }

    $dt = \DateTime::createFromFormat('Y-m-d H:i:s', $departRaw);
    if (!$dt) {
        http_response_code(422);
        echo json_encode([
            'ok'    => false,
            'error' => 'Format departure invalide (Y-m-d H:i:s)',
        ]);
        exit;
    }

    $depart = $dt->format('Y-m-d H:i:s');

    try {
        // arrival_datetime = +2h pour l’exemple
        $stmt = $pdo->prepare("
            INSERT INTO trips (
                driver_id,
                origin_city,
                dest_city,
                price,
                total_seats,
                available_seats,
                departure_datetime,
                arrival_datetime,
                note,
                eco,
                car,
                plate_display,
                smoker,
                pets,
                music,
                quiet,
                is_canceled
            ) VALUES (
                :driver_id,
                :origin_city,
                :dest_city,
                :price,
                :total_seats,
                :available_seats,
                :departure_datetime,
                DATE_ADD(:departure_datetime, INTERVAL 2 HOUR),
                :note,
                :eco,
                :car,
                :plate_display,
                :smoker,
                :pets,
                :music,
                :quiet,
                0
            )
        ");

        $stmt->execute([
            ':driver_id'          => $userId,
            ':origin_city'        => $origin,
            ':dest_city'          => $dest,
            ':price'              => $price,
            ':total_seats'        => $seats,
            ':available_seats'    => $seats,
            ':departure_datetime' => $depart,
            ':note'               => $note,
            ':eco'                => $eco,
            ':car'                => $car,
            ':plate_display'      => $plate,
            ':smoker'             => $smoker,
            ':pets'               => $pets,
            ':music'              => $music,
            ':quiet'              => $quiet,
        ]);

        $id = (int) $pdo->lastInsertId();

        echo json_encode([
            'ok'   => true,
            'trip' => [
                'id'              => $id,
                'origin_city'     => $origin,
                'dest_city'       => $dest,
                'price'           => $price,
                'total_seats'     => $seats,
                'available_seats' => $seats,
                'date'            => $depart,
                'car'             => $car,
                'plate_display'   => $plate,
            ],
        ]);
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode([
            'ok'      => false,
            'error'   => 'DB error',
            'details' => $e->getMessage(),
        ]);
    }

    exit;
}

// Méthode non autorisée
http_response_code(405);
echo json_encode([
    'ok'    => false,
    'error' => 'Méthode non autorisée',
]);
