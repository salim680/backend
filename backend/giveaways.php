<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PATCH, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$dataFile = __DIR__ . '/giveaways_data.json';

if (!file_exists($dataFile)) {
    file_put_contents($dataFile, json_encode([], JSON_UNESCAPED_UNICODE));
}

function parseDuration($duration) {
    $duration = strtolower(trim($duration));
    $total = 0;
    if (preg_match('/(\d+)\s*s/', $duration, $m)) $total += $m[1];
    if (preg_match('/(\d+)\s*m/', $duration, $m)) $total += $m[1] * 60;
    if (preg_match('/(\d+)\s*h/', $duration, $m)) $total += $m[1] * 3600;
    if (preg_match('/(\d+)\s*d/', $duration, $m)) $total += $m[1] * 86400;
    if ($total == 0 && is_numeric($duration)) $total = (int)$duration;
    if ($total == 0) $total = 86400;
    return $total;
}

function endGiveaway(&$gw) {
    $gw['status'] = 'ended';
    $gw['endedAt'] = date('Y-m-d H:i:s');
    $participants = $gw['participants'];
    $winnerCount = min($gw['winnerCount'], count($participants));
    if ($winnerCount > 0 && count($participants) > 0) {
        shuffle($participants);
        $gw['winners'] = array_slice($participants, 0, $winnerCount);
    }
}

// GET
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $giveaways = json_decode(file_get_contents($dataFile), true);
    if (!is_array($giveaways)) $giveaways = [];
    
    $now = time();
    $changed = false;
    foreach ($giveaways as &$gw) {
        if ($gw['status'] === 'active' && isset($gw['endTimestamp']) && $now >= $gw['endTimestamp']) {
            endGiveaway($gw);
            $changed = true;
        }
    }
    if ($changed) file_put_contents($dataFile, json_encode($giveaways, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    
    echo json_encode(['success' => true, 'giveaways' => $giveaways], JSON_UNESCAPED_UNICODE);
    exit();
}

// POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input || !isset($input['name']) || !isset($input['winners'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'بيانات ناقصة']);
        exit();
    }
    
    $giveaways = json_decode(file_get_contents($dataFile), true);
    if (!is_array($giveaways)) $giveaways = [];
    
    $durationSeconds = parseDuration($input['duration']);
    
    $newGiveaway = [
        'id' => uniqid(),
        'name' => $input['name'],
        'description' => $input['description'] ?? '',
        'duration' => $input['duration'] ?? '24 ساعة',
        'durationSeconds' => $durationSeconds,
        'endTimestamp' => time() + $durationSeconds,
        'winnerCount' => (int)$input['winners'],
        'participants' => [],
        'winners' => [],
        'status' => 'active',
        'createdAt' => date('Y-m-d H:i:s')
    ];
    
    array_unshift($giveaways, $newGiveaway);
    file_put_contents($dataFile, json_encode($giveaways, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    
    echo json_encode(['success' => true, 'giveaway' => $newGiveaway], JSON_UNESCAPED_UNICODE);
    exit();
}

// PATCH
if ($_SERVER['REQUEST_METHOD'] === 'PATCH') {
    $input = json_decode(file_get_contents('php://input'), true);
    $giveaways = json_decode(file_get_contents($dataFile), true);
    if (!is_array($giveaways)) $giveaways = [];
    
    foreach ($giveaways as &$gw) {
        if ($gw['id'] === $input['id']) {
            if (isset($input['action']) && $input['action'] === 'join') {
                if ($gw['status'] !== 'active') break;
                $userId = $input['userId'];
                $username = $input['username'];
                $alreadyJoined = false;
                foreach ($gw['participants'] as $p) {
                    if ($p['id'] === $userId) { $alreadyJoined = true; break; }
                }
                if (!$alreadyJoined) {
                    $gw['participants'][] = ['id' => $userId, 'username' => $username, 'joinedAt' => date('Y-m-d H:i:s')];
                }
            }
            if (isset($input['action']) && $input['action'] === 'end') {
                endGiveaway($gw);
            }
            break;
        }
    }
    
    file_put_contents($dataFile, json_encode($giveaways, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    echo json_encode(['success' => true, 'giveaways' => $giveaways], JSON_UNESCAPED_UNICODE);
    exit();
}

// DELETE
if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    $input = json_decode(file_get_contents('php://input'), true);
    $giveaways = json_decode(file_get_contents($dataFile), true);
    $giveaways = array_values(array_filter($giveaways, function($g) use ($input) {
        return $g['id'] !== $input['id'];
    }));
    file_put_contents($dataFile, json_encode($giveaways, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    echo json_encode(['success' => true]);
    exit();
}

http_response_code(405);
echo json_encode(['success' => false, 'error' => 'طريقة غير مدعومة']);
?>