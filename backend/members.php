<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

$MEMBERS_FILE = __DIR__ . '/members_data.json';
$VISITS_FILE  = __DIR__ . '/visits_data.json';

function readJson($file) {
    if (!file_exists($file)) return [];
    $data = json_decode(file_get_contents($file), true);
    return is_array($data) ? $data : [];
}

function writeJson($file, $data) {
    file_put_contents($file, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);
}

$method = $_SERVER['REQUEST_METHOD'];

// ===== GET =====
if ($method === 'GET') {
    $members     = readJson($MEMBERS_FILE);
    $visits      = readJson($VISITS_FILE);
    $todayVisits = $visits[date('Y-m-d')] ?? 0;

    // ترتيب حسب تاريخ التسجيل (الأحدث أولاً)
    usort($members, fn($a,$b) => strtotime($b['registeredAt']??'0') - strtotime($a['registeredAt']??'0'));

    echo json_encode([
        'success'     => true,
        'members'     => array_values($members),
        'todayVisits' => (int)$todayVisits,
        'total'       => count($members),
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// ===== POST =====
if ($method === 'POST') {
    $input  = json_decode(file_get_contents('php://input'), true);
    
    // دعم كلا الحالتين: userId أو discordId
    $userId = trim($input['userId'] ?? $input['discordId'] ?? '');
    $action = $input['action'] ?? '';

    if (!$userId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'userId مطلوب'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $username = trim($input['username'] ?? '');
    $avatar   = trim($input['avatar']   ?? '');
    $email    = trim($input['email']    ?? '');
    $roles    = is_array($input['roles'] ?? null) ? $input['roles'] : [];
    $now      = date('c');
    $today    = date('Y-m-d');

    // ===== تحديث الزيارات أولاً (بغض النظر عن وجود العضو) =====
    $visits = readJson($VISITS_FILE);
    $visits[$today] = ($visits[$today] ?? 0) + 1;
    if (count($visits) > 365) { 
        ksort($visits); 
        $visits = array_slice($visits, -365, 365, true); 
    }
    writeJson($VISITS_FILE, $visits);

    // ===== معالجة الأعضاء =====
    $members = readJson($MEMBERS_FILE);
    $found = false;
    
    foreach ($members as $i => $m) {
        if (($m['id'] ?? '') === $userId) {
            // تحديث بيانات العضو الموجود
            $members[$i]['lastSeen'] = $now;
            $members[$i]['totalVisits'] = ($members[$i]['totalVisits'] ?? 0) + 1;
            if ($username) $members[$i]['username'] = $username;
            if ($avatar)   $members[$i]['avatar']   = $avatar;
            if ($email)    $members[$i]['email']    = $email;
            if (!empty($roles)) $members[$i]['roles'] = $roles;
            $found = true;
            break;
        }
    }

    // إذا كان العضو غير موجود، أضفه (لا نحذف أبداً)
    if (!$found) {
        $newMember = [
            'id'           => $userId,
            'username'     => $username ?: 'مستخدم',
            'avatar'       => $avatar,
            'email'        => $email,
            'roles'        => $roles,
            'registeredAt' => $now,
            'lastSeen'     => $now,
            'totalVisits'  => 1,
        ];
        $members[] = $newMember;
    }

    // حفظ البيانات (لا يوجد حذف هنا أبداً)
    writeJson($MEMBERS_FILE, $members);

    echo json_encode(['success' => true], JSON_UNESCAPED_UNICODE);
    exit;
}

// ===== DELETE - إيقاف الحذف نهائياً =====
if ($method === 'DELETE') {
    // نمنع الحذف تماماً
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'حذف الأعضاء غير مسموح به'], JSON_UNESCAPED_UNICODE);
    exit;
}

http_response_code(405);
echo json_encode(['success' => false, 'error' => 'Method Not Allowed'], JSON_UNESCAPED_UNICODE);
?>
