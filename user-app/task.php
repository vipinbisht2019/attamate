<?php 
include('../config/config.php');
include('../config/functions.php');

//error_log(json_encode($get));

$uniquecode = blank($get->uniquecode, 'Uniquecode');
$date = blank($get->date, 'Date');
$data = blank($get->data, 'Data');
$device = blank($get->device, 'Device');
$token = blank($get->token, 'Token');

//======= Authinticate first =========
	authenticate($uniquecode, $token);
//====================================

$user = user($uniquecode);
if( $user->num_rows > 0 ) {

	$user_data = $user->fetch_assoc();
	$name = $user_data['name'];

	if( $date <= $today ){

		$sql = "select * from `task` where `date`='".$date."' and emp_code='$uniquecode' ";
		$result = $mysqli->query($sql);
		if( $result->num_rows == 0 ){

			$txn = txn_id("TSK");
			//$newleave_id = $txn['txn_id'];

			$data_array = array(
				"emp_code" => $uniquecode,
				"date" => $date,
				"data" => $data,
				"name" => $name,
				"txn_id" => $txn['txn_id'],
			);
			$result = insert($data_array, 'task');

			if( $result > 0 ) {
				//========= update master txn ===========
				update_txn($txn['txn_id'], $txn['type'], $uniquecode, $user_data['role'], $device);
				//=======================================
				echo json_encode(array('status'=>'2', 'data'=> 'Saved successfully', 'message'=>''));
			} else {
				exception($uniquecode, basename($_SERVER['PHP_SELF']), $json, 'Not Saved', $user_data['role'], $device);
				echo json_encode(array('status'=>'0', 'data'=>'', 'message'=>'Not Saved'));
			}

		} else {
		    
		    $task_data = $result->fetch_assoc();
			
			$data_array = array(
				"data" => $task_data['data'].' | '.$data
			);
			$where = " emp_code='".$uniquecode."' and date='".$date."' ";
			$result = update($data_array, 'task', $where);

			if( $result ) {
				echo json_encode(array('status'=>'2', 'data'=> 'Saved successfully', 'message'=>''));
			} else {
				exception($uniquecode, basename($_SERVER['PHP_SELF']), $json, 'Not Saved', $user_data['role'], $device);
				echo json_encode(array('status'=>'0', 'data'=>'', 'message'=>'Not Saved'));
			}
			
		}

	} else {
		exception($uniquecode, basename($_SERVER['PHP_SELF']), $json, 'You cannot put data for next day.', $user_data['role'], $device);
		echo json_encode(array('status'=>'0', 'data'=>'', 'message'=>'You cannot put data for next day.'));
	} 

} else {
	exception($uniquecode, basename($_SERVER['PHP_SELF']), $json, 'Invalid User', $user_data['role'], $device);
	echo json_encode(array('status'=>'0', 'data'=>'', 'message'=>'Invalid User'));
}
?>