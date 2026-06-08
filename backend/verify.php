<?php
// verify.php - نقطة تحقق مركزية آمنة
require_once 'jwt.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: https://aladlyfamily.kesug.com');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// ========== 1. التحقق من JWT ==========
$headers = getallheaders();
$authHeader = $headers['Authorization'] ?? '';

if (!str_starts_with($authHeader, 'Bearer ')) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'توكن مفقود']);
    exit();
}

$token = substr($authHeader, 7);
$payload = verifyJWT($token, JWT_SECRET);

if (!$payload) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'جلسة منتهية']);
    exit();
}

// ========== 2. جلب الرتب من البوت ==========
$userId = $payload['sub'];
$botApiUrl = "https://b-fo6h.onrender.com/api/check-role/" . $userId;

$ch = curl_init($botApiUrl);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 10,
    CURLOPT_SSL_VERIFYPEER => true
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode !== 200) {
    http_response_code(503);
    echo json_encode(['success' => false, 'error' => 'البوت غير متاح']);
    exit();
}

$botData = json_decode($response, true);
$userRoles = $botData['success'] ? $botData['roles'] : [];

// ========== 3. التحقق من الرول المطلوب ==========
$input = json_decode(file_get_contents('php://input'), true);
$requiredRole = $input['role'] ?? '';

$hasRole = false;
if (!empty($requiredRole)) {
    $hasRole = in_array($requiredRole, $userRoles);
}

// ========== 4. الرد ==========
echo json_encode([
    'success' => true,
    'userId' => $userId,
    'username' => $payload['username'],
    'avatar' => $payload['avatar'],
    'roles' => $userRoles,
    'hasRole' => $hasRole,
    'requiredRole' => $requiredRole
], JSON_UNESCAPED_UNICODE);
?>
