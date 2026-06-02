<?php
// =============================================
// members.php - إدارة الأعضاء - عائلة العدلي
// Deploy على: Render
// =============================================

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// ===== مسارات ملفات البيانات =====
define('DATA_DIR',    __DIR__ . '/data/');
define('MEMBERS_FILE', DATA_DIR . 'members.json');
define('VISITS_FILE',  DATA_DIR . 'visits.json');

// إنشاء مجلد البيانات إن لم يكن موجوداً
if (!is_dir(DATA_DIR)) {
    mkdir(DATA_DIR, 0755, true);
}

// ===== دوال مساعدة =====

function readJson(string $file): array {
    if (!file_exists($file)) return [];
    $raw = file_get_contents($file);
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

function writeJson(string $file, array $data): bool {
    return file_put_contents($file, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)) !== false;
}

function getTodayKey(): string {
    return date('Y-m-d');
}

// ===== المعالجة الرئيسية =====

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

// ===== GET: قائمة الأعضاء والإحصائيات =====
if ($method === 'GET' && $action === 'list') {

    $members = readJson(MEMBERS_FILE);
    $visits  = readJson(VISITS_FILE);

    $todayKey     = getTodayKey();
    $todayVisits  = $visits[$todayKey] ?? 0;

    // ترتيب الأعضاء: الأحدث تسجيلاً أولاً
    usort($members, function($a, $b) {
        $ta = strtotime($a['registeredAt'] ?? '0');
        $tb = strtotime($b['registeredAt'] ?? '0');
        return $tb - $ta;
    });

    echo json_encode([
        'success'      => true,
        'members'      => array_values($members),
        'todayVisits'  => (int)$todayVisits,
        'total'        => count($members),
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// ===== POST: تسجيل زيارة / تسجيل عضو جديد =====
if ($method === 'POST') {

    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input || empty($input['userId'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'userId مطلوب'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $action_post = $input['action'] ?? 'visit';

    if ($action_post === 'visit') {

        $userId   = trim($input['userId']);
        $username = trim($input['username'] ?? '');
        $avatar   = trim($input['avatar']   ?? '');
        $roles    = is_array($input['roles']) ? $input['roles'] : [];
        $now      = date('c'); // ISO 8601
        $todayKey = getTodayKey();

        // ===== تحديث سجل الأعضاء =====
        $members = readJson(MEMBERS_FILE);
        $idx     = -1;

        foreach ($members as $i => $m) {
            if (($m['id'] ?? '') === $userId) { $idx = $i; break; }
        }

        if ($idx === -1) {
            // عضو جديد
            $members[] = [
                'id'           => $userId,
                'username'     => $username,
                'avatar'       => $avatar,
                'roles'        => $roles,
                'registeredAt' => $now,
                'lastSeen'     => $now,
                'totalVisits'  => 1,
            ];
        } else {
            // تحديث بيانات موجودة
            $members[$idx]['lastSeen']    = $now;
            $members[$idx]['totalVisits'] = ($members[$idx]['totalVisits'] ?? 0) + 1;
            if ($username) $members[$idx]['username'] = $username;
            if ($avatar)   $members[$idx]['avatar']   = $avatar;
            if ($roles)    $members[$idx]['roles']     = $roles;
        }

        writeJson(MEMBERS_FILE, $members);

        // ===== تحديث عداد زيارات اليوم =====
        $visits = readJson(VISITS_FILE);
        $visits[$todayKey] = ($visits[$todayKey] ?? 0) + 1;

        // الاحتفاظ فقط بآخر 90 يوم لتوفير المساحة
        if (count($visits) > 90) {
            ksort($visits);
            $visits = array_slice($visits, -90, 90, true);
        }

        writeJson(VISITS_FILE, $visits);

        echo json_encode([
            'success'     => true,
            'action'      => 'visit_recorded',
            'totalVisits' => $members[$idx === -1 ? count($members)-1 : $idx]['totalVisits'],
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'action غير معروف'], JSON_UNESCAPED_UNICODE);
    exit;
}

// ===== طلب غير مدعوم =====
http_response_code(405);
echo json_encode(['success' => false, 'error' => 'Method Not Allowed'], JSON_UNESCAPED_UNICODE);
