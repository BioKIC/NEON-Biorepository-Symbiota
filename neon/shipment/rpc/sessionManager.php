<?php
include_once('../../../config/symbini.php');
include_once($SERVER_ROOT.'/config/dbconnection.php');


// Check if session is not already active
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$request_body = file_get_contents('php://input');
$data = json_decode($request_body, true);

if ($data['action'] === 'start_session') {
    $conn = MySQLiConnectionFactory::getCon("write");
    $sql = "SELECT LPAD(MAX(sessionID)+1, 3, '0') AS sessionNumber FROM NeonSample";    
    $rs = $conn->query($sql);
    while($r = $rs->fetch_object()){
        $sessionNum = $r->sessionNumber;
    }
    $rs->free();    
    if($conn) $conn->close();
    
    $session_data = [
        'sessionID' => $USERNAME.'-'.$sessionNum,
        'start_time' => date('Y-m-d H:i:s'),
        'end_time' => null,
    ];

    $_SESSION['sampleCheckinSessionData'] = $session_data;

    // Return session data to frontend
    echo json_encode(['start_time' => $session_data['start_time']]);
}

if ($data['action'] === 'stop_session') {
    $conn = MySQLiConnectionFactory::getCon("write");
    $session_data = $_SESSION['sampleCheckinSessionData'];
    $session_data['end_time'] = date('Y-m-d H:i:s');
    $end_session_data_json = json_encode($session_data);
    $start_session_data_json = json_encode($_SESSION['sampleCheckinSessionData']);    
    
    $sqlUpdate = "UPDATE NeonSample 
                  SET sessionData = '" . $conn->real_escape_string($end_session_data_json) . "' 
                  WHERE sessionData = '" . $conn->real_escape_string($start_session_data_json) . "'";
    
    $conn->query($sqlUpdate);
    if($conn) $conn->close();
    $_SESSION['sampleCheckinSessionData'] = $session_data;

    // Return session end time to frontend
    echo json_encode(['end_time' => $session_data['end_time']]);
}
?>
