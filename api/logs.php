<?php
require_once '../config/db.php';

$pdo = getDB();
$method = $_SERVER['REQUEST_METHOD'];

if ($method !== 'GET') {
    json_response(['error' => 'Méthode non autorisée'], 405);
}

$d = get_request_data();
$actor = getActorInfo($d);
if (!isAdminRole($actor['actor_role'])) {
    json_response(['error' => 'Accès refusé'], 403);
}

$limit = max(1, min(200, (int)($d['limit'] ?? 200)));
$stmt = $pdo->prepare('SELECT * FROM activity_logs ORDER BY created_at DESC LIMIT :limit');
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->execute();
json_response($stmt->fetchAll());
