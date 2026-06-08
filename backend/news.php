<?php
// عرض الأخطاء للتشخيص
error_reporting(E_ALL);
ini_set('display_errors', 1);

// السماح بالوصول
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json; charset=utf-8');

// معالجة OPTIONS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// ملف التخزين
$newsFile = __DIR__ . '/news_data.json';

// إنشاء الملف إذا مش موجود
if (!file_exists($newsFile)) {
    file_put_contents($newsFile, '[]');
}

// ========== GET - جلب الأخبار ==========
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $news = json_decode(file_get_contents($newsFile), true);
    if (!is_array($news)) $news = [];
    
    echo json_encode([
        'success' => true,
        'news' => $news
    ], JSON_UNESCAPED_UNICODE);
    exit();
}

// ========== POST - إضافة خبر ==========
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rawData = file_get_contents('php://input');
    $input = json_decode($rawData, true);
    
    if (!$input) {
        $input = $_POST;
    }
    
    if (!$input) {
        $input = [
            'title' => $_POST['title'] ?? $_REQUEST['title'] ?? '',
            'description' => $_POST['description'] ?? $_REQUEST['description'] ?? '',
            'date' => $_POST['date'] ?? $_REQUEST['date'] ?? '',
            'image' => $_POST['image'] ?? $_REQUEST['image'] ?? '',
            'author' => $_POST['author'] ?? $_REQUEST['author'] ?? 'مسؤول'
        ];
    }
    
    if (empty($input['title']) || empty($input['description']) || empty($input['date'])) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'البيانات ناقصة',
            'received' => $input
        ], JSON_UNESCAPED_UNICODE);
        exit();
    }
    
    $news = json_decode(file_get_contents($newsFile), true);
    if (!is_array($news)) $news = [];
    
    $newItem = [
        'id' => uniqid(),
        'title' => $input['title'],
        'image' => $input['image'] ?? '',
        'description' => $input['description'],
        'date' => $input['date'],
        'author' => $input['author'] ?? 'مسؤول',
        'createdAt' => date('Y-m-d H:i:s'),
        'likes' => 0
    ];
    
    array_unshift($news, $newItem);
    
    $saved = file_put_contents($newsFile, json_encode($news, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    
    if ($saved === false) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'فشل حفظ الملف. تأكد من صلاحيات المجلد'
        ], JSON_UNESCAPED_UNICODE);
        exit();
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'تم النشر بنجاح',
        'news' => $newItem
    ], JSON_UNESCAPED_UNICODE);
    exit();
}

// ========== PUT - تحديث الإعجابات ==========
if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    $rawData = file_get_contents('php://input');
    $input = json_decode($rawData, true);
    
    if (!$input || !isset($input['newsId'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'معرف الخبر مطلوب']);
        exit();
    }
    
    $news = json_decode(file_get_contents($newsFile), true);
    if (!is_array($news)) $news = [];
    
    $found = false;
    foreach ($news as &$item) {
        if ($item['id'] === $input['newsId']) {
            $item['likes'] = ($item['likes'] ?? 0) + 1;
            $found = true;
            $updatedLikes = $item['likes'];
            break;
        }
    }
    
    if (!$found) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'الخبر غير موجود']);
        exit();
    }
    
    file_put_contents($newsFile, json_encode($news, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    
    echo json_encode([
        'success' => true,
        'likes' => $updatedLikes
    ], JSON_UNESCAPED_UNICODE);
    exit();
}

// ========== DELETE - حذف خبر ==========
if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    $rawData = file_get_contents('php://input');
    $input = json_decode($rawData, true);
    
    if (!$input || !isset($input['id'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'معرف الخبر مطلوب']);
        exit();
    }
    
    $news = json_decode(file_get_contents($newsFile), true);
    $news = array_filter($news, function($item) use ($input) {
        return $item['id'] !== $input['id'];
    });
    
    file_put_contents($newsFile, json_encode(array_values($news), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    
    echo json_encode(['success' => true, 'message' => 'تم حذف الخبر']);
    exit();
}

// ========== PATCH - تعديل خبر ==========
if ($_SERVER['REQUEST_METHOD'] === 'PATCH') {
    $rawData = file_get_contents('php://input');
    $input = json_decode($rawData, true);
    
    if (!$input || !isset($input['id'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'معرف الخبر مطلوب']);
        exit();
    }
    
    $news = json_decode(file_get_contents($newsFile), true);
    $found = false;
    
    foreach ($news as &$item) {
        if ($item['id'] === $input['id']) {
            if (isset($input['title'])) $item['title'] = $input['title'];
            if (isset($input['image'])) $item['image'] = $input['image'];
            if (isset($input['description'])) $item['description'] = $input['description'];
            if (isset($input['date'])) $item['date'] = $input['date'];
            $found = true;
            break;
        }
    }
    
    if (!$found) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'الخبر غير موجود']);
        exit();
    }
    
    file_put_contents($newsFile, json_encode($news, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    
    echo json_encode(['success' => true, 'message' => 'تم تعديل الخبر']);
    exit();
}

// طريقة غير مدعومة
http_response_code(405);
echo json_encode(['success' => false, 'error' => 'طريقة غير مدعومة']);
?>
