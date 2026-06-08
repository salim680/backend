<?php
// check-role.php
require_once 'jwt.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: https://aladlyfamily.kesug.com');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Credentials: true');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// تحقق من الـ JWT
$headers = getallheaders();
$authHeader = $headers['Authorization'] ?? '';

if (!str_starts_with($authHeader, 'Bearer ')) {
    http_response_code(401);
    echo json_encode(['hasRole' => false, 'error' => 'غير مصرح']);
    exit();
}

$token   = substr($authHeader, 7);
$payload = verifyJWT($token, JWT_SECRET);

if (!$payload) {
    http_response_code(401);
    echo json_encode(['hasRole' => false, 'error' => 'جلسة منتهية']);
    exit();
}

// اقرأ الرول المطلوب
$input = json_decode(file_get_contents('php://input'), true);
$requiredRole = $input['role'] ?? '';

// جلب الرتب من البوت مباشرة
$userId = $payload['sub'];
$botApiUrl = "https://b-fo6h.onrender.com/api/check-role/" . $userId;

$ch = curl_init($botApiUrl);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 10,
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_HTTPHEADER => ['Content-Type: application/json']
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode !== 200) {
    http_response_code(503);
    echo json_encode(['hasRole' => false, 'error' => 'البوت غير متاح حالياً']);
    exit();
}

$botData = json_decode($response, true);
$userRoles = $botData['success'] ? $botData['roles'] : [];

// التحقق من الرول
$hasRole = false;
if (!empty($requiredRole)) {
    $hasRole = in_array($requiredRole, $userRoles);
}

echo json_encode([
    'hasRole' => $hasRole,
    'username' => $payload['username'],
    'userId' => $userId,
    'roles' => $userRoles
], JSON_UNESCAPED_UNICODE);
?>
