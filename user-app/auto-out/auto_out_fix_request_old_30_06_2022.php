<?php
include('../../config/config.php');
include('../../config/functions.php');

$uniquecode = blank($get->uniquecode, 'Uniquecode');
$last_attendance_date = blank($get->last_attendance_date, 'Attendance Date');
// $attendance_id = blank($get->attendance_id, 'Attendance Id');
$selected_out_time = blank($get->selected_out_time, 'Out Time');
$remarks = blank($get->remarks, 'Remarks');
$device = blank($get->device, 'Device');
$token = blank($get->token, 'Token');

//======= Authinticate first =========
	authenticate($uniquecode, $token);
//====================================

// $today_date = date('Y-m-d');
$today_date2 = date('Y-m');
$attendance_date = date('Y-m-d',strtotime($last_attendance_date));


$user = user($uniquecode);
if( $user->num_rows > 0 ) {
    
  //   echo json_encode(array('status'=>'0', 'data'=>'', 'message'=>'No.. This feature is disabled.'));
	// exit();

	$user_data = $user->fetch_assoc();

	$txn = txn_id("ATF");
	$master_txn = $txn['txn_id'];
	
	//=== Only 5 Auto out fix is allowed date-31-07-2018 ==================
	$auto_sql = "select * from `auto_out_fix_entries` where `emp_code`='".$uniquecode."' and `for_date` like '%".$attendance_date."%' ";
	$result_auto_sql = $mysqli->query($auto_sql);
	if( $result_auto_sql->num_rows > 4 ){
	    echo json_encode(array('status'=>'0', 'data'=>'', 'message'=>'Limit exhausted for Auto Out, Contact Manager/HR.'));
		exit();
	}

	$sql = "select * from `attendance` where `emp_code`='".$uniquecode."' and `date` = '".$attendance_date."' ";
	$result = $mysqli->query($sql);
	if( $result->num_rows > 0 ){
		$attendance_data = $result->fetch_assoc();
		
		if( strtotime($attendance_data['in_time']) >= strtotime($selected_out_time) ) {
			echo json_encode(array('status'=>'0', 'data'=>'', 'message'=>'Out time should be greater than In Time.'));
			exit();
		}
	} 
	/* else {
		echo json_encode(array('status'=>'0', 'data'=>'', 'message'=>'Invalid Id'));
		exit();
	}
	*/
	
	 $check_sql = "select * from `auto_out_fix_entries` where `emp_code`='".$uniquecode."' and `for_date`='".$attendance_data['date']."' ";
	
	$result_check_sql = $mysqli->query($check_sql);
	// echo $result_check_sql->num_rows; die;
	 $attendancetable_data11 = $result_check_sql->fetch_assoc();
						
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


		
		 $in_time = $attendance_data['in_time']; 
		// echo "<br>";
		 $out_time = $selected_out_time;
		// echo "<br>";		
		
		$to_time = strtotime($in_time);
		$from_time = strtotime($out_time);

		$ss = round(abs($to_time - $from_time) / 60,2);
		$zz = $ss*60;
		$th = gmdate("H:i:s", $zz);
		//  echo "<br>";
		//	echo strtotime($th); 
		//  echo "<br>";
		//  echo strtotime("02:00:00");
		//  die;
		$tch = $mysqli->query("update attendance set out_time = '$selected_out_time',working_hours = '$th' where emp_code = '$uniquecode' AND date = '$attendance_date'");
		if( strtotime($th)<strtotime("02:00:00") ){			
			$q1 = $mysqli->query("update attendance set halfs = 'Absent' where emp_code = '$uniquecode' AND date = '$attendance_date'") or die($mysqli->error);
		} else if( strtotime($th)>=strtotime("02:00:00") && strtotime($th)<=strtotime("08:30:00") ){		
			
			$q1 = $mysqli->query("update attendance set halfs = 'Half day' where emp_code = '$uniquecode' AND date = '$attendance_date'") or die($mysqli->error);
		} else if( strtotime($th)>=strtotime("08:30:00") ){
			
			$q1 = $mysqli->query("update attendance set halfs = 'Full day' where emp_code = '$uniquecode' AND date = '$attendance_date'") or die($mysqli->error);
		}
						
		$update_user_sql = "update `user` set can_mark_attendance ='yes' where `emp_code`='".$uniquecode."' ";	
		 $update_user_check_sql = $mysqli->query($update_user_sql);
		
		// $update_sql11 = "update `attendance` set out_time ='".$selected_out_time."', halfs='Full day' where emp_code='".$uniquecode."' // and date = '".$attendance_date."' ";	 
		// $update_check_sql = $mysqli->query($update_sql11);
		
		$update_sql112 = "update `attendancetable` set time ='".$selected_out_time."', place='".$attendancetable_data11['place']."' where emp_code='".$uniquecode."' and date = '".$attendance_date."' and status = 'OUT' ";	 
		$update_check_sql2 = $mysqli->query($update_sql112);
		
						
		exception($uniquecode, basename($_SERVER['PHP_SELF']), $json, 'Request already submitted', $user_data['role'], $device);
		// echo json_encode(array('status'=>'0', 'data'=>'', 'message'=>'Request already submitted'));
		echo json_encode(array('status'=>'1','message'=>'Request already submitted'));
	}

} else {
	exception($uniquecode, basename($_SERVER['PHP_SELF']), $json, 'Invalid User', $user_data['role'], $device);
	echo json_encode(array('status'=>'0', 'data'=>'', 'message'=>'Invalid User'));
}
?>