<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PATCH, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$dataFile = __DIR__ . '/notifications_data.json';

if (!file_exists($dataFile)) {
    file_put_contents($dataFile, json_encode([], JSON_UNESCAPED_UNICODE));
}

// ========== GET - جلب الإشعارات لمستخدم ==========
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $userId = $_GET['userId'] ?? '';
    
    if (!$userId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'معرف المستخدم مطلوب']);
        exit();
    }
    
    $notifications = json_decode(file_get_contents($dataFile), true);
    if (!is_array($notifications)) $notifications = [];
    
    // فلترة إشعارات هذا المستخدم فقط
    $userNotifications = array_filter($notifications, function($n) use ($userId) {
        return $n['userId'] === $userId;
    });
    
    // ترتيب من الأحدث
    usort($userNotifications, function($a, $b) {
        return strtotime($b['createdAt']) - strtotime($a['createdAt']);
    });
    
    // عدد غير المقروءة
    $unreadCount = count(array_filter($userNotifications, function($n) {
        return !$n['read'];
    }));
    
    echo json_encode([
        'success' => true,
        'notifications' => array_values($userNotifications),
        'unreadCount' => $unreadCount
    ], JSON_UNESCAPED_UNICODE);
    exit();
}

// ========== POST - إضافة إشعار جديد ==========
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input || !isset($input['userId']) || !isset($input['message'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'بيانات ناقصة']);
        exit();
    }
    
    $notifications = json_decode(file_get_contents($dataFile), true);
    if (!is_array($notifications)) $notifications = [];
    
    $newNotification = [
        'id' => uniqid(),
        'userId' => $input['userId'],
        'type' => $input['type'] ?? 'info',
        'title' => $input['title'] ?? 'إشعار',
        'message' => $input['message'],
        'link' => $input['link'] ?? '',
        'read' => false,
        'createdAt' => date('Y-m-d H:i:s')
    ];
    
    array_unshift($notifications, $newNotification);
    file_put_contents($dataFile, json_encode($notifications, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    
    echo json_encode(['success' => true, 'notification' => $newNotification], JSON_UNESCAPED_UNICODE);
    exit();
}

// ========== PATCH - تعليم كمقروء ==========
if ($_SERVER['REQUEST_METHOD'] === 'PATCH') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $notifications = json_decode(file_get_contents($dataFile), true);
    if (!is_array($notifications)) $notifications = [];
    
    // تعليم الكل كمقروء
    if (isset($input['markAllRead']) && $input['markAllRead']) {
        $userId = $input['userId'] ?? '';
        foreach ($notifications as &$n) {
            if ($n['userId'] === $userId) {
                $n['read'] = true;
            }
        }
        file_put_contents($dataFile, json_encode($notifications, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        echo json_encode(['success' => true]);
        exit();
    }
    
    // تعليم واحد كمقروء
    if (isset($input['id'])) {
        foreach ($notifications as &$n) {
            if ($n['id'] === $input['id']) {
                $n['read'] = true;
                break;
            }
        }
        file_put_contents($dataFile, json_encode($notifications, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        echo json_encode(['success' => true]);
        exit();
    }
    
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'بيانات غير مكتملة']);
    exit();
}

http_response_code(405);
echo json_encode(['success' => false, 'error' => 'طريقة غير مدعومة']);
?>