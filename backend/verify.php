<?php
// verify.php - استدعيه من أي صفحة PHP تحتاج حماية

require_once 'auth.php'; // للحصول على verifyJWT و JWT_SECRET

function requireAuth(): array {
    $headers = getallheaders();
    $authHeader = $headers['Authorization'] ?? '';

    if (!str_starts_with($authHeader, 'Bearer ')) {
        http_response_code(401);
        echo json_encode(['error' => 'غير مصرح']);
        exit();
    }

    $token   = substr($authHeader, 7);
    $payload = verifyJWT($token, JWT_SECRET);

    if (!$payload) {
        http_response_code(401);
        echo json_encode(['error' => 'الجلسة منتهية أو غير صالحة']);
        exit();
    }

    return $payload; // يحتوي على id, username, roles الحقيقية
}

function requireRole(array $payload, string $roleId): void {
    if (!in_array($roleId, $payload['roles'] ?? [])) {
        http_response_code(403);
        echo json_encode(['error' => 'ليس لديك صلاحية للوصول']);
        exit();
    }
}
