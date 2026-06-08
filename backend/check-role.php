<?php
// check-role.php
require_once 'jwt.php'; // للحصول على verifyJWT و JWT_SECRET

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: https://aladlyfamily.kesug.com');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit(); }

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

if (empty($requiredRole)) {
    echo json_encode(['hasRole' => false, 'error' => 'لم يُحدد الدور']);
    exit();
}

// الأدوار موجودة في الـ JWT — لا يستطيع المستخدم تعديلها
$userRoles = $payload['roles'] ?? [];
$hasRole   = in_array($requiredRole, $userRoles);

echo json_encode([
    'hasRole'      => $hasRole,
    'username'     => $payload['username'],
    'requiredRole' => $requiredRole,
    'userRoles'    => $userRoles
], JSON_UNESCAPED_UNICODE);
