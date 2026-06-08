<?php
// login.php - نسخة مبسطة وآمنة
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: https://aladlyfamily.kesug.com');
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// الرد على طلبات OPTIONS (ما قبل الطلب)
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    echo json_encode(['success' => true]);
    exit();
}

// التحقق من أن الطريقة هي POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed. Use POST.']);
    exit();
}

// قراءة البيانات الواردة
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid JSON input']);
    exit();
}

$code = isset($input['code']) ? trim($input['code']) : '';

if (empty($code)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'No authorization code provided']);
    exit();
}

// ========== إعدادات Discord ==========
// ضع بيانات تطبيقك الصحيحة هنا
$client_id = '1505715876287221860';
$client_secret = 'WEE9DBW6NrJWfET9vL40u1D2lCjj9bOM'; // <- هذا مهم جداً! ضع الـ Client Secret حقك
$redirect_uri = 'https://aladlyfamily.kesug.com/callback.html';

// ========== تبادل الـ code مع Discord ==========
$token_url = 'https://discord.com/api/oauth2/token';
$token_data = [
    'client_id' => $client_id,
    'client_secret' => $client_secret,
    'grant_type' => 'authorization_code',
    'code' => $code,
    'redirect_uri' => $redirect_uri,
];

// استخدام cURL لطلب التوكن
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $token_url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($token_data));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // فقط للتجربة على بعض الاستضافات
$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_error = curl_error($ch);
curl_close($ch);

// التحقق من أخطاء cURL
if ($curl_error) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'cURL Error: ' . $curl_error]);
    exit();
}

// تحليل استجابة Discord
$token_response = json_decode($response, true);

if ($http_code !== 200) {
    http_response_code(400);
    $error_msg = isset($token_response['error_description']) ? $token_response['error_description'] : ($token_response['error'] ?? 'Unknown error');
    echo json_encode(['success' => false, 'error' => 'Discord API Error: ' . $error_msg]);
    exit();
}

if (empty($token_response['access_token'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'No access token received']);
    exit();
}

$access_token = $token_response['access_token'];

// ========== جلب بيانات المستخدم من Discord ==========
$user_url = 'https://discord.com/api/users/@me';
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $user_url);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $access_token]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
$user_response = curl_exec($ch);
$user_http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($user_http_code !== 200) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Failed to fetch user data from Discord']);
    exit();
}

$user_data = json_decode($user_response, true);
$user_id = $user_data['id'] ?? '';
$username = $user_data['username'] ?? 'Unknown';
$avatar = !empty($user_data['avatar']) ? "https://cdn.discordapp.com/avatars/{$user_id}/{$user_data['avatar']}.png" : '';

// ========== إنشاء جلسة للمستخدم ==========
session_start();
$auth_token = bin2hex(random_bytes(32));

$_SESSION['auth_token'] = $auth_token;
$_SESSION['user_id'] = $user_id;
$_SESSION['username'] = $username;

// ========== تحديد صلاحيات المستخدم ==========
// هنا تحدد إذا كان المستخدم أدمن أم لا
// حالياً أضفنا أيدي الأدمن المؤقت
$admin_ids = ['1505715876287221860']; // ضع أيدي المستخدمين الأدمن هنا
$roles = in_array($user_id, $admin_ids) ? ['1506606160059564232'] : [];

// ========== إرجاع الاستجابة الناجحة ==========
echo json_encode([
    'success' => true,
    'token' => $auth_token,
    'user' => [
        'id' => $user_id,
        'username' => $username,
        'avatar' => $avatar,
        'roles' => $roles
    ]
]);
?>
