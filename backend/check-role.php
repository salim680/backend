<?php
// check-role.php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: https://aladlyfamily.kesug.com');
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: GET, OPTIONS');

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

session_start();

// التحقق من التوكن
$headers = getallheaders();
$auth_header = isset($headers['Authorization']) ? $headers['Authorization'] : '';
$token = preg_replace('/Bearer\s/', '', $auth_header);

if (empty($token) || !isset($_SESSION['auth_token']) || $_SESSION['auth_token'] !== $token) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

$userId = $_GET['userId'] ?? '';
$roleId = $_GET['roleId'] ?? '';

if (empty($userId) || empty($roleId)) {
    echo json_encode(['success' => false, 'error' => 'Missing parameters']);
    exit();
}

// قائمة رتب الأدمن المسموح بها
$admin_role_id = '1506606160059564232';
$hasRole = ($roleId === $admin_role_id && isset($_SESSION['user_id']) && $_SESSION['user_id'] === $userId);

echo json_encode([
    'success' => true,
    'hasRole' => $hasRole,
    'userId' => $userId,
    'roleId' => $roleId
]);
?>
