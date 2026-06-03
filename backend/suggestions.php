<?php
// إعداد الرؤوس البرمجية للسماح بالاتصال الخارجي (CORS) وحفظ المدخلات
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST, GET, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

$file = 'suggestions_data.json';

// تهيئة الملف إذا لم يكن موجوداً
if (!file_exists($file)) {
    file_put_contents($file, json_encode([]));
}

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $data = json_decode(file_get_contents($file), true);
    echo json_encode(["success" => true, "suggestions" => $data]);
    exit;
}

if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!isset($input['username']) || !isset($input['content'])) {
        echo json_encode(["success" => false, "error" => "بيانات ناقصة"]);
        exit;
    }
    
    $data = json_decode(file_get_contents($file), true);
    
    $newSuggestion = [
        "id" => time() . rand(10, 99),
        "username" => $input['username'],
        "content" => $input['content'],
        "date" => date('Y-m-d H:i')
    ];
    
    array_unshift($data, $newSuggestion);
    file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    
    echo json_encode(["success" => true]);
    exit;
}

if ($method === 'DELETE') {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!isset($input['id'])) {
        echo json_encode(["success" => false, "error" => "المعرف مطلوب"]);
        exit;
    }
    
    $data = json_decode(file_get_contents($file), true);
    $filteredData = array_filter($data, function($item) use ($input) {
        return $item['id'] != $input['id'];
    });
    
    file_put_contents($file, json_encode(array_values($filteredData), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    echo json_encode(["success" => true]);
    exit;
}
?>
