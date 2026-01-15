<?php
declare(strict_types=1);

require_once __DIR__ . '/../../src/DB.php';
require_once __DIR__ . '/../../src/Auth.php';

use App\DB\DB;
use App\Security\Auth;

Auth::cors(true);
header('Content-Type: application/json; charset=utf-8');

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
  http_response_code(200);
  echo json_encode(['ok' => true]);
  exit;
}


if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode(['ok' => false, 'error' => 'Méthode non autorisée']);
  exit;
}

$user = Auth::requireUser();
$role = strtolower((string)($user['role'] ?? ''));

if (!in_array($role, ['admin', 'employee'], true)) {
  http_response_code(403);
  echo json_encode(['ok' => false, 'error' => 'Accès interdit']);
  exit;
}

$in = json_decode(file_get_contents('php://input'), true);
if (!is_array($in)) $in = [];

$id = (int)($in['id'] ?? 0);
$newStatus = (string)($in['status'] ?? '');

if ($id <= 0 || !in_array($newStatus, ['ok', 'issue'], true)) {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => 'Paramètres invalides']);
  exit;
}

$pdo = DB::pdo();

// Vérifier l'existence de l'avis
$check = $pdo->prepare("SELECT status FROM trip_feedbacks WHERE id = :id");
$check->execute([':id' => $id]);
$row = $check->fetch(PDO::FETCH_ASSOC);

if (!$row) {
  http_response_code(404);
  echo json_encode(['ok' => false, 'error' => 'Avis introuvable']);
  exit;
}

$upd = $pdo->prepare("
  UPDATE trip_feedbacks
  SET status = :status,
      moderated_by = :mod_by,
      moderated_at = NOW()
  WHERE id = :id
");
$upd->execute([
  ':status' => $newStatus,
  ':mod_by' => (int)($user['id'] ?? 0),
  ':id' => $id
]);

echo json_encode(['ok' => true]);
