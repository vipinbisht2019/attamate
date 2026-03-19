<?php 
include('../../config/config.php');
include('../../config/functions.php');

$uniquecode = blank($get->uniquecode);
$project_name = blank($get->project_name);
$token = blank($get->token);
$device = blank($get->device);

//======= Authinticate first =========
	authenticate($uniquecode, $token);
//====================================

$user = user($uniquecode);
if( $user->num_rows > 0 ) {

	$user_data = $user->fetch_assoc();

    $check_sql = "select * from `todo_project` where `emp_code`='".$uniquecode."' and project_name='".$project_name."' ";
	$result_check_sql = $mysqli->query($check_sql);
	if( $result_check_sql->num_rows == 0 ){

		$project_name_array = array(
			"emp_code" => $uniquecode,
			"project_name" => $project_name,
		);
		$project_name_insert = insert($project_name_array, 'todo_project');
		if( $project_name_insert > 0 ){

			echo json_encode(array('status'=>'2', 'data'=> 'Project added successfully', 'message'=>''));

		} else {
			exception($uniquecode, basename($_SERVER['PHP_SELF']), $json, 'Project add request Failed', $user_data['role'], $device);
			echo json_encode(array('status'=>'0', 'data'=>'', 'message'=>'Project add request Failed'));
		}

	} else {
		exception($uniquecode, basename($_SERVER['PHP_SELF']), $json, 'Project already exist', $user_data['role'], $device);
		echo json_encode(array('status'=>'0', 'data'=>'', 'message'=>'Project already exist'));
	}


} else {
	exception($uniquecode, basename($_SERVER['PHP_SELF']), $json, 'Invalid User', $user_data['role'], $device);
	echo json_encode(array('status'=>'0', 'data'=>'', 'message' => 'Invalid User'));
}


?>