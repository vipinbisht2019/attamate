<?php 
include('../../config/config.php');
include('../../config/functions.php');

$uniquecode = blank($get->uniquecode);
$task_id = blank($get->task_id, 'Task ID');
$status = blank($get->status, 'Status');
$token = blank($get->token);
$device = blank($get->device);

//======= Authinticate first =========
	authenticate($uniquecode, $token);
//====================================

$user = user($uniquecode);
if( $user->num_rows > 0 ) {

	$user_data = $user->fetch_assoc();

    $check_sql = "select * from `todo` where `emp_code`='".$uniquecode."' and id='".$task_id."'  ";
	$result_check_sql = $mysqli->query($check_sql);
	if( $result_check_sql->num_rows > 0 ){

		if( $status == 'up' || $status == 'down' ){
		    $update_array = array(
				"task_status" => $status,
				"updated_at" => date('Y-m-d H:i:s'),
			);
			$where = "id='".$task_id."'";
			$result = update($update_array, 'todo', $where);
		} else if( $status == 'delete' ){
		    $update_array = array(
				"task_deleted" => 'yes',
				"updated_at" => date('Y-m-d H:i:s'),
			);
			$where = "id='".$task_id."'";
			$result = update($update_array, 'todo', $where);
		}

		echo json_encode(array('status'=>'2', 'data'=> 'Task status updated successfully', 'message'=>''));

	} else {
		exception($uniquecode, basename($_SERVER['PHP_SELF']), $json, 'Invalid task', $user_data['role'], $device);
		echo json_encode(array('status'=>'0', 'data'=>'', 'message'=>'Invalid task'));
	}


} else {
	exception($uniquecode, basename($_SERVER['PHP_SELF']), $json, 'Invalid User', $user_data['role'], $device);
	echo json_encode(array('status'=>'0', 'data'=>'', 'message' => 'Invalid User'));
}


?>