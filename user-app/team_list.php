<?php 
include('../config/config.php');
include('../config/functions.php');

$uniquecode = blank($get->uniquecode);
$token = blank($get->token);
$device = blank($get->device);
$data = array();

//======= Authinticate first =========
	authenticate($uniquecode, $token);
//====================================

$user = user($uniquecode);
if( $user->num_rows > 0 ) {

	$user_data = $user->fetch_assoc();

    $sql = "select `name`,`emp_code` from user where `manager_code`='".$uniquecode."' and role='user' and status='Approved' ";
	$result = $mysqli->query($sql);
	if( $result->num_rows  > 0 ) {
		while( $row = $result->fetch_assoc() )
		{
			$data[] = $row;
		}
	}

	if( count($data) > 0 ){
		echo json_encode(array('status'=>'1', 'data'=>$data, 'message'=>''));
	} else {
		echo json_encode(array('status'=>'0', 'data'=>$data, 'message'=>'No Data'));
	}

} else {
	exception($uniquecode, basename($_SERVER['PHP_SELF']), $json, 'Invalid User', 'kre', $device);
	echo json_encode(array('status'=>'0', 'data'=>'', 'message' => 'Invalid User'));
}


?>