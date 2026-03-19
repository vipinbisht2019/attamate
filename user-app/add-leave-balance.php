<?php 
include('../config/config.php');
include('../config/functions.php');

//error_log(json_encode($get));

$uniquecode = blank($get->uniquecode, 'Uniquecode');
$user_uniquecode = blank($get->user_uniquecode, 'User Uniquecode');
$days = blank($get->days, 'Days');
$reason = blank($get->reason, 'Reason');
$device = blank($get->device, 'Device');
$token = blank($get->token, 'Token');

//======= Authinticate first =========
	authenticate($uniquecode, $token);
//====================================

$user = user($uniquecode);
if( $user->num_rows > 0 ) {

	$user_data = $user->fetch_assoc();
	
	echo json_encode(array('status'=>'2', 'data'=> 'Leave added successfully', 'message'=>''));
 

} else {
	exception($uniquecode, basename($_SERVER['PHP_SELF']), $json, 'Invalid User', $user_data['role'], $device);
	echo json_encode(array('status'=>'0', 'data'=>'', 'message'=>'Invalid User'));
}
?>