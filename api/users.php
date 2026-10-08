<?php
require_once '../config/db.php';

$pdo = getDB();
$method = $_SERVER['REQUEST_METHOD'];
$id = $_GET['id'] ?? null;



if ($method === 'GET') {
    $d = get_request_data();
    $actor = getActorInfo($d);
    if (!isAdminRole($actor['actor_role'])) {
        json_response(['error' => 'Accès refusé'], 403);
    }

    if ($id) {
        $stmt = $pdo->prepare("
            SELECT u.*, r.nom AS role_name
            FROM users u
            LEFT JOIN roles r ON r.id = u.role_id
            WHERE u.id = ?
        ");
        $stmt->execute([(int)$id]);
        $user = $stmt->fetch();
        json_response($user ?: ['error' => 'Utilisateur introuvable'], $user ? 200 : 404);
    }

    $stmt = $pdo->query("
        SELECT u.*, r.nom AS role_name
        FROM users u
        LEFT JOIN roles r ON r.id = u.role_id
        ORDER BY u.id
    ");
    json_response($stmt->fetchAll());
}

if ($method === 'POST') {
    $d = get_request_data();
    $actor = getActorInfo($d);
    if (!isAdminRole($actor['actor_role'])) {
        json_response(['error' => 'Accès refusé'], 403);
    }

    $username = trim((string)($d['username'] ?? ''));
    $nom = trim((string)($d['nom'] ?? ''));
    $prenom = trim((string)($d['prenom'] ?? ''));
    if ($username === '' || $nom === '' || $prenom === '') {
        json_response(['error' => 'Nom d’utilisateur, nom et prénom obligatoires'], 400);
    }

    $password = (string)($d['mot_de_passe'] ?? 'changeme');
    $passwordHash = password_hash($password, PASSWORD_DEFAULT);

    $stmt = $pdo->prepare("
        INSERT INTO users (username, nom, prenom, email, password_hash, role_id, is_active)
        VALUES (:username, :nom, :prenom, :email, :password_hash, :role_id, :is_active)
        RETURNING id
    ");
    $stmt->execute([
        ':username' => strtolower($username),
        ':nom' => strtoupper($nom),
        ':prenom' => $prenom,
        ':email' => trim((string)($d['email'] ?? '')),
        ':password_hash' => $passwordHash,
        ':role_id' => !empty($d['role_id']) ? (int)$d['role_id'] : null,
        ':is_active' => boolValue($d['is_active'] ?? true),
    ]);
    $userId = (int)$stmt->fetchColumn();
    logActivity($pdo, $actor['actor_name'], $actor['actor_role'], 'Création utilisateur', 'Utilisateur ' . $username);
    json_response(['id' => $userId, 'message' => 'Utilisateur créé'], 201);
}

if ($method === 'PUT') {
    if (!$id) {
        json_response(['error' => 'ID requis'], 400);
    }
    $d = get_request_data();
    $actor = getActorInfo($d);
    $actorId = (int)($d['actor_id'] ?? $d['actorId'] ?? 0);
    $isSelfUpdate = $actorId > 0 && (int)$id === $actorId;

    if (!$isSelfUpdate && !isAdminRole($actor['actor_role'])) {
        json_response(['error' => 'Accès refusé'], 403);
    }

    $exists = $pdo->prepare('SELECT 1 FROM users WHERE id = ?');
    $exists->execute([(int)$id]);
    if (!$exists->fetchColumn()) {
        json_response(['error' => 'Utilisateur introuvable'], 404);
    }

    $username = strtolower(trim((string)($d['username'] ?? '')));
    $nom = strtoupper(trim((string)($d['nom'] ?? '')));
    $prenom = trim((string)($d['prenom'] ?? ''));

    if ($username === '' || $nom === '' || $prenom === '') {
        json_response(['error' => 'Nom d’utilisateur, nom et prénom obligatoires'], 400);
    }

    if ($isSelfUpdate) {
        $currentPassword = (string)($d['current_password'] ?? '');
        if ($currentPassword === '') {
            json_response(['error' => 'Le mot de passe actuel est requis'], 400);
        }
        $stmt = $pdo->prepare('SELECT password_hash FROM users WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $actorId]);
        $currentUser = $stmt->fetch();
        if (!$currentUser || !password_verify($currentPassword, $currentUser['password_hash'])) {
            json_response(['error' => 'Mot de passe actuel incorrect'], 401);
        }
    }

    $fields = [
        'username' => $username,
        'nom' => $nom,
        'prenom' => $prenom,
        'email' => trim((string)($d['email'] ?? '')),
    ];

    if (isAdminRole($actor['actor_role']) && !$isSelfUpdate) {
        $fields['role_id'] = !empty($d['role_id']) ? (int)$d['role_id'] : null;
        $fields['is_active'] = boolValue($d['is_active'] ?? true);
    }

    if (!empty($d['mot_de_passe'])) {
        $fields['password_hash'] = password_hash((string)$d['mot_de_passe'], PASSWORD_DEFAULT);
    }

    $sets = implode(', ', array_map(fn($f) => "$f = :$f", array_keys($fields)));
    $params = $fields;
    $params['id'] = (int)$id;
    $stmt = $pdo->prepare("UPDATE users SET $sets, updated_at = NOW() WHERE id = :id");
    $stmt->execute($params);
    logActivity($pdo, $actor['actor_name'], $actor['actor_role'], 'Modification utilisateur', 'Utilisateur #' . $id);
    json_response(['message' => 'Utilisateur mis à jour']);
}

if ($method === 'DELETE') {
    if (!$id) {
        json_response(['error' => 'ID requis'], 400);
    }
    $d = get_request_data();
    $actor = getActorInfo($d);
    if (!isAdminRole($actor['actor_role'])) {
        json_response(['error' => 'Accès refusé'], 403);
    }
    $exists = $pdo->prepare('SELECT 1 FROM users WHERE id = ?');
    $exists->execute([(int)$id]);
    if (!$exists->fetchColumn()) {
        json_response(['error' => 'Utilisateur introuvable'], 404);
    }
    $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([(int)$id]);
    logActivity($pdo, $actor['actor_name'], $actor['actor_role'], 'Suppression utilisateur', 'Utilisateur #' . $id);
    json_response(['message' => 'Utilisateur supprimé']);
}

json_response(['error' => 'Méthode non autorisée'], 405);
