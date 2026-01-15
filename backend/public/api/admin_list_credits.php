<?php
declare(strict_types=1);

require_once __DIR__ . '/../../src/DB.php';
require_once __DIR__ . '/../../src/Auth.php';

use App\DB\DB;
use App\Security\Auth;

Auth::cors(true);
header('Content-Type: application/json; charset=utf-8');

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method === 'OPTIONS') { http_response_code(204); exit; }
if ($method !== 'GET') {
  http_response_code(405);
  echo json_encode(['ok'=>false,'error'=>'Méthode non autorisée']);
  exit;
}

try {
  $pdo = DB::pdo();

  // ✅ admin + employee
  Auth::requireLogin($pdo, ['admin','employee']);

  $limit = (int)($_GET['limit'] ?? 50);
  if ($limit < 1) $limit = 50;
  if ($limit > 200) $limit = 200;

  $userId = (int)($_GET['user_id'] ?? 0);

  $sql = "
    SELECT
      ct.id,
      ct.user_id,
      u.email AS user_email,
      ct.amount,
      ct.meta,
      ct.created_at
    FROM credit_transactions ct
    LEFT JOIN users u ON u.id = ct.user_id
    WHERE ct.reason = 'manual_adjustment'
  ";

  $params = [];
  if ($userId > 0) {
    $sql .= " AND ct.user_id = :uid ";
    $params[':uid'] = $userId;
  }

  $sql .= " ORDER BY ct.id DESC LIMIT {$limit} ";

  $stmt = $pdo->prepare($sql);
  $stmt->execute($params);
  $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

  // Collect all "by" user IDs from metadata
  $byIds = [];
  $metas = [];

  foreach ($rows as $r) {
    $metaRaw = (string)($r['meta'] ?? '');
    $meta = json_decode($metaRaw, true);
    if (!is_array($meta)) $meta = [];
    $metas[] = $meta;

    $byId = (int)($meta['by'] ?? 0);
    if ($byId > 0) $byIds[$byId] = true;
  }

  $byNames = [];
  if (!empty($byIds)) {
    $ids = array_keys($byIds);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $st2 = $pdo->prepare("SELECT id, full_name FROM users WHERE id IN ($placeholders)");
    $st2->execute($ids);
    while ($row = $st2->fetch(PDO::FETCH_ASSOC)) {
      $byNames[(int)$row['id']] = (string)$row['full_name'];
    }
  }

  $items = [];
  foreach ($rows as $i => $r) {
    $meta = $metas[$i] ?? [];
    $byId = (int)($meta['by'] ?? 0);
    $note = (string)($meta['note'] ?? '');

    $items[] = [
      'id' => (int)($r['id'] ?? 0),
      'user_id' => (int)($r['user_id'] ?? 0),
      'user_email' => (string)($r['user_email'] ?? ''),
      'amount' => isset($r['amount']) ? (float)$r['amount'] : 0.0,
      'note' => $note,
      'by_name' => $byId > 0 ? ($byNames[$byId] ?? '') : '',
      'created_at' => (string)($r['created_at'] ?? ''),
    ];
  }

  echo json_encode(['ok'=>true,'items'=>$items]);
} catch (Throwable $e) {
  http_response_code(400);
  echo json_encode(['ok'=>false,'error'=>'Accès refusé / erreur', 'details'=>$e->getMessage()]);
}
