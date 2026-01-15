<?php
// backend/public/api/refresh.php
require_once __DIR__ . '/../../src/Auth.php';

Auth::cors(true); // cookie HttpOnly
header('Content-Type: application/json; charset=utf-8');
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') exit;
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
  http_response_code(405); echo json_encode(['ok'=>false,'error'=>'Méthode non autorisée']); exit;
}

$cookie = $_COOKIE['refresh_token'] ?? '';
if ($cookie === '') { http_response_code(401); echo json_encode(['ok'=>false,'error'=>'No refresh']); exit; }

try {
  $claims = Auth::decode($cookie);
  if (($claims['type'] ?? '') !== 'refresh') throw new Exception('Not refresh');

  $access = Auth::issueAccessToken((int)$claims['sub'], $claims['email'] ?? '');
  echo json_encode(['ok'=>true, 'access_token'=>$access]);
} catch (Throwable $e) {
  http_response_code(401); echo json_encode(['ok'=>false,'error'=>'Invalid refresh']);
}
