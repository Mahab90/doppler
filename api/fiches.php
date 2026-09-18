<?php
require_once __DIR__ . '/../config/db.php';

$method = $_SERVER['REQUEST_METHOD'];
$id = $_GET['id'] ?? null;
$pdo = getDB();

// GET — liste ou fiche unique
if ($method === 'GET') {
    if ($id) {
        $stmt = $pdo->prepare("
            SELECT e.*, 
                   p.nom AS patient_nom, p.prenom AS patient_prenom,
                   p.date_naissance, p.sexe, p.poids, p.taille, p.service,
                   m.nom AS medecin_nom, m.prenom AS medecin_prenom
            FROM echocardiographies e
            JOIN patients p ON p.id = e.patient_id
            LEFT JOIN medecins m ON m.id = e.medecin_id
            WHERE e.id = ?
        ");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if (!$row) json_response(['error' => 'Fiche introuvable'], 404);
        json_response($row);
    }
    // Liste paginée
    $page = max(1, (int)($_GET['page'] ?? 1));
    $limit = 15;
    $offset = ($page - 1) * $limit;
    $stmt = $pdo->query("
        SELECT e.id, e.date_examen, e.fe, e.conclusion,
               p.nom, p.prenom, p.sexe
        FROM echocardiographies e
        JOIN patients p ON p.id = e.patient_id
        ORDER BY e.date_examen DESC
        LIMIT $limit OFFSET $offset
    ");
    $total = $pdo->query("SELECT COUNT(*) FROM echocardiographies")->fetchColumn();
    json_response(['data' => $stmt->fetchAll(), 'total' => (int)$total, 'page' => $page]);
}

// POST — créer
if ($method === 'POST') {
    $d = get_input();
    $actor = getActorInfo($d);
    
    if (!canPerformFicheAction($pdo, $actor['actor_role'], 'create')) {
        json_response(['error' => 'Accès refusé pour la création'], 403);
    }
    
    // Créer patient si nouveau
    $patient_id = $d['patient_id'] ?? null;
    if (!$patient_id && !empty($d['patient_nom'])) {
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
    }
    
    // Trouver le médecin par son nom d'échographiste si existant
    $medecin_id = null;
    if (!empty($d['echographiste'])) {
        $mStmt = $pdo->prepare("SELECT id FROM medecins WHERE nom ILIKE ? OR ? ILIKE CONCAT('%', nom, '%') LIMIT 1");
        $mStmt->execute([$d['echographiste'], $d['echographiste']]);
        $medecin_id = $mStmt->fetchColumn() ?: null;
    }
    
    $stmt = $pdo->prepare("
        INSERT INTO echocardiographies (
            patient_id, medecin_id, date_examen, indication, appareil, medecin_demandeur, echographiste, surface_corporelle,
            fenetre, fenetre_mode,
            vo_dia, siv, dts, dtd, pp, ao, og, fe, fr,
            cinetique_globale, vg_obs, vd_obs, aorte_obs, paroi_post_obs, siv_obs, og_obs, s_og, od_obs, s_od, taps, vci_obs, vci_diametre,
            valve_mitrale, valve_aortique, valve_pulmonaire, valve_tricuspide, pericarde,
            doppler_mitrale, doppler_tricuspide, doppler_pulmonaire, doppler_aortique,
            doppler_tricuspide_vmax, paps, doppler_pulmonaire_vmax, doppler_aortique_vmax,
            doppler_sor, doppler_vor, doppler_ea, commentaire, conclusion
        ) VALUES (
            :patient_id, :medecin_id, :date_examen, :indication, :appareil, :medecin_demandeur, :echographiste, :surface_corporelle,
            :fenetre, :fenetre_mode,
            :vo_dia, :siv, :dts, :dtd, :pp, :ao, :og, :fe, :fr,
            :cinetique_globale, :vg_obs, :vd_obs, :aorte_obs, :paroi_post_obs, :siv_obs, :og_obs, :s_og, :od_obs, :s_od, :taps, :vci_obs, :vci_diametre,
            :valve_mitrale, :valve_aortique, :valve_pulmonaire, :valve_tricuspide, :pericarde,
            :doppler_mitrale, :doppler_tricuspide, :doppler_pulmonaire, :doppler_aortique,
            :doppler_tricuspide_vmax, :paps, :doppler_pulmonaire_vmax, :doppler_aortique_vmax,
            :doppler_sor, :doppler_vor, :doppler_ea, :commentaire, :conclusion
        ) RETURNING id
    ");
    
    $params = [
        ':patient_id' => $patient_id,
        ':medecin_id' => $medecin_id,
        ':date_examen' => !empty($d['date_examen']) ? $d['date_examen'] : date('Y-m-d'),
        ':indication' => !empty($d['indication']) ? $d['indication'] : null,
        ':appareil' => !empty($d['appareil']) ? $d['appareil'] : null,
        ':medecin_demandeur' => !empty($d['medecin_demandeur']) ? $d['medecin_demandeur'] : null,
        ':echographiste' => !empty($d['echographiste']) ? $d['echographiste'] : null,
        ':surface_corporelle' => !empty($d['surface_corporelle']) ? (float)$d['surface_corporelle'] : null,
        ':fenetre' => !empty($d['fenetre']) ? $d['fenetre'] : null,
        ':fenetre_mode' => !empty($d['fenetre_mode']) ? $d['fenetre_mode'] : null,
        ':vo_dia' => !empty($d['vo_dia']) ? (float)$d['vo_dia'] : null,
        ':siv' => !empty($d['siv']) ? (float)$d['siv'] : null,
        ':dts' => !empty($d['dts']) ? (float)$d['dts'] : null,
        ':dtd' => !empty($d['dtd']) ? (float)$d['dtd'] : null,
        ':pp' => !empty($d['pp']) ? (float)$d['pp'] : null,
        ':ao' => !empty($d['ao']) ? (float)$d['ao'] : null,
        ':og' => !empty($d['og']) ? (float)$d['og'] : null,
        ':fe' => !empty($d['fe']) ? (float)$d['fe'] : null,
        ':fr' => !empty($d['fr']) ? (float)$d['fr'] : null,
        ':cinetique_globale' => !empty($d['cinetique_globale']) ? $d['cinetique_globale'] : null,
        ':vg_obs' => !empty($d['vg_obs']) ? $d['vg_obs'] : null,
        ':vd_obs' => !empty($d['vd_obs']) ? $d['vd_obs'] : null,
        ':aorte_obs' => !empty($d['aorte_obs']) ? $d['aorte_obs'] : null,
        ':paroi_post_obs' => !empty($d['paroi_post_obs']) ? $d['paroi_post_obs'] : null,
        ':siv_obs' => !empty($d['siv_obs']) ? $d['siv_obs'] : null,
        ':og_obs' => !empty($d['og_obs']) ? $d['og_obs'] : null,
        ':s_og' => !empty($d['s_og']) ? (float)$d['s_og'] : null,
        ':od_obs' => !empty($d['od_obs']) ? $d['od_obs'] : null,
        ':s_od' => !empty($d['s_od']) ? (float)$d['s_od'] : null,
        ':taps' => !empty($d['taps']) ? (float)$d['taps'] : null,
        ':vci_obs' => !empty($d['vci_obs']) ? $d['vci_obs'] : null,
        ':vci_diametre' => !empty($d['vci_diametre']) ? (float)$d['vci_diametre'] : null,
        ':valve_mitrale' => !empty($d['valve_mitrale']) ? $d['valve_mitrale'] : null,
        ':valve_aortique' => !empty($d['valve_aortique']) ? $d['valve_aortique'] : null,
        ':valve_pulmonaire' => !empty($d['valve_pulmonaire']) ? $d['valve_pulmonaire'] : null,
        ':valve_tricuspide' => !empty($d['valve_tricuspide']) ? $d['valve_tricuspide'] : null,
        ':pericarde' => !empty($d['pericarde']) ? $d['pericarde'] : null,
        ':doppler_mitrale' => !empty($d['doppler_mitrale']) ? $d['doppler_mitrale'] : null,
        ':doppler_tricuspide' => !empty($d['doppler_tricuspide']) ? $d['doppler_tricuspide'] : null,
        ':doppler_pulmonaire' => !empty($d['doppler_pulmonaire']) ? $d['doppler_pulmonaire'] : null,
        ':doppler_aortique' => !empty($d['doppler_aortique']) ? $d['doppler_aortique'] : null,
        ':doppler_tricuspide_vmax' => !empty($d['doppler_tricuspide_vmax']) ? (float)$d['doppler_tricuspide_vmax'] : null,
        ':paps' => !empty($d['paps']) ? (float)$d['paps'] : null,
        ':doppler_pulmonaire_vmax' => !empty($d['doppler_pulmonaire_vmax']) ? (float)$d['doppler_pulmonaire_vmax'] : null,
        ':doppler_aortique_vmax' => !empty($d['doppler_aortique_vmax']) ? (float)$d['doppler_aortique_vmax'] : null,
        ':doppler_sor' => !empty($d['doppler_sor']) ? (float)$d['doppler_sor'] : null,
        ':doppler_vor' => !empty($d['doppler_vor']) ? (float)$d['doppler_vor'] : null,
        ':doppler_ea' => !empty($d['doppler_ea']) ? (float)$d['doppler_ea'] : null,
        ':commentaire' => !empty($d['commentaire']) ? $d['commentaire'] : null,
        ':conclusion' => !empty($d['conclusion']) ? $d['conclusion'] : null,
    ];
    
    $stmt->execute($params);
    $ficheId = (int)$stmt->fetchColumn();
    
    logActivity($pdo, $actor['actor_name'], $actor['actor_role'], 'Création fiche', 'Fiche #' . $ficheId . ' pour le patient ' . strtoupper($d['patient_nom']));
    json_response(['id' => $ficheId, 'message' => 'Fiche créée'], 201);
}

// PUT — modifier
if ($method === 'PUT') {
    if (!$id) json_response(['error' => 'ID requis'], 400);
    $d = get_input();
    $actor = getActorInfo($d);
    
    if (!canPerformFicheAction($pdo, $actor['actor_role'], 'update')) {
        json_response(['error' => 'Accès refusé pour la modification'], 403);
    }
    
    // Récupérer le patient_id associé à cette fiche
    $stmt = $pdo->prepare("SELECT patient_id FROM echocardiographies WHERE id = ?");
    $stmt->execute([$id]);
    $patient_id = $stmt->fetchColumn();
    if (!$patient_id) {
        json_response(['error' => 'Fiche introuvable'], 404);
    }
    
    // Mettre à jour le patient
    if (!empty($d['patient_nom'])) {
        $stmt = $pdo->prepare("
            UPDATE patients 
            SET nom = :nom, prenom = :prenom, sexe = :sexe, age = :age, poids = :poids, taille = :taille, 
                telephone = :telephone, num_dossier = :num_dossier, service = :service
            WHERE id = :patient_id
        ");
        $stmt->execute([
            ':nom' => strtoupper($d['patient_nom']),
            ':prenom' => $d['patient_prenom'] ?? '',
            ':sexe' => !empty($d['sexe']) ? $d['sexe'] : null,
            ':age' => !empty($d['age']) ? (int)$d['age'] : null,
            ':poids' => !empty($d['poids']) ? (float)$d['poids'] : null,
            ':taille' => !empty($d['taille']) ? (float)$d['taille'] : null,
            ':telephone' => !empty($d['telephone']) ? $d['telephone'] : null,
            ':num_dossier' => !empty($d['num_dossier']) ? $d['num_dossier'] : null,
            ':service' => !empty($d['service']) ? $d['service'] : null,
            ':patient_id' => $patient_id
        ]);
    }
    
    // Trouver le médecin par son nom d'échographiste si existant
    $medecin_id = null;
    if (!empty($d['echographiste'])) {
        $mStmt = $pdo->prepare("SELECT id FROM medecins WHERE nom ILIKE ? OR ? ILIKE CONCAT('%', nom, '%') LIMIT 1");
        $mStmt->execute([$d['echographiste'], $d['echographiste']]);
        $medecin_id = $mStmt->fetchColumn() ?: null;
    }
    
    $fields = [
        'medecin_id', 'date_examen', 'indication', 'appareil', 'medecin_demandeur', 'echographiste', 'surface_corporelle', 'fenetre', 'fenetre_mode',
        'vo_dia', 'siv', 'dts', 'dtd', 'pp', 'ao', 'og', 'fe', 'fr',
        'cinetique_globale', 'vg_obs', 'vd_obs', 'aorte_obs', 'paroi_post_obs', 'siv_obs', 'og_obs', 's_og', 'od_obs', 's_od', 'taps', 'vci_obs', 'vci_diametre',
        'valve_mitrale', 'valve_aortique', 'valve_pulmonaire', 'valve_tricuspide', 'pericarde',
        'doppler_mitrale', 'doppler_tricuspide', 'doppler_pulmonaire', 'doppler_aortique',
        'doppler_tricuspide_vmax', 'paps', 'doppler_pulmonaire_vmax', 'doppler_aortique_vmax',
        'doppler_sor', 'doppler_vor', 'doppler_ea', 'commentaire', 'conclusion'
    ];
    
    $sets = implode(', ', array_map(fn($f) => "$f = :$f", $fields));
    $stmt = $pdo->prepare("UPDATE echocardiographies SET $sets, updated_at = NOW() WHERE id = :id");
    
    $params = [':id' => $id];
    foreach ($fields as $field) {
        if ($field === 'medecin_id') {
            $params[':medecin_id'] = $medecin_id;
        } elseif (in_array($field, ['surface_corporelle', 'vo_dia', 'siv', 'dts', 'dtd', 'pp', 'ao', 'og', 'fe', 'fr', 's_og', 's_od', 'taps', 'vci_diametre', 'doppler_tricuspide_vmax', 'paps', 'doppler_pulmonaire_vmax', 'doppler_aortique_vmax', 'doppler_sor', 'doppler_vor', 'doppler_ea'], true)) {
            $params[':' . $field] = isset($d[$field]) && $d[$field] !== '' ? (float)$d[$field] : null;
        } else {
            $params[':' . $field] = !empty($d[$field]) ? $d[$field] : null;
        }
    }
    
    $stmt->execute($params);
    logActivity($pdo, $actor['actor_name'], $actor['actor_role'], 'Modification fiche', 'Fiche #' . $id);
    json_response(['message' => 'Fiche mise à jour']);
}

// DELETE
if ($method === 'DELETE') {
    if (!$id) json_response(['error' => 'ID requis'], 400);
    $d = get_input();
    $actor = getActorInfo($d);
    
    if (!canPerformFicheAction($pdo, $actor['actor_role'], 'delete')) {
        json_response(['error' => 'Accès refusé pour la suppression'], 403);
    }
    
    $pdo->prepare("DELETE FROM echocardiographies WHERE id = ?")->execute([$id]);
    logActivity($pdo, $actor['actor_name'], $actor['actor_role'], 'Suppression fiche', 'Fiche #' . $id);
    json_response(['message' => 'Fiche supprimée']);
}