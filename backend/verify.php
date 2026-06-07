<?php
// verify.php
require_once 'api_config.php';

$user_id = authenticate();

// جلب بيانات المستخدم من الجلسة
echo json_encode([
    'success' => true,
    'user' => [
        'id' => $_SESSION['user_id'],
        'username' => $_SESSION['username'],
        'avatar' => $_SESSION['avatar'],
        'roles' => $_SESSION['roles']
    ]
]);
?>
