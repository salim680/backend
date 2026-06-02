<?php
date_default_timezone_set('Asia/Riyadh'); // ضبط الوقت حسب السعودية

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
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

// دالة تسجيل الزيارة (مرة واحدة لكل جلسة يومياً)
function recordDailyVisit() {
    global $visitsFile;
    $vdata = json_decode(file_get_contents($visitsFile), true);
    $today = date('Y-m-d');
    // نستخدم session ID لمنع التكرار المتعدد لنفس المستخدم في نفس اليوم
    session_start();
    $sessionKey = 'visit_recorded_' . $today;
    if (!isset($_SESSION[$sessionKey])) {
        $vdata['daily'][$today] = ($vdata['daily'][$today] ?? 0) + 1;
        $_SESSION[$sessionKey] = true;
        file_put_contents($visitsFile, json_encode($vdata, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }
    session_write_close();
}

// GET – جلب الأعضاء والإحصائيات
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $members = json_decode(file_get_contents($membersFile), true);
    $visits = json_decode(file_get_contents($visitsFile), true);
    $today = date('Y-m-d');
    $onlineThreshold = date('Y-m-d H:i:s', strtotime('-5 minutes'));

    foreach ($members['members'] as &$m) {
        $m['isOnline'] = isset($m['lastSeen']) && $m['lastSeen'] >= $onlineThreshold;
        // تحويل lastSeen إلى timestamp للفرونت
        $m['lastSeenTimestamp'] = isset($m['lastSeen']) ? strtotime($m['lastSeen']) : null;
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

// POST – تسجيل/تحديث عضو (عند تسجيل الدخول أو عند فتح أي صفحة)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $discordId = $input['discordId'] ?? '';
    if (!$discordId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'discordId مطلوب']);
        exit();
    }

    // تسجيل الزيارة اليومية (مرة واحدة لكل جلسة)
    recordDailyVisit();

    $members = json_decode(file_get_contents($membersFile), true);
    $found = false;
    foreach ($members['members'] as &$m) {
        if ($m['discordId'] === $discordId) {
            // تحديث بيانات العضو الموجود
            $m['username'] = $input['username'] ?? $m['username'];
            $m['avatar'] = $input['avatar'] ?? $m['avatar'] ?? null;
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
            'username' => $input['username'] ?? 'مستخدم',
            'discriminator' => $input['discriminator'] ?? '0000',
            'email' => $input['email'] ?? '',
            'avatar' => $input['avatar'] ?? null,
            'role' => 'member',      // الكل "عضو" – الرتبة تُحدد من الأدوار لاحقاً
            'visits' => 1,
            'lastSeen' => date('Y-m-d H:i:s'),
            'joinedAt' => date('Y-m-d'),
            'avatarColor' => 'hsl(' . rand(0, 360) . ', 60%, 55%)'
        ];
    }
    file_put_contents($membersFile, json_encode($members, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

    echo json_encode(['success' => true]);
    exit();
}

http_response_code(405);
echo json_encode(['success' => false, 'error' => 'Method not allowed']);
?>
