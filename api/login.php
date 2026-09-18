<?php
require_once '../config/db.php';

$pdo = getDB();
$method = $_SERVER['REQUEST_METHOD'];

if ($method !== 'POST') {
    json_response(['error' => 'Méthode non autorisée'], 405);
}

$data = get_input();
$username = trim((string)($data['username'] ?? ''));
$password = (string)($data['password'] ?? '');

if ($username === '' || $password === '') {
    json_response(['error' => 'Nom d’utilisateur et mot de passe requis'], 400);
}

$stmt = $pdo->prepare("SELECT u.id, u.username, u.nom, u.prenom, u.password_hash, r.nom AS role_name, r.can_manage_users, r.can_manage_fiches, r.can_view_logs, r.can_create_fiches, r.can_update_fiches, r.can_delete_fiches, r.can_view_fiches FROM users u LEFT JOIN roles r ON r.id = u.role_id WHERE u.username = :username AND u.is_active = TRUE LIMIT 1");
$stmt->execute([':username' => strtolower($username)]);
$user = $stmt->fetch();

if (!$user || !password_verify($password, $user['password_hash'])) {
    json_response(['error' => 'Identifiants invalides'], 401);
}

json_response([
    'id' => (int)$user['id'],
    'username' => $user['username'],
    'nom' => $user['nom'],
    'prenom' => $user['prenom'],
    'role' => $user['role_name'],
    'permissions' => [
        'can_manage_users' => (bool)$user['can_manage_users'],
        'can_manage_fiches' => (bool)$user['can_manage_fiches'],
        'can_view_logs' => (bool)$user['can_view_logs'],
        'can_create_fiches' => (bool)$user['can_create_fiches'],
        'can_update_fiches' => (bool)$user['can_update_fiches'],
        'can_delete_fiches' => (bool)$user['can_delete_fiches'],
        'can_view_fiches' => (bool)$user['can_view_fiches'],
    ]
]);