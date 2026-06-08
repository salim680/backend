<?php
// =============================================
// معالج مصادقة Discord OAuth2
// =============================================

error_reporting(E_ALL);
ini_set('display_errors', 0);

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json; charset=utf-8');

// =============================================
// الدوال المساعدة (يجب تعريفها أولاً)
// =============================================

function sendError($message, $code = 400) {
    http_response_code($code);
    echo json_encode(['success' => false, 'error' => $message], JSON_UNESCAPED_UNICODE);
    exit();
}

function makeDiscordRequest($url, $method = 'GET', $data = null, $isTokenExchange = false, $accessToken = null) {
    $ch = curl_init();

    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        if ($isTokenExchange) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/x-www-form-urlencoded',
                'User-Agent: DiscordBot (aladlyfamily, 1.0)'
            ]);
        } else {
            if ($data) curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $accessToken,
                'User-Agent: DiscordBot (aladlyfamily, 1.0)'
            ]);
        }
    } else {
        $headers = ['User-Agent: DiscordBot (aladlyfamily, 1.0)'];
        if ($accessToken) {
            $headers[] = 'Authorization: Bearer ' . $accessToken;
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    }

    $response  = curl_exec($ch);
    $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError) {
        error_log('cURL error: ' . $curlError);
        return ['error' => true, 'error_description' => $curlError];
    }

    error_log("Discord API [{$method}] {$url} => HTTP {$httpCode} => " . substr($response, 0, 300));

    return json_decode($response, true);
}

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

// =============================================
// معالجة الطلبات
// =============================================

// OPTIONS - CORS preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// GET - غير مدعوم
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    sendError('طريقة GET غير مدعومة. استخدم POST', 405);
}

// POST فقط من هنا
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendError('طريقة الطلب غير مسموحة', 405);
}

// =============================================
// الإعدادات
// =============================================
$clientId        = '1505715876287221860';
$clientSecret    = 'WEE9DBW6NrJWfET9vL40u1D2lCjj9bOM';
$requiredGuildId = '1018879014150090832';

// =============================================
// استقبال البيانات
// =============================================
$rawInput = file_get_contents('php://input');
error_log('auth.php raw input: ' . $rawInput);

$input = json_decode($rawInput, true);

if (!$input || !isset($input['code']) || empty(trim($input['code']))) {
    sendError('بيانات الطلب غير مكتملة - code مفقود');
}

$code        = trim($input['code']);
$redirectUri = isset($input['redirectUri']) ? trim($input['redirectUri']) : 'https://aladlyfamily.kesug.com/login.html';

error_log('code: ' . substr($code, 0, 20) . '... | redirectUri: ' . $redirectUri);

// =============================================
// 1. تبادل الكود للحصول على Access Token
// =============================================
$tokenData = exchangeCode($code, $redirectUri, $clientId, $clientSecret);

if (!$tokenData) {
    sendError('لم يُستلم رد من Discord');
}

if (isset($tokenData['error'])) {
    $errDesc = $tokenData['error_description'] ?? $tokenData['error'] ?? 'خطأ غير معروف';
    error_log('Token exchange error: ' . json_encode($tokenData));
    sendError('فشل تبادل الكود: ' . $errDesc);
}

if (!isset($tokenData['access_token'])) {
    error_log('No access_token in response: ' . json_encode($tokenData));
    sendError('لم يُستلم access_token من Discord');
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
// 3. التحقق من العضوية وجلب الأدوار
// =============================================
$userRoles  = [];
$memberData = null;

if ($requiredGuildId) {
    $memberUrl  = "https://discord.com/api/v10/users/@me/guilds/{$requiredGuildId}/member";
    $memberData = makeDiscordRequest($memberUrl, 'GET', null, false, $accessToken);

    if ($memberData && isset($memberData['roles'])) {
        $userRoles = $memberData['roles'];
    } else {
        error_log('Member check failed: ' . json_encode($memberData));
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
?>
