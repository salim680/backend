<?php
// jwt.php - دوال JWT فقط
// ضع هذا الملف في نفس مجلد ملفات PHP الأخرى

define('JWT_SECRET', 'AladlyFamily_SecretKey_2024_@#$%^&*_Secure_12345');

function createJWT(array $payload, string $secret): string {
    $header = base64url_encode(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
    $body = base64url_encode(json_encode($payload));
    $sig = base64url_encode(hash_hmac('sha256', "$header.$body", $secret, true));
    return "$header.$body.$sig";
}

function verifyJWT(string $token, string $secret): ?array {
    $parts = explode('.', $token);
    if (count($parts) !== 3) return null;
    
    [$header, $body, $sig] = $parts;
    $expected = base64url_encode(hash_hmac('sha256', "$header.$body", $secret, true));
    
    if (!hash_equals($expected, $sig)) return null;
    
    $payload = json_decode(base64url_decode($body), true);
    if (($payload['exp'] ?? 0) < time()) return null;
    
    return $payload;
}

function base64url_encode(string $data): string {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function base64url_decode(string $data): string {
    return base64_decode(strtr($data, '-_', '+/'));
}
?>
