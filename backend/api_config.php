<?php
// api_config.php
header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');

// إعدادات CORS (اسمح فقط لموقعك)
$allowed_origin = "https://aladlyfamily.kesug.com"; // ضع رابط موقعك هنا
if (isset($_SERVER['HTTP_ORIGIN']) && $_SERVER['HTTP_ORIGIN'] === $allowed_origin) {
    header("Access-Control-Allow-Origin: " . $allowed_origin);
    header("Access-Control-Allow-Credentials: true");
    header("Access-Control-Allow-Methods: GET, POST, PATCH, DELETE, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
}

// الرد على طلبات OPTIONS (ما قبل الطلب)
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

session_start(); // بدء الجلسة

// تنظيف بيانات الإدخال (لحماية XSS و SQL)
function sanitize_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

// التحقق من الصلاحيات من قاعدة البيانات أو ملف ثابت
// هذه مجرد مثال بسيط، الأفضل عمل جدول Roles في قاعدة البيانات
function is_admin($user_id) {
    // هنا يجب أن تتصل بقاعدة البيانات وتفحص إذا كان هذا المستخدم لديه رتبة الأدمن
    // مثال بسيط: قائمة مسموح بها (ضع أيدي الأدمن الحقيقية هنا)
    $admin_ids = ['1506606160059564232']; // أضف كل أيدي الأدمن هنا
    return in_array($user_id, $admin_ids);
}

// دالة للتحقق من صحة الجلسة (يجب أن ترسلها من Frontend في Header)
function authenticate() {
    $headers = getallheaders();
    $auth_header = isset($headers['Authorization']) ? $headers['Authorization'] : '';
    
    if (preg_match('/Bearer\s(\S+)/', $auth_header, $matches)) {
        $token = $matches[1];
        // هنا يجب فك تشفير الـ token والتحقق منه
        // سنستخدم آلية بسيطة مؤقتاً: نخزن token عشوائي مرتبط بالجلسة
        if (isset($_SESSION['auth_token']) && $_SESSION['auth_token'] === $token) {
            return $_SESSION['user_id'];
        }
    }
    
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

// دالة خاصة للأدمن
function authenticate_admin() {
    $user_id = authenticate();
    if (!is_admin($user_id)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Forbidden: Admin only']);
        exit();
    }
    return $user_id;
}
?>
