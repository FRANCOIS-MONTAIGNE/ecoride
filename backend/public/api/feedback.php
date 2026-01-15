<?php
// backend/public/api/feedback.php
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
 * POST /api/feedback
 * Enregistre ou met à jour un retour utilisateur sur un trajet
 * body JSON : { "trip_id": 123, "status": "ok"|"issue", "comment": "..." }
 */
if ($method === 'POST') {
    // Vérification de l'authentification ( obligation d'être connecté )
    $claims = Auth::requireAuth();
    $userId = (int) ($claims['sub'] ?? 0);

    $in = json_decode(file_get_contents('php://input'), true) ?? [];

    $tripId  = (int) ($in['trip_id'] ?? 0);
    $status  = trim($in['status'] ?? '');
    $comment = trim($in['comment'] ?? '');

    if ($tripId <= 0 || ($status !== 'ok' && $status !== 'issue')) {
        http_response_code(422);
        echo json_encode([
            'ok'    => false,
            'error' => 'Paramètres invalides (trip_id, status)',
        ]);
        exit;
    }

    try {
        // Vérifie si l'utilisateur a déjà laissé un feedback pour ce trajet.
        // Si oui, on met à jour, sinon on insère.
        $sql = "
            INSERT INTO trip_feedbacks (trip_id, user_id, status, comment)
            VALUES (:trip_id, :user_id, :status, :comment)
            ON DUPLICATE KEY UPDATE
                status     = VALUES(status),
                comment    = VALUES(comment),
                created_at = CURRENT_TIMESTAMP
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':trip_id' => $tripId,
            ':user_id' => $userId,
            ':status'  => $status,
            ':comment' => $comment !== '' ? $comment : null,
        ]);

        echo json_encode([
            'ok'      => true,
            'message' => 'Merci, votre retour a été enregistré.',
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
