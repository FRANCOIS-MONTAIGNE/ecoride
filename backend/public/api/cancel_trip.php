<?php
// backend/public/api/cancel_trip.php
declare(strict_types=1);

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . '/../../src/DB.php';
require_once __DIR__ . '/../../src/Auth.php';
require_once __DIR__ . '/../../src/Mailer.php';
use App\Utils\Mailer;


use App\Security\Auth;
use App\DB\DB;

Auth::cors();
header('Content-Type: application/json; charset=utf-8');

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($method !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'ok'    => false,
        'error' => 'Méthode non autorisée (POST uniquement)',
    ]);
    exit;
}

// Utilisateur connecté
$claims = Auth::requireAuth();
$userId = (int)($claims['sub'] ?? 0);

$in = json_decode(file_get_contents('php://input'), true) ?? [];
$tripId = isset($in['trip_id']) ? (int)$in['trip_id'] : 0;

if ($tripId <= 0) {
    http_response_code(422);
    echo json_encode([
        'ok'    => false,
        'error' => 'Paramètre trip_id manquant ou invalide',
    ]);
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

try {
    $pdo->beginTransaction();

    // 1) Récupérer le trajet
    $stmt = $pdo->prepare("SELECT * FROM trips WHERE id = :id FOR UPDATE");
    $stmt->execute([':id' => $tripId]);
    $trip = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$trip) {
        $pdo->rollBack();
        http_response_code(404);
        echo json_encode([
            'ok'    => false,
            'error' => 'Trajet introuvable',
        ]);
        exit;
    }

    if ((int)$trip['driver_id'] !== $userId) {
        $pdo->rollBack();
        http_response_code(403);
        echo json_encode([
            'ok'    => false,
            'error' => 'Vous n’êtes pas le conducteur de ce trajet.',
        ]);
        exit;
    }

    if ((int)$trip['is_canceled'] === 1) {
        $pdo->rollBack();
        echo json_encode([
            'ok'    => true,
            'message' => 'Trajet déjà annulé.',
        ]);
        exit;
    }

    // 2) Annuler le trajet
    $stmt = $pdo->prepare("
        UPDATE trips
        SET is_canceled = 1
        WHERE id = :id
    ");
    $stmt->execute([':id' => $tripId]);

    // 3) Mettre à jour les participations
    $stmt = $pdo->prepare("
        UPDATE trip_participants
        SET status = 'canceled'
        WHERE trip_id = :trip_id
          AND status <> 'canceled'
    ");
    $stmt->execute([':trip_id' => $tripId]);

    $pdo->commit();

    echo json_encode([
        'ok'      => true,
        'message' => 'Trajet annulé ✅',
    ]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode([
        'ok'      => false,
        'error'   => 'Erreur lors de l’annulation',
        'details' => $e->getMessage(),
    ]);
}

//  récupérer les passagers concernés
$stmt = $pdo->prepare("
  SELECT u.email, u.full_name
  FROM trip_participants tp
  JOIN users u ON u.id = tp.user_id
  WHERE tp.trip_id = :trip_id
    AND tp.status = 'accepted'
");
$stmt->execute([':trip_id' => $tripId]);
$passengers = $stmt->fetchAll(PDO::FETCH_ASSOC);

//  envoyer les emails de notification (simmulation)
foreach ($passengers as $p) {
    Mailer::send(
        $p['email'],
        "Annulation de votre covoiturage EcoRide",
        "Bonjour {$p['full_name']},\n\n"
        . "Le covoiturage que vous aviez réservé a été annulé par le conducteur.\n\n"
        . "Vos crédits ont été automatiquement recrédités.\n\n"
        . "Merci de votre compréhension.\n\n"
        . "— L’équipe EcoRide"
    );
}
