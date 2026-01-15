<?php
// backend/public/api/admin_stats.php
declare(strict_types=1);

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . '/../../src/DB.php';
require_once __DIR__ . '/../../src/Auth.php';

use App\DB\DB;
use App\Security\Auth;

// CORS + JSON
Auth::cors(true);
header('Content-Type: application/json; charset=utf-8');

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($method !== 'GET') {
    http_response_code(405);
    echo json_encode([
        'ok'    => false,
        'error' => 'Méthode non autorisée',
    ]);
    exit;
}

try {
    $pdo = DB::getConnection();

    // 🔐 Admin only
$admin = Auth::requireLogin($pdo, ['admin']); // vérifie que l'utilisateur est admin

    // =========================
    // STAT 1 : trips per day (7 days)
    // =========================
    $sqlTrips = "
        SELECT DATE(t.departure_datetime) AS jour, COUNT(*) AS nb_trips
        FROM trips t
        WHERE t.departure_datetime >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
        GROUP BY DATE(t.departure_datetime)
        ORDER BY jour ASC
    ";
    $stmt = $pdo->prepare($sqlTrips);
    $stmt->execute();
    $tripsRows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

    $tripsPerDay = [];
    foreach ($tripsRows as $row) {
        $tripsPerDay[] = [
            'day'   => $row['jour'],
            'count' => (int) $row['nb_trips'],
        ];
    }

    // KPI total trips sur 7 jours
    $sqlTotalTrips7 = "
        SELECT COUNT(*) AS total_trips
        FROM trips
        WHERE departure_datetime >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    ";
    $totalTrips7 = (int) $pdo->query($sqlTotalTrips7)->fetchColumn();

    // total global
    $sqlTotalTripsAll = "SELECT COUNT(*) FROM trips";
    $totalTripsAll = (int) $pdo->query($sqlTotalTripsAll)->fetchColumn();

    // =========================
    // STAT 2 : credits per day (7 days)
    // Hypothèse : chaque participant validé génère 2 crédits
    // =========================
    $creditsPerDay = [];
    $totalCredits  = 0;

    $sqlCredits = "
        SELECT DATE(created_at) AS jour, COUNT(*) * 2 AS total_credits
        FROM trip_participants
        WHERE status = 'accepted'
          AND created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
        GROUP BY DATE(created_at)
        ORDER BY jour ASC
    ";
    $stmt2 = $pdo->prepare($sqlCredits);
    $stmt2->execute();
    $creditsRows = $stmt2->fetchAll(\PDO::FETCH_ASSOC);

    foreach ($creditsRows as $row) {
        $creditsPerDay[] = [
            'day'     => $row['jour'],
            'credits' => (int) ($row['total_credits'] ?? 0),
        ];
    }

    $sqlTotalCredits = "
        SELECT COUNT(*) * 2
        FROM trip_participants
        WHERE status = 'accepted'
          AND created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    ";
    $totalCredits = (int) $pdo->query($sqlTotalCredits)->fetchColumn();

    //statut des avis en attente
    $sqlPendingReviews = "SELECT COUNT(*) FROM trip_participants WHERE review_status = 'pending'";
    $pendingReviews = (int) $pdo->query($sqlPendingReviews)->fetchColumn();

    echo json_encode([
        'ok'              => true,

        'trips_per_day'   => $tripsPerDay,
        'credits_per_day' => $creditsPerDay,

        'total_credits'   => $totalCredits,
        'pending_reviews' => $pendingReviews,   

        'total_trips'     => $totalTrips7,
        'total_trips_all' => $totalTripsAll,
    ]);

} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'ok'      => false,
        'message' => 'Erreur DB',
        'details' => $e->getMessage(),
    ]);
}
