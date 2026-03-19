<?php 
include('../../config/config.php');
include('../../config/functions.php');

$uniquecode = blank($get->uniquecode);
$project = blank($get->project, 'Project');
$token = blank($get->token);
$device = blank($get->device);

//======= Authinticate first =========
	//authenticate($uniquecode, $token);
//====================================

$user = user($uniquecode);
if( $user->num_rows > 0 ) {

	$user_data = $user->fetch_assoc();

    $check_sql = "select * from `todo` where `emp_code`='".$uniquecode."' and project_client_name='".$project."' and project_deleted=''  ";
	$result_check_sql = $mysqli->query($check_sql);
	if( $result_check_sql->num_rows > 0 ){

	    $update_array = array(
			"project_deleted" => 'yes',
			"updated_at" => date('Y-m-d H:i:s'),
		);
		$where = "project_client_name='".$project."' and project_deleted='' and emp_code='".$uniquecode."' ";
		$result = update($update_array, 'todo', $where);

		echo json_encode(array('status'=>'2', 'data'=> 'Project/Client deleted successfully', 'message'=>''));

	} else {
		exception($uniquecode, basename($_SERVER['PHP_SELF']), $json, 'Invalid Project', $user_data['role'], $device);
		echo json_encode(array('status'=>'0', 'data'=>'', 'message'=>'Invalid Project'));
	}


} else {
	exception($uniquecode, basename($_SERVER['PHP_SELF']), $json, 'Invalid User', $user_data['role'], $device);
	echo json_encode(array('status'=>'0', 'data'=>'', 'message' => 'Invalid User'));
}


?>