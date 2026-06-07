<?php
// login.php
require_once 'api_config.php';

// 1. تحقق من كود Discord المرسل من Frontend
$input = json_decode(file_get_contents('php://input'), true);
$discord_code = $input['code'] ?? '';

if (empty($discord_code)) {
    echo json_encode(['success' => false, 'error' => 'No code provided']);
    exit();
}

// 2. تبادل الكود مع Token من Discord
$client_id = 'YOUR_CLIENT_ID';
$client_secret = 'YOUR_CLIENT_SECRET';
$redirect_uri = 'YOUR_REDIRECT_URI'; // نفس الـ Redirect URI في Discord Developer Portal

$token_url = 'https://discord.com/api/oauth2/token';
$token_data = [
    'client_id' => $client_id,
    'client_secret' => $client_secret,
    'grant_type' => 'authorization_code',
    'code' => $discord_code,
    'redirect_uri' => $redirect_uri,
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $token_url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($token_data));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
curl_close($ch);

$token_data = json_decode($response, true);
$access_token = $token_data['access_token'] ?? '';

if (empty($access_token)) {
    echo json_encode(['success' => false, 'error' => 'Failed to get access token']);
    exit();
}

// 3. جلب بيانات المستخدم من Discord
$user_url = 'https://discord.com/api/users/@me';
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $user_url);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $access_token]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$user_response = curl_exec($ch);
curl_close($ch);

$user_data = json_decode($user_response, true);
$user_id = $user_data['id'] ?? '';
$username = $user_data['username'] ?? 'Unknown';
$avatar = $user_data['avatar'] ? "https://cdn.discordapp.com/avatars/{$user_id}/{$avatar}.png" : '';

// 4. حفظ الجلسة وإنشاء Token عشوائي
$auth_token = bin2hex(random_bytes(32)); // توليد Token قوي
$_SESSION['auth_token'] = $auth_token;
$_SESSION['user_id'] = $user_id;
$_SESSION['username'] = $username;
$_SESSION['avatar'] = $avatar;
$_SESSION['roles'] = ['1506606160059564232']; // هنا تجيب الرتب من قاعدة البيانات

echo json_encode([
    'success' => true,
    'token' => $auth_token,
    'user' => [
        'id' => $user_id,
        'username' => $username,
        'avatar' => $avatar,
        'roles' => $_SESSION['roles']
    ]
]);
?>
