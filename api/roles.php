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
        $stmt = $pdo->prepare('SELECT * FROM roles WHERE id = ?');
        $stmt->execute([(int)$id]);
        $role = $stmt->fetch();
        json_response($role ?: ['error' => 'Rôle introuvable'], $role ? 200 : 404);
    }

    $stmt = $pdo->query('SELECT * FROM roles ORDER BY id');
    json_response($stmt->fetchAll());
}

if ($method === 'POST') {
    $d = get_request_data();
    $actor = getActorInfo($d);
    if (!isAdminRole($actor['actor_role'])) {
        json_response(['error' => 'Accès refusé'], 403);
    }

    $nom = trim((string)($d['nom'] ?? ''));
    if ($nom === '') {
        json_response(['error' => 'Nom du rôle requis'], 400);
    }

    $stmt = $pdo->prepare('INSERT INTO roles (nom, description, can_manage_users, can_manage_fiches, can_view_logs, can_create_fiches, can_update_fiches, can_delete_fiches, can_view_fiches, can_manage_patients) VALUES (:nom, :description, :can_manage_users, :can_manage_fiches, :can_view_logs, :can_create_fiches, :can_update_fiches, :can_delete_fiches, :can_view_fiches, :can_manage_patients) RETURNING id');
    $stmt->execute([
        ':nom' => strtolower($nom),
        ':description' => trim((string)($d['description'] ?? '')),
        ':can_manage_users' => boolValue($d['can_manage_users'] ?? false),
        ':can_manage_fiches' => boolValue($d['can_manage_fiches'] ?? false),
        ':can_view_logs' => boolValue($d['can_view_logs'] ?? false),
        ':can_create_fiches' => boolValue($d['can_create_fiches'] ?? false),
        ':can_update_fiches' => boolValue($d['can_update_fiches'] ?? false),
        ':can_delete_fiches' => boolValue($d['can_delete_fiches'] ?? false),
        ':can_view_fiches' => boolValue($d['can_view_fiches'] ?? true),
        ':can_manage_patients' => isAdminRole($nom) || boolValue($d['can_manage_patients'] ?? false),
    ]);
    $roleId = (int)$stmt->fetchColumn();
    logActivity($pdo, $actor['actor_name'], $actor['actor_role'], 'Création de rôle', 'Rôle ' . strtolower($nom));
    json_response(['id' => $roleId, 'message' => 'Rôle créé'], 201);
}

if ($method === 'PUT') {
    if (!$id) {
        json_response(['error' => 'ID requis'], 400);
    }
    $d = get_request_data();
    $actor = getActorInfo($d);
    if (!isAdminRole($actor['actor_role'])) {
        json_response(['error' => 'Accès refusé'], 403);
    }

    $nom = strtolower(trim((string)($d['nom'] ?? '')));
    if ($nom === '') {
        json_response(['error' => 'Nom du rôle requis'], 400);
    }
    $exists = $pdo->prepare('SELECT 1 FROM roles WHERE id = ?');
    $exists->execute([(int)$id]);
    if (!$exists->fetchColumn()) {
        json_response(['error' => 'Rôle introuvable'], 404);
    }

    $fields = [
        'nom' => $nom,
        'description' => trim((string)($d['description'] ?? '')),
        'can_manage_users' => boolValue($d['can_manage_users'] ?? false),
        'can_manage_fiches' => boolValue($d['can_manage_fiches'] ?? false),
        'can_view_logs' => boolValue($d['can_view_logs'] ?? false),
        'can_create_fiches' => boolValue($d['can_create_fiches'] ?? false),
        'can_update_fiches' => boolValue($d['can_update_fiches'] ?? false),
        'can_delete_fiches' => boolValue($d['can_delete_fiches'] ?? false),
        'can_view_fiches' => boolValue($d['can_view_fiches'] ?? true),
        'can_manage_patients' => isAdminRole($nom) || boolValue($d['can_manage_patients'] ?? false),
    ];

    $sets = implode(', ', array_map(fn($f) => "$f = :$f", array_keys($fields)));
    $params = $fields;
    $params['id'] = (int)$id;
    $stmt = $pdo->prepare("UPDATE roles SET $sets WHERE id = :id");
    $stmt->execute($params);
    logActivity($pdo, $actor['actor_name'], $actor['actor_role'], 'Modification de rôle', 'Rôle #' . $id);
    json_response(['message' => 'Rôle mis à jour']);
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
    $roleStmt = $pdo->prepare('SELECT nom FROM roles WHERE id = ?');
    $roleStmt->execute([(int)$id]);
    $roleName = $roleStmt->fetchColumn();
    if ($roleName === false) {
        json_response(['error' => 'Rôle introuvable'], 404);
    }
    if (strtolower($roleName) === 'admin') {
        json_response(['error' => 'Le rôle admin ne peut pas être supprimé'], 409);
    }
    $assignedStmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE role_id = ?');
    $assignedStmt->execute([(int)$id]);
    if ((int)$assignedStmt->fetchColumn() > 0) {
        json_response(['error' => 'Ce rôle est encore attribué à un utilisateur'], 409);
    }
    $pdo->prepare('DELETE FROM roles WHERE id = ?')->execute([(int)$id]);
    logActivity($pdo, $actor['actor_name'], $actor['actor_role'], 'Suppression de rôle', 'Rôle #' . $id);
    json_response(['message' => 'Rôle supprimé']);
}

json_response(['error' => 'Méthode non autorisée'], 405);
