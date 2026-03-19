<?php 
include('../config/config.php');
include('../config/functions.php');

$uniquecode = blank($get->uniquecode, 'Uniquecode');
$location_data = blank($get->location_data, 'Location Data');
$device = blank($get->device, 'Device');
$token = blank($get->token, 'Token');

//======= Authinticate first =========
	authenticate($uniquecode, $token);
//====================================

$user = user($uniquecode);
if( $user->num_rows > 0 ) {

	$user_data = $user->fetch_assoc();

	$live_location_array = array(
		"emp_code" => $uniquecode,
		"emp_name" => $user_data['name'],
		"date" => date('Y-m-d'),
		"time" => date('H:i:s'),
		"location_data" => $location_data,
		"manager_name" => $user_data['manager_name'],
		"manager_code" => $user_data['manager_code']
	);
	$result = insert($live_location_array, 'user_live_location');

	if( $result > 0 ) {
		echo json_encode(array('status'=>'2', 'data'=> 'Saved successfully', 'message'=>''));
	} else {
		exception($uniquecode, basename($_SERVER['PHP_SELF']), $json, 'Error occured', $user_data['role'], $device);
		echo json_encode(array('status'=>'0', 'data'=>'', 'message'=>'Error occured'));
	}

} else {
	exception($uniquecode, basename($_SERVER['PHP_SELF']), $json, 'Invalid User', $user_data['role'], $device);
	echo json_encode(array('status'=>'0', 'data'=>'', 'message'=>'Invalid User'));
}
?>