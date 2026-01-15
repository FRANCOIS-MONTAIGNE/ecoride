<?php
// backend/public/api/admin_users.php
declare(strict_types=1);

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

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
  echo json_encode(['ok'=>false,'error'=>'Méthode non autorisée'], JSON_UNESCAPED_UNICODE);
  exit;
}

try {
  $pdo = DB::getConnection();

  // 🔐 Admin uniquement
  $admin = Auth::requireLogin($pdo, ['admin']);

  // Filtres
  $q        = trim((string)($_GET['q'] ?? ''));
  $roleFilt = trim((string)($_GET['role'] ?? '')); // admin / employee / user
  $limit    = (int)($_GET['limit'] ?? 100);
  $limit    = max(1, min($limit, 500));

  $where  = [];
  $params = [];

  if ($q !== '') {
    $where[] = "(u.full_name LIKE :q OR u.email LIKE :q)";
    $params[':q'] = "%{$q}%";
  }

  if ($roleFilt !== '') {
    $where[] = "LOWER(u.role) = :role";
    $params[':role'] = strtolower($roleFilt);
  }

  $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

  $sql = "
    SELECT u.id, u.full_name, u.email, u.rating, u.role, u.created_at
    FROM users u
    {$whereSql}
    ORDER BY u.created_at DESC
    LIMIT {$limit}
  ";

  $stmt = $pdo->prepare($sql);
  $stmt->execute($params);
  $users = $stmt->fetchAll(\PDO::FETCH_ASSOC);

  echo json_encode([
    'ok'    => true,
    'admin' => [
      'id'    => (int)($admin['id'] ?? 0),
      'email' => (string)($admin['email'] ?? ''),
      'role'  => (string)($admin['role'] ?? 'admin'),
    ],
    'count' => count($users),
    'users' => $users,
  ], JSON_UNESCAPED_UNICODE);

} catch (\Throwable $e) {
  http_response_code(500);
  echo json_encode([
    'ok'      => false,
    'message' => 'Erreur serveur',
    'details' => $e->getMessage(),
  ], JSON_UNESCAPED_UNICODE);
}
