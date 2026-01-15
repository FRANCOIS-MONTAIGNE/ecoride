<?php
// backend/public/api/trips/search.php

declare(strict_types=1);

require_once __DIR__ . '/../../src/DB.php';
require_once __DIR__ . '/../../src/Auth.php';

use App\Security\Auth;
use App\DB\DB;

Auth::cors(); // Autoriser le CORS
header('Content-Type: application/json; charset=utf-8');

// Vérification de la méthode HTTP utilisée (POST attendu)
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'message' => 'Méthode non autorisée']);
    exit;
}

// Récupération des données JSON envoyées en POST
$raw  = file_get_contents('php://input');
$data = json_decode($raw, true) ?: [];

// Extraction des critères de recherche
$origin = trim($data['origin_city'] ?? '');
$dest   = trim($data['dest_city'] ?? '');
$date   = trim($data['date'] ?? ''); // "YYYY-MM-DD"

try {
    $pdo = DB::pdo();
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'Erreur de connexion BDD']);
    exit;
}

// Construction de la requête SQL avec les filtres
$sql = "SELECT 
          id,
          origin_city,
          dest_city,
          price,
          available_seats,
          departure_datetime
        FROM trips
        WHERE is_canceled = 0";

$params = [];

// Filtres
if ($origin !== '') {
    $sql .= " AND origin_city LIKE :origin";
    $params[':origin'] = '%' . $origin . '%';
}

if ($dest !== '') {
    $sql .= " AND dest_city LIKE :dest";
    $params[':dest'] = '%' . $dest . '%';
}

if ($date !== '') {
    $sql .= " AND DATE(departure_datetime) = :d";
    $params[':d'] = $date;
}

$sql .= " ORDER BY departure_datetime ASC LIMIT 100";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $trips = [];
    foreach ($rows as $r) {
        $trips[] = [
            'id'          => (int)$r['id'],
            'origin_city' => $r['origin_city'],
            'dest_city'   => $r['dest_city'],
            'price'       => (float)$r['price'],
            'seats'       => (int)$r['available_seats'],
            'date'        => date('c', strtotime($r['departure_datetime'])),
            'driver'      => null,
            'eco'         => false,
        ];
    }

    echo json_encode(['ok' => true, 'trips' => $trips]);
} catch (Throwable $e) {
    http_response_code(500);
    // Pour le développement, on affiche le message d’erreur SQL
    echo json_encode([
  'ok'    => false,
  'error' => 'Erreur serveur'
]);
}
