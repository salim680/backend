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

if (!file_exists($membersFile)) {
    file_put_contents($membersFile, json_encode(['members' => []]));
}
if (!file_exists($visitsFile)) {
    file_put_contents($visitsFile, json_encode(['visits' => [], 'daily' => []]));
}

function recordVisit() {
    global $visitsFile;
    $data = json_decode(file_get_contents($visitsFile), true);
    $today = date('Y-m-d');
    $data['visits'][] = [
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'time' => date('Y-m-d H:i:s'),
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
    ];
    $data['daily'][$today] = ($data['daily'][$today] ?? 0) + 1;
    if (count($data['visits']) > 1000) $data['visits'] = array_slice($data['visits'], -1000);
    file_put_contents($visitsFile, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $members = json_decode(file_get_contents($membersFile), true);
    $visits = json_decode(file_get_contents($visitsFile), true);
    $today = date('Y-m-d');
    $yesterday = date('Y-m-d', strtotime('-1 day'));
    $onlineThreshold = date('Y-m-d H:i:s', strtotime('-5 minutes'));
    $weekAgo = date('Y-m-d', strtotime('-7 days'));

    $onlineMembers = array_filter($members['members'] ?? [], fn($m) => ($m['lastSeen'] ?? '') >= $onlineThreshold && ($m['status'] ?? '') !== 'banned');
    $newThisWeek = array_filter($members['members'] ?? [], fn($m) => ($m['joinedAt'] ?? '') >= $weekAgo);
    $bannedCount = count(array_filter($members['members'] ?? [], fn($m) => ($m['status'] ?? '') === 'banned'));
    $rolesCount = count(array_unique(array_column($members['members'] ?? [], 'role')));

    echo json_encode([
        'members' => $members['members'] ?? [],
        'stats' => [
            'total' => count($members['members'] ?? []),
            'todayVisits' => $visits['daily'][$today] ?? 0,
            'yesterdayVisits' => $visits['daily'][$yesterday] ?? 0,
            'online' => count($onlineMembers),
            'newThisWeek' => count($newThisWeek),
            'banned' => $bannedCount,
            'roles' => $rolesCount
        ]
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $members = json_decode(file_get_contents($membersFile), true);
    $discordId = $input['discordId'] ?? '';
    $found = false;
    foreach ($members['members'] as &$m) {
        if ($m['discordId'] === $discordId) {
            $m['username'] = $input['username'] ?? $m['username'];
            $m['lastSeen'] = date('Y-m-d H:i:s');
            $m['visits'] = ($m['visits'] ?? 0) + 1;
            $m['status'] = 'online';
            $found = true;
            break;
        }
    }
    if (!$found) {
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
            'browser' => $input['browser'] ?? 'Unknown',
            'device' => $input['device'] ?? 'Unknown',
            'avatarColor' => 'hsl(' . rand(0, 360) . ', 60%, 55%)',
            'notes' => ''
        ];
    }
    file_put_contents($membersFile, json_encode($members, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    recordVisit();
    echo json_encode(['success' => true]);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    $input = json_decode(file_get_contents('php://input'), true);
    $members = json_decode(file_get_contents($membersFile), true);
    foreach ($members['members'] as &$m) {
        if ($m['id'] === ($input['id'] ?? '')) {
            if ($input['action'] === 'ban') $m['status'] = 'banned';
            elseif ($input['action'] === 'unban') $m['status'] = 'online';
            elseif ($input['action'] === 'change_role') $m['role'] = $input['role'];
            break;
        }
    }
    file_put_contents($membersFile, json_encode($members, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    echo json_encode(['success' => true]);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    $input = json_decode(file_get_contents('php://input'), true);
    $members = json_decode(file_get_contents($membersFile), true);
    $members['members'] = array_values(array_filter($members['members'], fn($m) => $m['id'] !== ($input['id'] ?? '')));
    file_put_contents($membersFile, json_encode($members, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    echo json_encode(['success' => true]);
    exit();
}

http_response_code(405);
echo json_encode(['success' => false, 'error' => 'Method not allowed']);
?>
