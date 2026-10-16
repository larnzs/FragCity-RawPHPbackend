<?php

require_once __DIR__ . '/../settings/loader.php';
session_start();

header('Content-Type: application/json; charset=utf-8');



$cfg=fragcity_settings('files.agent.agent_login',array());
$profile=isset($cfg['database_profile'])?$cfg['database_profile']:'main';
$pdo=fragcity_create_pdo($profile);
if(!$pdo){http_response_code(503);echo json_encode(array('status'=>'error','message'=>'Database unavailable or not configured.'));exit;}

$data = json_decode(file_get_contents("php://input"), true);



$loginId = trim($data['login_id'] ?? '');

$password = $data['password'] ?? '';



if (empty($loginId) || empty($password)) {

    echo json_encode(['status' => 'error', 'message' => 'Username and password are required.']);

    exit;

}



if (preg_match('/^[0-9]{17}$/', $loginId)) {

    $field = 'steamid64';

} elseif (preg_match('/^09[0-9]{9}$/', $loginId)) {

    $field = 'phone';

} else {

    $field = 'username';

}



$stmt = $pdo->prepare("SELECT * FROM users WHERE $field = ?");

$stmt->execute([$loginId]);

$user = $stmt->fetch(PDO::FETCH_ASSOC);



if ($user && password_verify($password, $user['password_hash'])) {

    if (isset($user['insmaster']) && $user['insmaster'] == 1) {

        $token = bin2hex(random_bytes(16));

        echo json_encode([

            'status' => 'success',

            'message' => 'Login successful.',

            'token' => $token,

            'username' => $user['username'] ?: $user['steamid64']

        ]);

    } else {

        echo json_encode(['status' => 'error', 'message' => 'Access denied: InsMaster permission required (insmaster = 0).']);

    }

} else {

    echo json_encode(['status' => 'error', 'message' => 'Invalid credentials provided.']);

}

?>