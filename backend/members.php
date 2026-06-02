<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$membersFile = __DIR__ . '/members_data.json';
$visitsFile = __DIR__ . '/visits_data.json';

if (!file_exists($membersFile)) {
    file_put_contents($membersFile, json_encode(['members' => []]));
}
if (!file_exists($visitsFile)) {
    file_put_contents($visitsFile, json_encode(['visits' => [], 'daily' => []]));
}

// تسجيل زيارة + تحديث آخر ظهور (يُستدعى من أي صفحة في الموقع)
function recordVisitAndUpdate($discordId = null) {
    global $visitsFile, $membersFile;
    // تسجيل الزيارة العامة
    $vdata = json_decode(file_get_contents($visitsFile), true);
    $today = date('Y-m-d');
    $vdata['visits'][] = ['time' => date('Y-m-d H:i:s'), 'ip' => $_SERVER['REMOTE_ADDR'] ?? ''];
    $vdata['daily'][$today] = ($vdata['daily'][$today] ?? 0) + 1;
    if (count($vdata['visits']) > 1000) $vdata['visits'] = array_slice($vdata['visits'], -1000);
    file_put_contents($visitsFile, json_encode($vdata, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

    // تحديث آخر ظهور للعضو إذا تم تمرير معرفه
    if ($discordId) {
        $mdata = json_decode(file_get_contents($membersFile), true);
        foreach ($mdata['members'] as &$m) {
            if ($m['discordId'] === $discordId) {
                $m['lastSeen'] = date('Y-m-d H:i:s');
                $m['visits'] = ($m['visits'] ?? 0) + 1;
                break;
            }
        }
        file_put_contents($membersFile, json_encode($mdata, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }
}

// GET – جلب الأعضاء والإحصائيات
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $members = json_decode(file_get_contents($membersFile), true);
    $visits = json_decode(file_get_contents($visitsFile), true);
    $today = date('Y-m-d');
    $onlineThreshold = date('Y-m-d H:i:s', strtotime('-5 minutes'));

    // تحديث حالة المتصلين (بدون حظر)
    foreach ($members['members'] as &$m) {
        $m['isOnline'] = isset($m['lastSeen']) && $m['lastSeen'] >= $onlineThreshold;
    }

    echo json_encode([
        'members' => $members['members'] ?? [],
        'stats' => [
            'total' => count($members['members'] ?? []),
            'todayVisits' => $visits['daily'][$today] ?? 0
        ]
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit();
}

// POST – تسجيل عضو جديد أو تحديث آخر ظهور له
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $discordId = $input['discordId'] ?? '';
    if (!$discordId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'discordId مطلوب']);
        exit();
    }

    $members = json_decode(file_get_contents($membersFile), true);
    $found = false;
    foreach ($members['members'] as &$m) {
        if ($m['discordId'] === $discordId) {
            // تحديث بيانات العضو الموجود
            $m['username'] = $input['username'] ?? $m['username'];
            $m['lastSeen'] = date('Y-m-d H:i:s');
            $m['visits'] = ($m['visits'] ?? 0) + 1;
            $found = true;
            break;
        }
    }
    if (!$found) {
        // إضافة عضو جديد
        $members['members'][] = [
            'id' => 'user_' . uniqid(),
            'discordId' => $discordId,
            'username' => $input['username'] ?? 'زائر',
            'discriminator' => $input['discriminator'] ?? '0000',
            'email' => $input['email'] ?? '',
            'role' => 'member',          // كل الأعضاء الجدد "عضو"
            'visits' => 1,
            'lastSeen' => date('Y-m-d H:i:s'),
            'joinedAt' => date('Y-m-d'),
            'avatarColor' => 'hsl(' . rand(0, 360) . ', 60%, 55%)'
        ];
    }
    file_put_contents($membersFile, json_encode($members, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

    // تسجيل الزيارة العامة أيضاً
    recordVisitAndUpdate($discordId);

    echo json_encode(['success' => true]);
    exit();
}

// PUT – تحديث الرتبة فقط (لا حظر)
if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    $input = json_decode(file_get_contents('php://input'), true);
    $members = json_decode(file_get_contents($membersFile), true);
    foreach ($members['members'] as &$m) {
        if ($m['id'] === ($input['id'] ?? '')) {
            if (isset($input['role'])) $m['role'] = $input['role'];
            break;
        }
    }
    file_put_contents($membersFile, json_encode($members, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    echo json_encode(['success' => true]);
    exit();
}

// أي طريقة أخرى
http_response_code(405);
echo json_encode(['success' => false, 'error' => 'Method not allowed']);
?>
