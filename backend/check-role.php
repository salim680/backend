<?php
// check-role.php - التحقق من رتب المستخدم عبر البوت
require_once 'jwt.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: https://aladlyfamily.kesug.com');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// ========== 1. التحقق من صحة JWT ==========
$headers = getallheaders();
$authHeader = $headers['Authorization'] ?? '';

if (!str_starts_with($authHeader, 'Bearer ')) {
    http_response_code(401);
    echo json_encode(['hasRole' => false, 'error' => 'غير مصرح - لا يوجد توكن']);
    exit();
}

$token = substr($authHeader, 7);
$payload = verifyJWT($token, JWT_SECRET);

if (!$payload) {
    http_response_code(401);
    echo json_encode(['hasRole' => false, 'error' => 'جلسة منتهية - الرجاء تسجيل الدخول مرة أخرى']);
    exit();
}

// ========== 2. قراءة الرول المطلوب ==========
$input = json_decode(file_get_contents('php://input'), true);
$requiredRole = $input['role'] ?? '';

if (empty($requiredRole)) {
    echo json_encode(['hasRole' => false, 'error' => 'لم يتم تحديد الدور المطلوب']);
    exit();
}

// ========== 3. الاتصال بالبوت لجلب رتب المستخدم ==========
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
$curlError = curl_error($ch);
curl_close($ch);

// ========== 4. معالجة رد البوت ==========
if ($httpCode !== 200 || !$response) {
    error_log("Bot API Error: HTTP $httpCode - $curlError");
    echo json_encode([
        'hasRole' => false,
        'error' => 'البوت غير متاح حالياً، حاول مرة أخرى'
    ]);
    exit();
}

$botData = json_decode($response, true);

if (!$botData || !isset($botData['success'])) {
    echo json_encode([
        'hasRole' => false,
        'error' => 'استجابة غير صالحة من البوت'
    ]);
    exit();
}

if (!$botData['success']) {
    echo json_encode([
        'hasRole' => false,
        'error' => $botData['error'] ?? 'فشل جلب الرتب من البوت'
    ]);
    exit();
}

$userRoles = $botData['roles'] ?? [];
$hasRole = in_array($requiredRole, $userRoles);

// ========== 5. الرد بالنتيجة ==========
echo json_encode([
    'hasRole' => $hasRole,
    'username' => $payload['username'],
    'requiredRole' => $requiredRole,
    'userRoles' => $userRoles // اختياري: للتصحيح فقط، يمكن حذفه بعد التأكد من العمل
], JSON_UNESCAPED_UNICODE);
?>
