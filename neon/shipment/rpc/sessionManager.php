<?php
include_once('../../../config/symbini.php');
// Check if session is not already active
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$request_body = file_get_contents('php://input');
$data = json_decode($request_body, true);

if ($data['action'] === 'start_session') {
    $session_data = [
        'start_time' => date('Y-m-d H:i:s'),
        'end_time' => null
    ];

    $_SESSION['session_data'] = json_encode($session_data);

    // Return session data to frontend
    echo json_encode(['start_time' => $session_data['start_time']]);
}

if ($data['action'] === 'stop_session') {
    $session_data = json_decode($_SESSION['session_data'], true);
    $session_data['end_time'] = date('Y-m-d H:i:s');
    $_SESSION['session_data'] = json_encode($session_data);

    // Return session end time to frontend
    echo json_encode(['end_time' => $session_data['end_time']]);
}
?>
