<?php
// =============================================
// معالج مصادقة Discord OAuth2 - نسخة مصلحة
// =============================================

error_reporting(E_ALL);
ini_set('display_errors', 0);

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// ========== GET - تحديث الأدوار ==========
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'refreshRoles') {
    sendError('هذه الوظيفة غير متاحة حالياً. استخدم تسجيل الدخول لتحديث الأدوار.', 400);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendError('طريقة الطلب غير مسموحة', 405);
}

// =============================================
// الإعدادات
// =============================================
$clientId       = '1505715876287221860';
$clientSecret   = 'vGmhcgg1IYHb5URdtWhthgJ5qI52UPtr';
$requiredGuildId = '1018879014150090832';// =============================================
// استقبال البيانات
// =============================================
$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['code'])) {
    sendError('بيانات الطلب غير مكتملة');
}

$code        = $input['code'];
$redirectUri = $input['redirectUri'] ?? 'https://aladlyfamily.kesug.com/login.html';

// =============================================
// 1. تبادل الكود للحصول على Access Token
// =============================================
$tokenData = exchangeCode($code, $redirectUri, $clientId, $clientSecret);

if (!$tokenData || isset($tokenData['error'])) {
    sendError('فشل تبادل الكود: ' . ($tokenData['error_description'] ?? 'خطأ غير معروف'));
}

$accessToken = $tokenData['access_token'];

// =============================================
// 2. جلب بيانات المستخدم
// =============================================
$userData = fetchUserData($accessToken);

if (!$userData || isset($userData['message'])) {
    sendError('فشل جلب بيانات المستخدم');
}

// =============================================
// 3. التحقق من العضوية + جلب الأدوار دفعة واحدة
//    endpoint واحد يثبت العضوية ويعطي الأدوار
// =============================================
$userRoles  = [];
$memberData = null;

if ($requiredGuildId) {
    $memberUrl  = "https://discord.com/api/users/@me/guilds/{$requiredGuildId}/member";
    $memberData = makeDiscordRequest($memberUrl, 'GET', null, false, $accessToken);

    // إذا رجع roles = العضو موجود في السيرفر
    if ($memberData && isset($memberData['roles'])) {
        $userRoles = $memberData['roles'];
    } else {
        // فشل الـ endpoint = مو عضو في السيرفر أو رُفض الطلب
        $errMsg = $memberData['message'] ?? 'غير معروف';
        sendError('يجب أن تكون عضواً في سيرفر Discord الرسمي للدخول');
    }
}

// =============================================
// 4. إرجاع البيانات
// =============================================
echo json_encode([
    'success' => true,
    'user' => [
        'id'            => $userData['id'],
        'username'      => $userData['username'],
        'discriminator' => $userData['discriminator'] ?? '0',
        'avatar'        => $userData['avatar']
            ? "https://cdn.discordapp.com/avatars/{$userData['id']}/{$userData['avatar']}.png"
            : null,
        'email'         => $userData['email'] ?? null,
        'joinedAt'      => $memberData['joined_at'] ?? date('c'),
        'roles'         => $userRoles
    ]
], JSON_UNESCAPED_UNICODE);

// =============================================
// الدوال المساعدة
// =============================================

function exchangeCode($code, $redirectUri, $clientId, $clientSecret) {
    return makeDiscordRequest(
        'https://discord.com/api/v10/oauth2/token',
        'POST',
        [
            'client_id'     => $clientId,
            'client_secret' => $clientSecret,
            'grant_type'    => 'authorization_code',
            'code'          => $code,
            'redirect_uri'  => $redirectUri
        ],
        true
    );
}

function fetchUserData($accessToken) {
    return makeDiscordRequest('https://discord.com/api/v10/users/@me', 'GET', null, false, $accessToken);
}

function makeDiscordRequest($url, $method = 'GET', $data = null, $isTokenExchange = false, $accessToken = null) {
    $ch = curl_init();

    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);

    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        if ($isTokenExchange) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
        } else {
            if ($data) curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $accessToken
            ]);
        }
    } else {
        if ($accessToken) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $accessToken]);
        }
    }

    $response  = curl_exec($ch);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError) return ['error' => true, 'error_description' => $curlError];

    return json_decode($response, true);
}

function sendError($message, $code = 400) {
    http_response_code($code);
    echo json_encode(['success' => false, 'error' => $message], JSON_UNESCAPED_UNICODE);
    exit();
}
?>
