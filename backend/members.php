<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$membersFile = __DIR__ . '/members_data.json';
$visitsFile = __DIR__ . '/visits_data.json';

// إنشاء الملفات إذا لم تكن موجودة
if (!file_exists($membersFile)) {
    file_put_contents($membersFile, json_encode(['members' => []]));
}
if (!file_exists($visitsFile)) {
    file_put_contents($visitsFile, json_encode(['visits' => [], 'daily' => []]));
}

// تسجيل الزيارة الحالية
function recordVisit() {
    global $visitsFile;
    $data = json_decode(file_get_contents($visitsFile), true);
    
    $today = date('Y-m-d');
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
    
    // تسجيل الزيارة
    $data['visits'][] = [
        'ip' => $ip,
        'time' => date('Y-m-d H:i:s'),
        'user_agent' => $userAgent,
        'page' => $_SERVER['HTTP_REFERER'] ?? 'direct'
    ];
    
    // تحديث عداد اليوم
    if (!isset($data['daily'][$today])) {
        $data['daily'][$today] = 0;
    }
    $data['daily'][$today]++;
    
    // الاحتفاظ بآخر 1000 زيارة فقط
    if (count($data['visits']) > 1000) {
        $data['visits'] = array_slice($data['visits'], -1000);
    }
    
    file_put_contents($visitsFile, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

// GET - جلب كل الأعضاء مع الإحصائيات
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $members = json_decode(file_get_contents($membersFile), true);
    $visits = json_decode(file_get_contents($visitsFile), true);
    
    $today = date('Y-m-d');
    $yesterday = date('Y-m-d', strtotime('-1 day'));
    
    // حسب الأعضاء المتصلين الآن (آخر 5 دقائق)
    $onlineThreshold = date('Y-m-d H:i:s', strtotime('-5 minutes'));
    $onlineMembers = array_filter($members['members'], function($m) use ($onlineThreshold) {
        return isset($m['lastSeen']) && $m['lastSeen'] >= $onlineThreshold && $m['status'] !== 'banned';
    });
    
    // حساب الجدد هذا الأسبوع
    $weekAgo = date('Y-m-d', strtotime('-7 days'));
    $newThisWeek = array_filter($members['members'], function($m) use ($weekAgo) {
        return isset($m['joinedAt']) && $m['joinedAt'] >= $weekAgo;
    });
    
    $response = [
        'members' => $members['members'],
        'stats' => [
            'total' => count($members['members']),
            'todayVisits' => $visits['daily'][$today] ?? 0,
            'yesterdayVisits' => $visits['daily'][$yesterday] ?? 0,
            'online' => count($onlineMembers),
            'newThisWeek' => count($newThisWeek),
            'banned' => count(array_filter($members['members'], fn($m) => ($m['status'] ?? '') === 'banned')),
            'roles' => count(array_unique(array_column($members['members'], 'role')))
        ]
    ];
    
    echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}

// POST - إضافة/تحديث عضو (عند ربط الدسكورد)
elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $members = json_decode(file_get_contents($membersFile), true);
    
    $discordId = $input['discordId'] ?? '';
    
    // البحث عن عضو موجود بنفس معرف الدسكورد
    $found = false;
    foreach ($members['members'] as &$member) {
        if ($member['discordId'] === $discordId) {
            // تحديث بيانات العضو الموجود
            $member['username'] = $input['username'] ?? $member['username'];
            $member['discriminator'] = $input['discriminator'] ?? $member['discriminator'];
            $member['email'] = $input['email'] ?? $member['email'] ?? '';
            $member['lastSeen'] = date('Y-m-d H:i:s');
            $member['visits'] = ($member['visits'] ?? 0) + 1;
            $member['ip'] = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
            $member['browser'] = $input['browser'] ?? getBrowser();
            $member['device'] = $input['device'] ?? getDevice();
            $member['lastPage'] = $input['page'] ?? '/';
            $member['status'] = 'online';
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
            'role' => 'member',
            'status' => 'online',
            'visits' => 1,
            'activityScore' => 0,
            'totalTimeSpent' => 0,
            'giveawayWins' => 0,
            'applications' => 0,
            'lastSeen' => date('Y-m-d H:i:s'),
            'joinedAt' => date('Y-m-d'),
            'linkedAt' => date('Y-m-d'),
            'lastPage' => $input['page'] ?? '/',
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'browser' => $input['browser'] ?? getBrowser(),
            'device' => $input['device'] ?? getDevice(),
            'avatarColor' => 'hsl(' . rand(0, 360) . ', 60%, 55%)',
            'notes' => ''
        ];
    }
    
    file_put_contents($membersFile, json_encode($members, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    recordVisit();
    
    echo json_encode(['success' => true, 'message' => 'تم تسجيل العضو بنجاح']);
}

// PUT - تحديث بيانات عضو (رتبة، حظر، إلخ)
elseif ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    $input = json_decode(file_get_contents('php://input'), true);
    $members = json_decode(file_get_contents($membersFile), true);
    
    $userId = $input['id'] ?? '';
    $action = $input['action'] ?? '';
    
    foreach ($members['members'] as &$member) {
        if ($member['id'] === $userId) {
            switch ($action) {
                case 'ban':
                    $member['status'] = 'banned';
                    break;
                case 'unban':
                    $member['status'] = 'offline';
                    break;
                case 'change_role':
                    $member['role'] = $input['role'] ?? $member['role'];
                    break;
                case 'add_note':
                    $member['notes'] = $input['note'] ?? '';
                    break;
            }
            break;
        }
    }
    
    file_put_contents($membersFile, json_encode($members, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    echo json_encode(['success' => true]);
}

// DELETE - حذف عضو
elseif ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    $input = json_decode(file_get_contents('php://input'), true);
    $members = json_decode(file_get_contents($membersFile), true);
    
    $userId = $input['id'] ?? '';
    
    $members['members'] = array_filter($members['members'], function($m) use ($userId) {
        return $m['id'] !== $userId;
    });
    
    // إعادة ترقيم
    $members['members'] = array_values($members['members']);
    
    file_put_contents($membersFile, json_encode($members, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    echo json_encode(['success' => true]);
}

// دوال مساعدة
function getBrowser() {
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    if (strpos($ua, 'Chrome') !== false) return 'Chrome';
    if (strpos($ua, 'Firefox') !== false) return 'Firefox';
    if (strpos($ua, 'Safari') !== false) return 'Safari';
    if (strpos($ua, 'Edge') !== false) return 'Edge';
    if (strpos($ua, 'Opera') !== false) return 'Opera';
    return 'Unknown';
}

function getDevice() {
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    if (strpos($ua, 'Windows') !== false) return 'Windows';
    if (strpos($ua, 'Mac') !== false) return 'Mac';
    if (strpos($ua, 'iPhone') !== false) return 'iPhone';
    if (strpos($ua, 'Android') !== false) return 'Android';
    if (strpos($ua, 'Linux') !== false) return 'Linux';
    return 'Unknown';
}
?>
