<?php
require_once __DIR__ . '/../config/db.php';

$pdo = getDB();
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