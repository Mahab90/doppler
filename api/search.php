<?php
require_once '../config/db.php';

$pdo = getDB();
$where = ['1=1'];
$params = [];

if (!empty($_GET['q'])) {
    $q = '%' . trim($_GET['q']) . '%';
    $where[] = "(p.nom ILIKE :q OR p.prenom ILIKE :q OR CONCAT(p.nom, ' ', p.prenom) ILIKE :q OR p.num_dossier ILIKE :q OR p.telephone ILIKE :q OR e.indication ILIKE :q OR e.conclusion ILIKE :q OR e.echographiste ILIKE :q)";
    $params['q'] = $q;
}

if (!empty($_GET['nom'])) {
    $where[] = "(p.nom ILIKE :nom OR p.prenom ILIKE :nom OR CONCAT(p.nom, ' ', p.prenom) ILIKE :nom)";
    $params['nom'] = '%' . trim($_GET['nom']) . '%';
}
if (!empty($_GET['date_debut'])) {
    $where[] = "e.date_examen >= :date_debut";
    $params['date_debut'] = $_GET['date_debut'];
}
if (!empty($_GET['date_fin'])) {
    $where[] = "e.date_examen <= :date_fin";
    $params['date_fin'] = $_GET['date_fin'];
}
if (!empty($_GET['sexe'])) {
    $where[] = "p.sexe = :sexe";
    $params['sexe'] = $_GET['sexe'];
}
if (!empty($_GET['num_dossier'])) {
    $where[] = "p.num_dossier ILIKE :num_dossier";
    $params['num_dossier'] = '%' . trim($_GET['num_dossier']) . '%';
}
if (!empty($_GET['telephone'])) {
    $where[] = "p.telephone ILIKE :telephone";
    $params['telephone'] = '%' . trim($_GET['telephone']) . '%';
}
if (!empty($_GET['indication'])) {
    $where[] = "e.indication ILIKE :indication";
    $params['indication'] = '%' . trim($_GET['indication']) . '%';
}

$sql = "
    SELECT e.id, e.date_examen, e.fe, e.conclusion, e.indication,
           p.nom, p.prenom, p.sexe, p.date_naissance, p.age,
           p.telephone, p.num_dossier,
           m.nom AS medecin_nom
    FROM echocardiographies e
    JOIN patients p ON p.id = e.patient_id
    LEFT JOIN medecins m ON m.id = e.medecin_id
    WHERE " . implode(' AND ', $where) . "
    ORDER BY e.date_examen DESC, e.id DESC
    LIMIT 200
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
json_response($stmt->fetchAll());
