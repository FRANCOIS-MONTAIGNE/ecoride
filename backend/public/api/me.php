<?php
declare(strict_types=1);

require_once __DIR__ . '/../../src/DB.php';
require_once __DIR__ . '/../../src/Auth.php';

use App\DB\DB;
use App\Security\Auth;

header('Content-Type: application/json; charset=utf-8');

try {
    $pdo  = DB::pdo();
    $user = Auth::requireLogin($pdo, null); // Récupère l'utilisateur connecté ou renvoie une erreur 401

    echo json_encode([
        'ok'   => true,
        'user' => $user
    ]);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'ok'      => false,
        'message' => 'Erreur serveur',
        'details' => $e->getMessage()
    ]);
}
