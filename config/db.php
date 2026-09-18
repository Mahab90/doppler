<?php
function getDB() {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = "pgsql:host=localhost;port=5432;dbname=doppler";
        $pdo = new PDO($dsn, 'postgres', 'admin', [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $pdo->exec('SET search_path TO public');
        ensureSchema($pdo);
    }
    return $pdo;
}

function columnExists(PDO $pdo, string $table, string $column): bool {
    $stmt = $pdo->prepare("SELECT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema = 'public' AND table_name = ? AND column_name = ?)");
    $stmt->execute([$table, $column]);
    return (bool)$stmt->fetchColumn();
}

function ensureColumn(PDO $pdo, string $table, string $definition): void {
    $parts = explode(' ', $definition, 2);
    if (count($parts) !== 2) {
        return;
    }
    [$column] = $parts;
    if (!columnExists($pdo, $table, $column)) {
        $pdo->exec("ALTER TABLE $table ADD COLUMN $definition");
    }
}

function ensureSchema(PDO $pdo): void {
    // Créer les tables médecales en premier
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS medecins (
            id SERIAL PRIMARY KEY,
            nom VARCHAR(100) NOT NULL,
            prenom VARCHAR(100) NOT NULL,
            specialite VARCHAR(100) DEFAULT 'Cardiologue',
            created_at TIMESTAMP DEFAULT NOW()
        )
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS patients (
            id SERIAL PRIMARY KEY,
            nom VARCHAR(100) NOT NULL,
            prenom VARCHAR(100) NOT NULL,
            date_naissance DATE,
            age INTEGER,
            sexe CHAR(1) CHECK (sexe IN ('M', 'F', NULL)),
            poids DECIMAL(5,2),
            taille DECIMAL(5,2),
            telephone VARCHAR(20),
            num_dossier VARCHAR(50),
            service VARCHAR(100),
            created_at TIMESTAMP DEFAULT NOW()
        )
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS echocardiographies (
            id SERIAL PRIMARY KEY,
            patient_id INTEGER NOT NULL REFERENCES patients(id) ON DELETE CASCADE,
            medecin_id INTEGER REFERENCES medecins(id),
            date_examen DATE NOT NULL,
            indication TEXT,
            appareil VARCHAR(200),
            medecin_demandeur VARCHAR(200),
            echographiste VARCHAR(200),
            surface_corporelle DECIMAL(5,2),
            vo_dia DECIMAL(5,2),
            siv DECIMAL(5,2),
            dts DECIMAL(5,2),
            dtd DECIMAL(5,2),
            pp DECIMAL(5,2),
            ao DECIMAL(5,2),
            og DECIMAL(5,2),
            fe DECIMAL(5,2),
            fr DECIMAL(5,2),
            cinetique_globale TEXT,
            vg_obs TEXT,
            aorte_obs TEXT,
            paroi_post_obs TEXT,
            siv_obs TEXT,
            og_obs TEXT,
            od_obs TEXT,
            vci_obs TEXT,
            valve_mitrale TEXT,
            valve_aortique TEXT,
            valve_pulmonaire TEXT,
            valve_tricuspide TEXT,
            pericarde TEXT,
            doppler_mitrale TEXT,
            doppler_tricuspide TEXT,
            doppler_pulmonaire TEXT,
            doppler_aortique TEXT,
            doppler_sor DECIMAL(6,2),
            doppler_vor DECIMAL(6,2),
            doppler_ea DECIMAL(5,2),
            commentaire TEXT,
            conclusion TEXT,
            created_at TIMESTAMP DEFAULT NOW(),
            updated_at TIMESTAMP DEFAULT NOW()
        )
    ");

    ensureColumn($pdo, 'patients', 'age INTEGER');
    ensureColumn($pdo, 'patients', 'telephone VARCHAR(20)');
    ensureColumn($pdo, 'patients', 'num_dossier VARCHAR(50)');
    ensureColumn($pdo, 'patients', 'service VARCHAR(100)');

    ensureColumn($pdo, 'echocardiographies', 'indication TEXT');
    ensureColumn($pdo, 'echocardiographies', 'appareil VARCHAR(200)');
    ensureColumn($pdo, 'echocardiographies', 'medecin_demandeur VARCHAR(200)');
    ensureColumn($pdo, 'echocardiographies', 'echographiste VARCHAR(200)');
    ensureColumn($pdo, 'echocardiographies', 'surface_corporelle DECIMAL(5,2)');
    ensureColumn($pdo, 'echocardiographies', 'fenetre VARCHAR(100)');
    ensureColumn($pdo, 'echocardiographies', 'fenetre_mode VARCHAR(100)');
    ensureColumn($pdo, 'echocardiographies', 'dts DECIMAL(5,2)');
    ensureColumn($pdo, 'echocardiographies', 'dtd DECIMAL(5,2)');
    ensureColumn($pdo, 'echocardiographies', 'pp DECIMAL(5,2)');
    ensureColumn($pdo, 'echocardiographies', 'ao DECIMAL(5,2)');
    ensureColumn($pdo, 'echocardiographies', 'og DECIMAL(5,2)');
    ensureColumn($pdo, 'echocardiographies', 'cinetique_globale TEXT');
    ensureColumn($pdo, 'echocardiographies', 'vg_obs TEXT');
    ensureColumn($pdo, 'echocardiographies', 'aorte_obs TEXT');
    ensureColumn($pdo, 'echocardiographies', 'paroi_post_obs TEXT');
    ensureColumn($pdo, 'echocardiographies', 'siv_obs TEXT');
    ensureColumn($pdo, 'echocardiographies', 'og_obs TEXT');
    ensureColumn($pdo, 'echocardiographies', 'od_obs TEXT');
    ensureColumn($pdo, 'echocardiographies', 'vci_obs TEXT');
    ensureColumn($pdo, 'echocardiographies', 'doppler_tricuspide TEXT');
    ensureColumn($pdo, 'echocardiographies', 'doppler_pulmonaire TEXT');
    ensureColumn($pdo, 'echocardiographies', 'doppler_aortique TEXT');
    ensureColumn($pdo, 'echocardiographies', 'vd_obs TEXT');
    ensureColumn($pdo, 'echocardiographies', 's_og DECIMAL(5,2)');
    ensureColumn($pdo, 'echocardiographies', 'od_obs TEXT');
    ensureColumn($pdo, 'echocardiographies', 's_od DECIMAL(5,2)');
    ensureColumn($pdo, 'echocardiographies', 'taps DECIMAL(5,2)');
    ensureColumn($pdo, 'echocardiographies', 'vci_diametre DECIMAL(5,2)');
    ensureColumn($pdo, 'echocardiographies', 'doppler_tricuspide_vmax DECIMAL(6,2)');
    ensureColumn($pdo, 'echocardiographies', 'paps DECIMAL(6,2)');
    ensureColumn($pdo, 'echocardiographies', 'doppler_pulmonaire_vmax DECIMAL(6,2)');
    ensureColumn($pdo, 'echocardiographies', 'doppler_aortique_vmax DECIMAL(6,2)');

    // Créer les index
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_echo_date ON echocardiographies(date_examen)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_echo_patient ON echocardiographies(patient_id)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_patient_nom ON patients(nom, prenom)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_patient_dossier ON patients(num_dossier)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_patient_tel ON patients(telephone)");

    // Créer les tables d'administration
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS roles (
            id SERIAL PRIMARY KEY,
            nom VARCHAR(100) NOT NULL UNIQUE,
            description TEXT,
            can_manage_users BOOLEAN DEFAULT FALSE,
            can_manage_fiches BOOLEAN DEFAULT FALSE,
            can_view_logs BOOLEAN DEFAULT FALSE,
            can_create_fiches BOOLEAN DEFAULT FALSE,
            can_update_fiches BOOLEAN DEFAULT FALSE,
            can_delete_fiches BOOLEAN DEFAULT FALSE,
            can_view_fiches BOOLEAN DEFAULT TRUE,
            created_at TIMESTAMP DEFAULT NOW()
        )
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS users (
            id SERIAL PRIMARY KEY,
            username VARCHAR(100) NOT NULL UNIQUE,
            nom VARCHAR(100) NOT NULL,
            prenom VARCHAR(100) NOT NULL,
            email VARCHAR(150),
            password_hash VARCHAR(255) NOT NULL,
            role_id INTEGER REFERENCES roles(id) ON DELETE SET NULL,
            is_active BOOLEAN DEFAULT TRUE,
            created_at TIMESTAMP DEFAULT NOW(),
            updated_at TIMESTAMP DEFAULT NOW()
        )
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS activity_logs (
            id SERIAL PRIMARY KEY,
            user_name VARCHAR(150),
            role_name VARCHAR(100),
            action TEXT NOT NULL,
            details TEXT,
            created_at TIMESTAMP DEFAULT NOW()
        )
    ");

    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_logs_created_at ON activity_logs(created_at)");

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM roles");
    $stmt->execute();
    if ((int)$stmt->fetchColumn() === 0) {
        $pdo->prepare("
            INSERT INTO roles (nom, description, can_manage_users, can_manage_fiches, can_view_logs, can_create_fiches, can_update_fiches, can_delete_fiches, can_view_fiches)
            VALUES
                ('admin', 'Administration complète', TRUE, TRUE, TRUE, TRUE, TRUE, TRUE, TRUE),
                ('m1', 'Gestion complète des fiches', FALSE, TRUE, FALSE, TRUE, TRUE, TRUE, TRUE),
                ('m2', 'Création et lecture des fiches', FALSE, TRUE, FALSE, TRUE, FALSE, FALSE, TRUE)
        ")->execute();
    }

    $pdo->exec("UPDATE roles SET nom = LOWER(nom) WHERE nom <> LOWER(nom)");

    // Créer un utilisateur admin par défaut si aucun n'existe
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = 'admin'");
    $stmt->execute();
    if ((int)$stmt->fetchColumn() === 0) {
        $stmt = $pdo->prepare("SELECT id FROM roles WHERE nom = 'admin' LIMIT 1");
        $stmt->execute();
        $adminRole = $stmt->fetch();
        $roleId = $adminRole ? $adminRole['id'] : null;
        
        $passwordHash = password_hash('admin123', PASSWORD_DEFAULT);
        $pdo->prepare("
            INSERT INTO users (username, nom, prenom, email, password_hash, role_id, is_active)
            VALUES ('admin', 'ADMIN', 'Administrateur', 'admin@doppler.local', :password_hash, :role_id, TRUE)
        ")->execute([':password_hash' => $passwordHash, ':role_id' => $roleId]);
    }

    $testUsers = [
        ['m1', 'MEDECIN', 'Niveau 1', 'm1@doppler.local', '$2y$10$awY3iIus92ZrKeTJ.GjV9uyKtvbN2eN.0ogR9z775SVhYLcH8NKOO', 'm1'],
        ['m2', 'MEDECIN', 'Niveau 2', 'm2@doppler.local', '$2y$10$xGGDryOEjDMdyzIowwaXAeeAlRnUoeeKTEsN4vPkmykn7HkHKtLsy', 'm2'],
    ];
    foreach ($testUsers as [$username, $nom, $prenom, $email, $hash, $roleNom]) {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE username = ?');
        $stmt->execute([$username]);
        if ((int)$stmt->fetchColumn() === 0) {
            $stmt = $pdo->prepare('SELECT id FROM roles WHERE LOWER(nom) = ? LIMIT 1');
            $stmt->execute([strtolower($roleNom)]);
            $roleId = $stmt->fetchColumn();
            $pdo->prepare('INSERT INTO users (username, nom, prenom, email, password_hash, role_id, is_active) VALUES (?, ?, ?, ?, ?, ?, TRUE)')
                ->execute([$username, $nom, $prenom, $email, $hash, $roleId]);
        }
    }
}

function get_request_data(): array {
    $body = get_input();
    return array_merge($_GET, is_array($body) ? $body : []);
}

function getActorInfo(array $d): array {
    $actorName = trim((string)($d['actor_name'] ?? $d['actorName'] ?? $d['user_name'] ?? $d['current_user'] ?? ''));
    $actorRole = trim(strtolower((string)($d['actor_role'] ?? $d['actorRole'] ?? $d['role'] ?? $d['role_name'] ?? '')));
    return [
        'actor_name' => $actorName !== '' ? $actorName : 'SYSTEM',
        'actor_role' => $actorRole,
    ];
}

function fetchRolePermissions(PDO $pdo, string $roleName): ?array {
    $role = strtolower(trim($roleName));
    if ($role === '') {
        return null;
    }
    $stmt = $pdo->prepare('SELECT * FROM roles WHERE LOWER(nom) = :role LIMIT 1');
    $stmt->execute([':role' => $role]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function canPerformFicheAction(PDO $pdo, string $actorRole, string $action): bool {
    $role = strtolower(trim($actorRole));
    if ($role === 'admin') {
        return true;
    }

    $permissions = fetchRolePermissions($pdo, $role);
    $map = [
        'read' => 'can_view_fiches',
        'create' => 'can_create_fiches',
        'update' => 'can_update_fiches',
        'delete' => 'can_delete_fiches',
    ];

    if ($permissions && isset($map[$action])) {
        return (bool)$permissions[$map[$action]];
    }

    if ($action === 'read') {
        return true;
    }
    if ($action === 'create') {
        return in_array($role, ['m1', 'm2'], true);
    }
    if ($action === 'update' || $action === 'delete') {
        return $role === 'm1';
    }

    return false;
}

function isAdminRole(string $actorRole): bool {
    return strtolower(trim($actorRole)) === 'admin';
}

function logActivity(PDO $pdo, ?string $userName, ?string $roleName, string $action, ?string $details = null): void {
    $stmt = $pdo->prepare("
        INSERT INTO activity_logs (user_name, role_name, action, details)
        VALUES (:user_name, :role_name, :action, :details)
    ");
    $stmt->execute([
        ':user_name' => $userName,
        ':role_name' => $roleName,
        ':action' => $action,
        ':details' => $details,
    ]);
}

class TestResponseException extends Exception {
    private $data;
    private $statusCode;
    public function __construct($data, $code) {
        $this->data = $data;
        $this->statusCode = $code;
        parent::__construct(is_array($data) && isset($data['error']) ? $data['error'] : 'API Response');
    }
    public function getData() { return $this->data; }
    public function getCodeVal() { return $this->statusCode; }
}

function json_response($data, $code = 200) {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit(0);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    if (defined('TESTING_MODE')) {
        throw new TestResponseException($data, $code);
    }
    exit;
}

function get_input() {
    global $mock_input;
    if (isset($mock_input)) {
        return $mock_input;
    }
    return json_decode(file_get_contents('php://input'), true) ?? [];
}

function boolValue($value): bool {
    if (is_bool($value)) return $value;
    if (is_numeric($value)) return (int)$value === 1;
    return in_array(strtolower((string)$value), ['1', 'true', 'oui', 'yes', 'o'], true);
}