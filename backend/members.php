<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

// الكتابة مباشرة في نفس المجلد (بدون /data/)
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
    $userId = trim($input['userId'] ?? $input['discordId'] ?? '');

    if (!$userId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'userId مطلوب'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $username = trim($input['username'] ?? '');
    $avatar   = trim($input['avatar']   ?? '');
    $roles    = is_array($input['roles'] ?? null) ? $input['roles'] : [];
    $now      = date('c');
    $today    = date('Y-m-d');

    $members = readJson($MEMBERS_FILE);
    $idx = -1;
    foreach ($members as $i => $m) {
        if (($m['id'] ?? '') === $userId) { $idx = $i; break; }
    }

    if ($idx === -1) {
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
        $members[$idx]['lastSeen']    = $now;
        $members[$idx]['totalVisits'] = ($members[$idx]['totalVisits'] ?? 0) + 1;
        if ($username) $members[$idx]['username'] = $username;
        if ($avatar)   $members[$idx]['avatar']   = $avatar;
        if ($roles)    $members[$idx]['roles']     = $roles;
    }

    writeJson($MEMBERS_FILE, $members);

    $visits = readJson($VISITS_FILE);
    $visits[$today] = ($visits[$today] ?? 0) + 1;
    if (count($visits) > 90) { ksort($visits); $visits = array_slice($visits, -90, 90, true); }
    writeJson($VISITS_FILE, $visits);

    echo json_encode(['success' => true], JSON_UNESCAPED_UNICODE);
    exit;
}

http_response_code(405);
echo json_encode(['success' => false, 'error' => 'Method Not Allowed'], JSON_UNESCAPED_UNICODE);
