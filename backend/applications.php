<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PATCH, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$dataFile = __DIR__ . '/applications_data.json';
$statusFile = __DIR__ . '/application_status.json';
$uploadsDir = __DIR__ . '/../uploads/applications/';

// إنشاء الملفات
if (!file_exists($dataFile)) {
    file_put_contents($dataFile, json_encode([], JSON_UNESCAPED_UNICODE));
}
if (!file_exists($statusFile)) {
    $defaultStatus = [
        'family' => 'open',
        'leader' => 'open',
        'designer' => 'open',
        'trader' => 'open'
    ];
    file_put_contents($statusFile, json_encode($defaultStatus, JSON_UNESCAPED_UNICODE));
}
if (!is_dir($uploadsDir)) {
    mkdir($uploadsDir, 0777, true);
}

// ========== GET ==========
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (isset($_GET['action']) && $_GET['action'] === 'status') {
        $status = json_decode(file_get_contents($statusFile), true);
        echo json_encode(['success' => true, 'status' => $status], JSON_UNESCAPED_UNICODE);
        exit();
    }
    
    $applications = json_decode(file_get_contents($dataFile), true);
    if (!is_array($applications)) $applications = [];
    
    echo json_encode(['success' => true, 'applications' => $applications], JSON_UNESCAPED_UNICODE);
    exit();
}

// ========== POST ==========
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $type = $_POST['type'] ?? 'family';
    
    // التحقق من حالة هذا النوع فقط
    $status = json_decode(file_get_contents($statusFile), true);
    $currentStatus = $status[$type] ?? 'open';
    
    if ($currentStatus !== 'open') {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'هذا التقديم مغلق حالياً'], JSON_UNESCAPED_UNICODE);
        exit();
    }
    
    $name = $_POST['name'] ?? '';
    $gameId = $_POST['gameId'] ?? '';
    $discordId = $_POST['discordId'] ?? '';
    $discordUsername = $_POST['discordUsername'] ?? '';
    $extraFields = json_decode($_POST['extraFields'] ?? '{}', true);
    
    $hoursImage = null;
    $historyImage = null;
    $extraImage = null;
    
    if (isset($_FILES['hoursImage']) && $_FILES['hoursImage']['error'] === UPLOAD_ERR_OK) {
        $ext = pathinfo($_FILES['hoursImage']['name'], PATHINFO_EXTENSION);
        $hoursImage = 'hours_' . time() . '_' . uniqid() . '.' . $ext;
        move_uploaded_file($_FILES['hoursImage']['tmp_name'], $uploadsDir . $hoursImage);
    }
    
    if (isset($_FILES['historyImage']) && $_FILES['historyImage']['error'] === UPLOAD_ERR_OK) {
        $ext = pathinfo($_FILES['historyImage']['name'], PATHINFO_EXTENSION);
        $historyImage = 'history_' . time() . '_' . uniqid() . '.' . $ext;
        move_uploaded_file($_FILES['historyImage']['tmp_name'], $uploadsDir . $historyImage);
    }
    
    if (isset($_FILES['extraImage']) && $_FILES['extraImage']['error'] === UPLOAD_ERR_OK) {
        $ext = pathinfo($_FILES['extraImage']['name'], PATHINFO_EXTENSION);
        $extraImage = 'extra_' . time() . '_' . uniqid() . '.' . $ext;
        move_uploaded_file($_FILES['extraImage']['tmp_name'], $uploadsDir . $extraImage);
    }
    
    if (!$name || !$discordId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'جميع الحقول مطلوبة'], JSON_UNESCAPED_UNICODE);
        exit();
    }
    
    $applications = json_decode(file_get_contents($dataFile), true);
    if (!is_array($applications)) $applications = [];
    
    $newApp = [
        'id' => uniqid(),
        'type' => $type,
        'name' => $name,
        'gameId' => $gameId,
        'discordId' => $discordId,
        'discordUsername' => $discordUsername,
        'hoursImage' => $hoursImage ? '/mta-family/uploads/applications/' . $hoursImage : null,
        'historyImage' => $historyImage ? '/mta-family/uploads/applications/' . $historyImage : null,
        'extraImage' => $extraImage ? '/mta-family/uploads/applications/' . $extraImage : null,
        'extraFields' => $extraFields,
        'status' => 'pending',
        'adminNote' => '',
        'createdAt' => date('Y-m-d H:i:s'),
        'updatedAt' => date('Y-m-d H:i:s')
    ];
    
    array_unshift($applications, $newApp);
    file_put_contents($dataFile, json_encode($applications, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    
    echo json_encode(['success' => true, 'message' => 'تم تقديم طلبك بنجاح', 'application' => $newApp], JSON_UNESCAPED_UNICODE);
    exit();
}

// ========== PATCH ==========
if ($_SERVER['REQUEST_METHOD'] === 'PATCH') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    // تغيير حالة نوع معين
    if (isset($input['action']) && $input['action'] === 'toggleTypeStatus') {
        $type = $input['type'] ?? 'family';
        $newStatus = $input['status'] ?? 'open';
        
        $status = json_decode(file_get_contents($statusFile), true);
        $status[$type] = $newStatus;
        file_put_contents($statusFile, json_encode($status, JSON_UNESCAPED_UNICODE));
        
        echo json_encode(['success' => true, 'status' => $status], JSON_UNESCAPED_UNICODE);
        exit();
    }
    
    // تحديث طلب معين
    if (isset($input['id'])) {
        $applications = json_decode(file_get_contents($dataFile), true);
        if (!is_array($applications)) $applications = [];
        
        $found = false;
        foreach ($applications as &$app) {
            if ($app['id'] === $input['id']) {
                if (isset($input['status'])) $app['status'] = $input['status'];
                if (isset($input['adminNote'])) $app['adminNote'] = $input['adminNote'];
                $app['updatedAt'] = date('Y-m-d H:i:s');
                $found = true;
                break;
            }
        }
        
        if (!$found) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'الطلب غير موجود'], JSON_UNESCAPED_UNICODE);
            exit();
        }
        
        file_put_contents($dataFile, json_encode($applications, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        echo json_encode(['success' => true, 'message' => 'تم تحديث الطلب'], JSON_UNESCAPED_UNICODE);
        exit();
    }
    
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'بيانات غير مكتملة'], JSON_UNESCAPED_UNICODE);
    exit();
}

// ========== DELETE ==========
if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    $rawData = file_get_contents('php://input');
    $input = json_decode($rawData, true);
    
    if (!$input || !isset($input['id'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'معرف التقديم مطلوب'], JSON_UNESCAPED_UNICODE);
        exit();
    }
    
    $applications = json_decode(file_get_contents($dataFile), true);
    if (!is_array($applications)) $applications = [];
    
    $filtered = array_filter($applications, function($app) use ($input) {
        return $app['id'] !== $input['id'];
    });
    
    $applications = array_values($filtered);
    file_put_contents($dataFile, json_encode($applications, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    
    echo json_encode(['success' => true, 'message' => 'تم حذف التقديم'], JSON_UNESCAPED_UNICODE);
    exit();
}

http_response_code(405);
echo json_encode(['success' => false, 'error' => 'طريقة غير مدعومة'], JSON_UNESCAPED_UNICODE);
?>