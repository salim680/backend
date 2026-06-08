<?php
// auth.php - معالجة تسجيل الدخول عبر Discord
require_once 'jwt.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: https://aladlyfamily.kesug.com');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// ========== إعدادات Discord (سرية) ==========
define('DISCORD_CLIENT_ID', '1505715876287221860');
define('DISCORD_CLIENT_SECRET', 'WEE9DBW6NrJWfET9vL40u1D2lCjj9bOM');
define('DISCORD_REDIRECT_URI', 'https://aladlyfamily.kesug.com/login.html');
define('SESSION_DURATION', 86400); // 24 ساعة

// ========== استقبال الكود من الفرونت اند ==========
$input = json_decode(file_get_contents('php://input'), true);

if (empty($input['code'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'كود OAuth مفقود']);
    exit();
}

$code = trim($input['code']);

// ========== 1. تبادل الكود مع Discord ==========
$tokenResponse = discordPost('https://discord.com/api/v10/oauth2/token', [
    'client_id' => DISCORD_CLIENT_ID,
    'client_secret' => DISCORD_CLIENT_SECRET,
    'grant_type' => 'authorization_code',
    'code' => $code,
    'redirect_uri' => DISCORD_REDIRECT_URI,
], true);

if (empty($tokenResponse['access_token'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'فشل التحقق من Discord']);
    exit();
}

$accessToken = $tokenResponse['access_token'];

// ========== 2. جلب بيانات المستخدم ==========
$user = discordGet('https://discord.com/api/v10/users/@me', $accessToken);

if (empty($user['id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'فشل جلب بيانات المستخدم']);
    exit();
}

// ========== 3. إنشاء JWT (بدون تخزين الرتب) ==========
$payload = [
    'sub' => $user['id'],
    'username' => $user['username'],
    'avatar' => $user['avatar'] ? "https://cdn.discordapp.com/avatars/{$user['id']}/{$user['avatar']}.png" : null,
    'iat' => time(),
    'exp' => time() + SESSION_DURATION
];

$jwt = createJWT($payload, JWT_SECRET);

// ========== 4. الرد على العميل ==========
echo json_encode([
    'success' => true,
    'token' => $jwt,
    'user' => [
        'id' => $user['id'],
        'username' => $user['username'],
        'avatar' => $payload['avatar'],
    ]
], JSON_UNESCAPED_UNICODE);

// ========== دوال مساعدة للاتصال بـ Discord API ==========
function discordGet($url, $token) {
    return discordRequest($url, 'GET', null, $token, false);
}

function discordPost($url, $data, $isForm = false) {
    return discordRequest($url, 'POST', $data, null, $isForm);
}

function discordRequest($url, $method, $data, $token, $isForm) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);

    $headers = ['User-Agent: AladlyBot/1.0'];

    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        if ($isForm) {
            $headers[] = 'Content-Type: application/x-www-form-urlencoded';
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        } else {
            $headers[] = 'Content-Type: application/json';
            if ($token) $headers[] = 'Authorization: Bearer ' . $token;
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
    }

    if ($token && $method === 'GET') {
        $headers[] = 'Authorization: Bearer ' . $token;
    }

    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    $response = curl_exec($ch);
    curl_close($ch);

    return json_decode($response, true) ?? [];
}
?>
