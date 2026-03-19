<?php
include('../../config/config.php');
include('../../config/functions.php');

$uniquecode = blank($get->uniquecode, 'Uniquecode');
$attendance_id = blank($get->attendance_id, 'Attendance Id');
$selected_out_time = blank($get->selected_out_time, 'Out Time');
$remarks = blank($get->remarks, 'Remarks');
$device = blank($get->device, 'Device');
$token = blank($get->token, 'Token');

//======= Authinticate first =========
	authenticate($uniquecode, $token);
//====================================

$user = user($uniquecode);
if( $user->num_rows > 0 ) {
    
    echo json_encode(array('status'=>'0', 'data'=>'', 'message'=>'No.. This feature is disabled.'));
	exit();

	$user_data = $user->fetch_assoc();

	$txn = txn_id("ATF");
	$master_txn = $txn['txn_id'];
	
	//=== Only 5 Auto out fix is allowed date-31-07-2018 ==================
	$auto_sql = "select * from `auto_out_fix_entries` where `emp_code`='".$uniquecode."' and `for_date` like '%".date('Y-m')."%' ";
	$result_auto_sql = $mysqli->query($auto_sql);
	if( $result_auto_sql->num_rows > 4 ){
	    echo json_encode(array('status'=>'0', 'data'=>'', 'message'=>'Limit exhausted for Auto Out, Contact Manager/HR.'));
		exit();
	}

	$sql = "select * from `attendance` where `id`='".$attendance_id."' ";
	$result = $mysqli->query($sql);
	if( $result->num_rows > 0 ){
		$attendance_data = $result->fetch_assoc();
		
		if( strtotime($attendance_data['in_time']) >= strtotime($selected_out_time) ) {
			echo json_encode(array('status'=>'0', 'data'=>'', 'message'=>'Out time should be greater than In Time.'));
			exit();
		}
	} else {
		echo json_encode(array('status'=>'0', 'data'=>'', 'message'=>'Invalid Id'));
		exit();
	}
	
	$check_sql = "select * from `auto_out_fix_entries` where `emp_code`='".$uniquecode."' and `for_date`='".$attendance_data['date']."' ";
	$result_check_sql = $mysqli->query($check_sql);
	if( $result_check_sql->num_rows == 0 ){


		$sql1 = "select * from `attendancetable` where `emp_code`='".$uniquecode."' and `date`='".$attendance_data['date']."' order by id limit 1 ";
		$result1 = $mysqli->query($sql1);
		$attendancetable_data = $result1->fetch_assoc();

        $selected_out_time = date('H:i:s', strtotime($selected_out_time));
		$auto_out_fix_array = array(
			"emp_code" => $uniquecode,
			"emp_name" => $user_data['name'],
			"for_date" => $attendance_data['date'],
			"in_time" => $attendance_data['in_time'],
			"selected_out_time" => $selected_out_time,
			"place" => $attendancetable_data['place'],
			"request_date" => $today,
			"reason_for_auto_out" => $remarks,
			"manager_name" => $user_data['manager_name'],
			"manager_code" => $user_data['manager_code'],
			"status" => "pending",
			"master_txn" => $master_txn,
		);
		$auto_out_insert = insert($auto_out_fix_array, 'auto_out_fix_entries');
		if( $auto_out_insert > 0 ){

			if( $user_data['role']=='user' ){
				// send_notification($uniquecode, $user_data['role'], 'auto_out');
			}
			//========= update master txn ===========
			update_txn($txn['txn_id'], $txn['type'], $uniquecode, $user_data['role'], $device);
			//=======================================
			echo json_encode(array('status'=>'2', 'data'=> $master_txn.' Request id created successfully', 'message'=>''));

		} else {
			exception($uniquecode, basename($_SERVER['PHP_SELF']), $json, 'Auto out fix request Failed', $user_data['role'], $device);
			echo json_encode(array('status'=>'0', 'data'=>'', 'message'=>'Auto out fix request Failed'));
		}

	} else {
		exception($uniquecode, basename($_SERVER['PHP_SELF']), $json, 'Request already submitted', $user_data['role'], $device);
		echo json_encode(array('status'=>'0', 'data'=>'', 'message'=>'Request already submitted'));
	}

} else {
	exception($uniquecode, basename($_SERVER['PHP_SELF']), $json, 'Invalid User', $user_data['role'], $device);
	echo json_encode(array('status'=>'0', 'data'=>'', 'message'=>'Invalid User'));
}
?>