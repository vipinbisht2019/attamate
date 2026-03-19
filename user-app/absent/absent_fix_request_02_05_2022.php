<?php
include('../../config/config.php');
include('../../config/functions.php');

$uniquecode = blank($get->uniquecode, 'Uniquecode');
$attendance_id = blank($get->attendance_id, 'Attendance Id');
$selected_in_time = blank($get->selected_in_time, 'In Time');
$selected_out_time = blank($get->selected_out_time, 'Out Time');
$place = blank($get->place, 'Place');
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

	$txn = txn_id("ABF");
	$master_txn = $txn['txn_id'];
	
	//=== Only 4 absent fix is allowed date-31-07-2018 ======= week off exclude date from 12-26-2018 by nishant sir  ===========
	$abs_sql = "select * from `absent_fix_entries` where `emp_code`='".$uniquecode."' and place!='Week off' and `for_date` like '%".date('Y-m')."%' ";
	$result_abs_sql = $mysqli->query($abs_sql);
	if( $result_abs_sql->num_rows > 3 ){
	    echo json_encode(array('status'=>'0', 'data'=>'', 'message'=>'Limit exhausted for Absent Fix, Contact Manager/HR.'));
		exit();
	}

	$sql = "select * from `attendance` where `id`='".$attendance_id."' ";
	$result = $mysqli->query($sql);
	if( $result->num_rows > 0 ){
		$attendance_data = $result->fetch_assoc();
	} else {
		echo json_encode(array('status'=>'0', 'data'=>'', 'message'=>'Invalid Id'));
		exit();
	}

	$check_sql = "select * from `absent_fix_entries` where `emp_code`='".$uniquecode."' and `for_date`='".$attendance_data['date']."' ";
	$result_check_sql = $mysqli->query($check_sql);
	if( $result_check_sql->num_rows == 0 ){


		$sql1 = "select * from `attendancetable` where `emp_code`='".$uniquecode."' and `date`='".$attendance_data['date']."' order by id limit 1 ";
		$result1 = $mysqli->query($sql1);
		$attendancetable_data = $result1->fetch_assoc();

        $selected_in_time = date('H:i:s', strtotime($selected_in_time));
        $selected_out_time = date('H:i:s', strtotime($selected_out_time));

		$absent_fix_array = array(
			"emp_code" => $uniquecode,
			"emp_name" => $user_data['name'],
			"for_date" => $attendance_data['date'],
			"in_time" => $selected_in_time,
			"out_time" => $selected_out_time,
			"place" => $place,
			"request_date" => $today,
			"reason_for_absent" => $remarks,
			"manager_name" => $user_data['manager_name'],
			"manager_code" => $user_data['manager_code'],
			"status" => "pending",
			"master_txn" => $master_txn,
		);
		$absent_insert = insert($absent_fix_array, 'absent_fix_entries');
		if( $absent_insert > 0 ){

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