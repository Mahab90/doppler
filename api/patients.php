<?php
require_once __DIR__ . '/../config/db.php';

$pdo = getDB();
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $page = max(1, (int)($_GET['page'] ?? 1));
    $limit = 20;
    $offset = ($page - 1) * $limit;
    $query = trim((string)($_GET['q'] ?? ''));

    $where = '';
    $params = [];
    if ($query !== '') {
        $where = "WHERE p.nom ILIKE :query
                  OR p.prenom ILIKE :query
                  OR COALESCE(p.num_dossier, '') ILIKE :query
                  OR COALESCE(p.telephone, '') ILIKE :query";
        $params[':query'] = '%' . $query . '%';
    }

    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM patients p $where");
    $countStmt->execute($params);
    $total = (int)$countStmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT p.id, p.nom, p.prenom, p.age, p.sexe, p.telephone,
                                  p.num_dossier, p.service, p.created_at,
                                  COUNT(e.id) AS fiches_count
                           FROM patients p
                           LEFT JOIN echocardiographies e ON e.patient_id = p.id
                           $where
                           GROUP BY p.id
                           ORDER BY p.nom, p.prenom
                           LIMIT :limit OFFSET :offset");
    foreach ($params as $name => $value) {
        $stmt->bindValue($name, $value, PDO::PARAM_STR);
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();

    json_response(['data' => $stmt->fetchAll(), 'total' => $total, 'page' => $page]);
}

if ($method === 'POST') {
    $d = get_input();
    
    // Authorization check
    $actor = getActorInfo($d);
    // (Assuming any user who can view can create patients, or we use a basic check.
    // If not specified in roles, we just allow authenticated users.
    if (!isset($actor['actor_id'])) {
        json_response(['error' => 'Non autorisé'], 401);
    }

    if (empty($d['patient_nom'])) {
        json_response(['error' => 'Le nom du patient est requis'], 400);
    }

    $stmt = $pdo->prepare("
        INSERT INTO patients (nom, prenom, date_naissance, sexe, poids, taille, telephone, num_dossier, service, age)
        VALUES (:nom, :prenom, :date_naissance, :sexe, :poids, :taille, :telephone, :num_dossier, :service, :age) RETURNING id
    ");
    $stmt->execute([
        ':nom' => strtoupper($d['patient_nom']),
        ':prenom' => $d['patient_prenom'] ?? '',
        ':date_naissance' => !empty($d['date_naissance']) ? $d['date_naissance'] : null,
        ':sexe' => !empty($d['sexe']) ? $d['sexe'] : null,
        ':poids' => !empty($d['poids']) ? (float)$d['poids'] : null,
        ':taille' => !empty($d['taille']) ? (float)$d['taille'] : null,
        ':telephone' => !empty($d['telephone']) ? $d['telephone'] : null,
        ':num_dossier' => !empty($d['num_dossier']) ? $d['num_dossier'] : null,
        ':service' => !empty($d['service']) ? $d['service'] : null,
        ':age' => !empty($d['age']) ? (int)$d['age'] : null,
    ]);
    $patient_id = (int)$stmt->fetchColumn();

    // Log the action
    logAction($pdo, $actor['actor_name'] ?? 'System', $actor['actor_role'] ?? '', 'CREATE_PATIENT', "Création du patient ID $patient_id");

    json_response(['success' => true, 'id' => $patient_id, 'message' => 'Patient créé avec succès']);
}