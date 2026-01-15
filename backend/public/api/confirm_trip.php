<?php
declare(strict_types=1);

ini_set('display_errors', '1');
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
    echo json_encode(['ok' => false, 'error' => 'Méthode non autorisée']);
    exit;
}

try {
    $pdo = DB::pdo();
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'DB error', 'details' => $e->getMessage()]);
    exit;
}

$claims = Auth::requireAuth();
$userId = (int)($claims['sub'] ?? 0);

$in       = json_decode(file_get_contents('php://input'), true) ?? [];
$tripId   = (int)($in['trip_id'] ?? 0);
$ok       = !empty($in['ok']);          // true si tout va bien, false si problème
$rating   = isset($in['rating']) ? (int)$in['rating'] : null;
$comment  = trim((string)($in['comment'] ?? ''));

if ($tripId <= 0) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => 'trip_id manquant']);
    exit;
}

try {
    $pdo->beginTransaction();

    // 1) Verrouille la participation + récupère infos du trajet (avec payment_status)
    $stmt = $pdo->prepare("
        SELECT
            tp.id AS participant_id,
            tp.seats,
            tp.status AS participant_status,
            tp.confirm_status,
            tp.payment_status,
            t.driver_id,
            t.price
        FROM trip_participants tp
        JOIN trips t ON t.id = tp.trip_id
        WHERE tp.trip_id = :trip_id
          AND tp.user_id = :user_id
        FOR UPDATE
    ");
    $stmt->execute([
        ':trip_id' => $tripId,
        ':user_id' => $userId,
    ]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        $pdo->rollBack();
        http_response_code(404);
        echo json_encode(['ok' => false, 'error' => 'Vous ne participez pas à ce trajet']);
        exit;
    }

    if (($row['confirm_status'] ?? '') !== 'pending') {
        $pdo->rollBack();
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Vous avez déjà confirmé ce trajet']);
        exit;
    }

    // Vérifie que la réservation est acceptée avant de payer
    if ($ok && ($row['participant_status'] ?? '') !== 'accepted') {
        $pdo->rollBack();
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => "Paiement impossible: réservation non acceptée"]);
        exit;
    }

    // Vérifie que le paiement n'a pas déjà été effectué
    if ($ok && (($row['payment_status'] ?? 'unpaid') === 'paid')) {
        $pdo->rollBack();
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => "Paiement déjà effectué"]);
        exit;
    }

    $confirmStatus = $ok ? 'ok' : 'issue';

    // 2) Met à jour le statut de confirmation + avis
    $stmt = $pdo->prepare("
        UPDATE trip_participants
        SET confirm_status = :status,
            rating = :rating,
            comment = :comment,
            review_status = 'pending'
        WHERE trip_id = :trip_id
          AND user_id = :user_id
    ");
    $stmt->execute([
        ':status'   => $confirmStatus,
        ':rating'   => ($rating !== null && $rating > 0) ? $rating : null,
        ':comment'  => ($comment !== '') ? $comment : null,
        ':trip_id'  => $tripId,
        ':user_id'  => $userId,
    ]);

    // 3) Si ok, effectue le paiement
    if ($ok) {
        $participantId = (int)$row['participant_id'];
        $driverId      = (int)$row['driver_id'];
        $seats         = (int)$row['seats'];
        $price         = (float)$row['price'];

        $amount = round($price * $seats, 2);

        // Calcul montant chauffeur (après frais)
        $fee = 0.00;
        $driverAmount = round($amount - $fee, 2);
        if ($driverAmount < 0) $driverAmount = 0.00;

        // Verrouille les comptes utilisateurs
        $lock = $pdo->prepare("SELECT credits FROM users WHERE id = :id FOR UPDATE");

        // Passager (verrouillage + vérification crédits)
        $lock->execute([':id' => $userId]);
        $passCredits = (float)$lock->fetchColumn();
        if ($passCredits < $amount) {
            throw new RuntimeException("Crédits insuffisants (il vous manque " . round($amount - $passCredits, 2) . " crédits).");
        }

        // Chauffeur
        $lock->execute([':id' => $driverId]);
        $lock->fetchColumn();

        // Débite le passager
        $pdo->prepare("UPDATE users SET credits = credits - :amt WHERE id = :id")
            ->execute([':amt' => $amount, ':id' => $userId]);

        // Crédite le chauffeur
        $pdo->prepare("UPDATE users SET credits = credits + :amt WHERE id = :id")
            ->execute([':amt' => $driverAmount, ':id' => $driverId]);

        // Historique
        $tx = $pdo->prepare("
            INSERT INTO credit_transactions (user_id, trip_id, participant_id, amount, reason)
            VALUES (:uid, :tid, :pid, :amt, :reason)
        ");

        $tx->execute([
            ':uid' => $userId,
            ':tid' => $tripId,
            ':pid' => $participantId,
            ':amt' => -$amount,
            ':reason' => 'booking_payment'
        ]);

        $tx->execute([
            ':uid' => $driverId,
            ':tid' => $tripId,
            ':pid' => $participantId,
            ':amt' => $driverAmount,
            ':reason' => 'driver_payout'
        ]);

        // Met à jour le statut de paiement
        $pdo->prepare("
            UPDATE trip_participants
            SET payment_status = 'paid',
                paid_at = NOW()
            WHERE id = :pid
        ")->execute([':pid' => $participantId]);
    }

    $pdo->commit();

    echo json_encode([
        'ok'      => true,
        'message' => $ok
            ? 'Merci, votre confirmation a été enregistrée et le paiement a été effectué.'
            : 'Votre signalement a été enregistré, un employé examinera la situation.',
    ]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Erreur', 'details' => $e->getMessage()]);
}
