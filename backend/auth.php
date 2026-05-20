<?php
// =============================================
// معالج مصادقة Discord OAuth2
// =============================================

// تعطيل عرض الأخطاء في الإنتاج
error_reporting(E_ALL);
ini_set('display_errors', 0);

// السماح بالطلبات من أي مصدر (للتطوير)
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json; charset=utf-8');

// معالجة طلب OPTIONS للمتصفح
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// قبول طلبات POST فقط
// ========== GET - تحديث الأدوار ==========
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'refreshRoles') {
    $userId = $_GET['userId'] ?? '';
    
    // هنا نحتاج access token، لكن لا نملكه في GET
    // لذلك نرجع خطأ - هذه الدالة تحتاج تطوير
    sendError('هذه الوظيفة غير متاحة حالياً. استخدم تسجيل الدخول لتحديث الأدوار.', 400);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendError('طريقة الطلب غير مسموحة', 405);
}

// استقبال البيانات
$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['code'])) {
    sendError('بيانات الطلب غير مكتملة');
}

$code = $input['code'];
$redirectUri = $input['redirectUri'] ?? 'https://aladlyfamily.kesug.com/login.html';

// =============================================
// الإعدادات - قم بتعديلها بمعلومات تطبيقك
// =============================================
$clientId = '1505715876287221860';     // استبدل بمعرف التطبيق
$clientSecret = 'VJ65TNLv4OWpdcOn9Wl9DPZq7kQT0pIX';  // استبدل بسر التطبيق
$requiredGuildId = '1505714786456043520'; // استبدل بمعرف السيرفر المطلوب

// =============================================
// تبادل الكود مع Discord
// =============================================
$tokenData = exchangeCode($code, $redirectUri, $clientId, $clientSecret);

if (!$tokenData || isset($tokenData['error'])) {
    sendError('فشل تبادل الكود: ' . ($tokenData['error_description'] ?? 'خطأ غير معروف'));
}

$accessToken = $tokenData['access_token'];

// =============================================
// جلب بيانات المستخدم
// =============================================
$userData = fetchUserData($accessToken);

if (!$userData || isset($userData['message'])) {
    sendError('فشل جلب بيانات المستخدم: ' . ($userData['message'] ?? 'خطأ غير معروف'));
}
// =============================================
// جلب أدوار المستخدم في السيرفر المطلوب
// =============================================
$userRoles = [];
if ($requiredGuildId && $requiredGuildId !== '123456789012345678') {
    $memberUrl = "https://discord.com/api/users/@me/guilds/{$requiredGuildId}/member";
    $memberData = makeDiscordRequest($memberUrl, 'GET', null, false, $accessToken);
    
    if ($memberData && isset($memberData['roles'])) {
        $userRoles = $memberData['roles'];
    }
}

// ... (في مصفوفة user النهائية أضف roles)
$user = [
    'success' => true,
    'user' => [
        'id' => $userData['id'],
        'username' => $userData['username'],
        'discriminator' => $userData['discriminator'] ?? '0',
        'roles' => $userRoles,
        'avatar' => $userData['avatar'] ? 
            "https://cdn.discordapp.com/avatars/{$userData['id']}/{$userData['avatar']}.png" : 
            null,
        'email' => $userData['email'] ?? null,
        'joinedAt' => date('c'),
        'roles' => $userRoles       // ← الأدوار هنا
    ]
];
// =============================================
// التحقق من عضوية السيرفر
// =============================================
if ($requiredGuildId && $requiredGuildId !== '123456789012345678') {
    $isInGuild = checkGuildMembership($accessToken, $requiredGuildId);
    
    if (!$isInGuild) {
        sendError('يجب أن تكون عضواً في سيرفر Discord الرسمي للدخول');
    }
}

// =============================================
// تجهيز بيانات المستخدم للإرجاع
// =============================================
$user = [
    'success' => true,
    'user' => [
        'id' => $userData['id'],
        'username' => $userData['username'],
        'discriminator' => $userData['discriminator'] ?? '0',
        'avatar' => $userData['avatar'] ? 
            "https://cdn.discordapp.com/avatars/{$userData['id']}/{$userData['avatar']}.png" : 
            null,
        'email' => $userData['email'] ?? null,
        'joinedAt' => date('c'),
        'roles' => $userRoles
    ]
];

echo json_encode($user, JSON_UNESCAPED_UNICODE);

// =============================================
// الدوال المساعدة
// =============================================

/**
 * تبادل كود OAuth مع Discord API
 */
function exchangeCode($code, $redirectUri, $clientId, $clientSecret) {
    $url = 'https://discord.com/api/v10/oauth2/token';
    
    $data = [
        'client_id' => $clientId,
        'client_secret' => $clientSecret,
        'grant_type' => 'authorization_code',
        'code' => $code,
        'redirect_uri' => $redirectUri
    ];
    
    return makeDiscordRequest($url, 'POST', $data, true);
}

/**
 * جلب بيانات المستخدم من Discord
 */
function fetchUserData($accessToken) {
    $url = 'https://discord.com/api/v10/users/@me';
    return makeDiscordRequest($url, 'GET', null, false, $accessToken);
}

/**
 * التحقق من عضوية المستخدم في سيرفر معين
 */
function checkGuildMembership($accessToken, $guildId) {
    $url = 'https://discord.com/api/users/@me/guilds';
    $guilds = makeDiscordRequest($url, 'GET', null, false, $accessToken);
    
    if (!is_array($guilds)) {
        return false;
    }
    
    foreach ($guilds as $guild) {
        if (isset($guild['id']) && $guild['id'] === $guildId) {
            return true;
        }
    }
    
    return false;
}

/**
 * إرسال طلب إلى Discord API
 */
function makeDiscordRequest($url, $method = 'GET', $data = null, $isTokenExchange = false, $accessToken = null) {
    $ch = curl_init();
    
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    // إعدادات الطلب حسب النوع
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        
        if ($isTokenExchange) {
            // تبادل الكود يستخدم form-urlencoded
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/x-www-form-urlencoded'
            ]);
        } else {
            // باقي الطلبات تستخدم JSON
            if ($data) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            }
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $accessToken
            ]);
        }
    } else {
        // طلب GET
        if ($accessToken) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer ' . $accessToken
            ]);
        }
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    
    curl_close($ch);
    
    if ($curlError) {
        return ['error' => true, 'error_description' => $curlError];
    }
    
    return json_decode($response, true);
}

/**
 * إرسال رد خطأ
 */
function sendError($message, $code = 400) {
    http_response_code($code);
    echo json_encode([
        'success' => false,
        'error' => $message
    ], JSON_UNESCAPED_UNICODE);
    exit();
}
?>