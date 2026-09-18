<?php
require_once '../config/db.php';

$pdo = getDB();

$stats = [
    'total_fiches'   => (int)$pdo->query("SELECT COUNT(*) FROM echocardiographies")->fetchColumn(),
    'total_patients' => (int)$pdo->query("SELECT COUNT(*) FROM patients")->fetchColumn(),
    'fiches_mois'    => (int)$pdo->query("
        SELECT COUNT(*) FROM echocardiographies
        WHERE date_examen >= date_trunc('month', CURRENT_DATE)
    ")->fetchColumn(),
    'fe_moyenne'     => round((float)$pdo->query("SELECT AVG(fe) FROM echocardiographies WHERE fe IS NOT NULL")->fetchColumn(), 1),
    'par_mois'       => $pdo->query("
        SELECT TO_CHAR(date_examen, 'YYYY-MM') AS mois, COUNT(*) AS nb
        FROM echocardiographies
        WHERE date_examen >= CURRENT_DATE - INTERVAL '6 months'
        GROUP BY mois ORDER BY mois
    ")->fetchAll(),
    'par_sexe'       => $pdo->query("
        SELECT sexe, COUNT(*) AS nb
        FROM patients
        WHERE sexe IS NOT NULL
        GROUP BY sexe
    ")->fetchAll(),
    'dernieres_fiches' => $pdo->query("
        SELECT e.id, e.date_examen, e.fe, e.indication,
               p.nom, p.prenom, p.num_dossier
        FROM echocardiographies e
        JOIN patients p ON p.id = e.patient_id
        ORDER BY e.created_at DESC
        LIMIT 8
    ")->fetchAll(),
];

json_response($stats);
