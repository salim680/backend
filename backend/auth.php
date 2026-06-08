<?php
// auth.php - Backend فقط، لا يُشارك مع الفرونت اند أبداً

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: https://aladlyfamily.kesug.com'); // محدد وليس *
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once 'jwt.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit(); }

// =============================================
// إعدادات سرية - لا تضعها في الفرونت اند أبداً
// =============================================
define('DISCORD_CLIENT_ID',     '1505715876287221860');
define('DISCORD_CLIENT_SECRET', 'WEE9DBW6NrJWfET9vL40u1D2lCjj9bOM'); // في الباكاند فقط
define('DISCORD_REDIRECT_URI',  'https://aladlyfamily.kesug.com/login.html');
define('REQUIRED_GUILD_ID',     '1018879014150090832');
define('JWT_SECRET',            'ضع_هنا_مفتاح_عشوائي_طويل_جداً_لا_يعرفه_أحد');
define('SESSION_DURATION',      86400); // 24 ساعة بالثواني

// =============================================
// استقبال الكود
// =============================================
$input = json_decode(file_get_contents('php://input'), true);

if (empty($input['code'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'كود OAuth مفقود']);
    exit();
}

$code = trim($input['code']);

// =============================================
// 1. تبادل الكود — السر يبقى في الباكاند
// =============================================
$tokenResponse = discordPost('https://discord.com/api/v10/oauth2/token', [
    'client_id'     => DISCORD_CLIENT_ID,
    'client_secret' => DISCORD_CLIENT_SECRET, // لا يصل للمتصفح أبداً
    'grant_type'    => 'authorization_code',
    'code'          => $code,
    'redirect_uri'  => DISCORD_REDIRECT_URI,
], true);

if (empty($tokenResponse['access_token'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'فشل التحقق من Discord']);
    exit();
}

$accessToken = $tokenResponse['access_token'];

// =============================================
// 2. جلب بيانات المستخدم
// =============================================
$user = discordGet('https://discord.com/api/v10/users/@me', $accessToken);

if (empty($user['id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'فشل جلب بيانات المستخدم']);
    exit();
}

// =============================================
// 3. التحقق من العضوية وجلب الأدوار — في الباكاند
// =============================================
$member = discordGet(
    "https://discord.com/api/v10/users/@me/guilds/" . REQUIRED_GUILD_ID . "/member",
    $accessToken
);

if (empty($member['roles'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'يجب أن تكون عضواً في السيرفر الرسمي']);
    exit();
}

$roles = $member['roles']; // الأدوار من Discord مباشرة، لا من الفرونت اند

// =============================================
// 4. إنشاء JWT موقّع — لا يمكن تزويره بدون JWT_SECRET
// =============================================
$payload = [
    'sub'      => $user['id'],       // Discord ID
    'username' => $user['username'],
    'avatar'   => $user['avatar']
        ? "https://cdn.discordapp.com/avatars/{$user['id']}/{$user['avatar']}.png"
        : null,
    'roles'    => $roles,            // أدوار حقيقية من Discord
    'iat'      => time(),
    'exp'      => time() + SESSION_DURATION
];

$jwt = createJWT($payload, JWT_SECRET);

// =============================================
// 5. الرد — نرسل JWT فقط، لا secrets
// =============================================
echo json_encode([
    'success' => true,
    'token'   => $jwt,
    'user'    => [
        'id'       => $user['id'],
        'username' => $user['username'],
        'avatar'   => $payload['avatar'],
        // لا نرسل الأدوار للفرونت اند — يتحقق منها الباكاند
    ]
], JSON_UNESCAPED_UNICODE);


// =============================================
// دوال مساعدة
// =============================================

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
        CURLOPT_TIMEOUT        => 15,
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

// JWT بسيط بدون مكتبة خارجية
