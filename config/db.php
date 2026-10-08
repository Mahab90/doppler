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
    }
    return $pdo;
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

function canManagePatients(PDO $pdo, string $actorRole): bool {
    if (isAdminRole($actorRole)) {
        return true;
    }

    $permissions = fetchRolePermissions($pdo, $actorRole);
    return $permissions ? (bool)$permissions['can_manage_patients'] : false;
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