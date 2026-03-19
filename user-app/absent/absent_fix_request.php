<?php
include('../../config/config.php');
include('../../config/functions.php');

$uniquecode = $get->uniquecode;
$last_attendance_date =$get->last_attendance_date;
// $attendance_id = blank($get->attendance_id, 'Attendance Id');
$selected_in_time = $get->selected_in_time;
$selected_out_time = $get->selected_out_time;
$place = $get->place;
$remarks = $get->remarks;
$device = $get->device;
$token = $get->token;

//======= Authinticate first =========
	authenticate($uniquecode, $token);
//====================================
$today_date2 = date('Y-m');
$attendance_date = date('Y-m-d',strtotime($last_attendance_date));

$user = user($uniquecode);
if( $user->num_rows > 0 ) {
    
    // echo json_encode(array('status'=>'0', 'data'=>'', 'message'=>'No.. This feature is disabled.'));
	// exit();

	$user_data = $user->fetch_assoc();

	$txn = txn_id("ABF");
	$master_txn = $txn['txn_id'];
	
	//=== Only 4 absent fix is allowed date-31-07-2018 ======= week off exclude date from 12-26-2018 by nishant sir  ===========
//	$abs_sql = "select * from `absent_fix_entries` where `emp_code`='".$uniquecode."' and place!='Week off' and `for_date` like '%".date('Y-m')."%' ";
	
	
	if($selected_in_time != '' && $selected_out_time != '' && $place !='') {
	
	$abs_sql = "select * from `absent_fix_entries` where `emp_code`='".$uniquecode."' and place!='Week off' and `for_date` like '%".$attendance_date."%' ";
	$result_abs_sql = $mysqli->query($abs_sql);
	if( $result_abs_sql->num_rows > 3 ){
	    echo json_encode(array('status'=>'0', 'data'=>'', 'message'=>'Limit exhausted for Absent Fix, Contact Manager/HR.'));
		exit();
	}

	$sql = "select * from `attendance` where `emp_code`='".$uniquecode."' and `date` = '".$attendance_date."' ";
	$result = $mysqli->query($sql);
	if( $result->num_rows > 0 ){
		$attendance_data = $result->fetch_assoc();
	}		
	else {
		echo json_encode(array('status'=>'0', 'data'=>'', 'message'=>'Invalid Id'));
		exit();
	}
	

	$check_sql = "select * from `absent_fix_entries` where `emp_code`='".$uniquecode."' and `for_date`='".$attendance_data['date']."' ";
	
	$result_check_sql = $mysqli->query($check_sql);
	$attendancetable_data11 = $result_check_sql->fetch_assoc();
	
	$sql1_new = "select * from `attendancetable` where `emp_code`='".$uniquecode."' and `date`='".$attendance_data['date']."' order by id limit 1 ";
	$result1_new = $mysqli->query($sql1_new);
	$attendancetable_data_new = $result1_new->fetch_assoc();
		
	if( $result_check_sql->num_rows == 0 ){

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
			
		$update_user_sql = "update `user` set can_mark_attendance ='yes' where `emp_code`='".$uniquecode."' ";	
		$update_user_check_sql = $mysqli->query($update_user_sql);
		

			if( $user_data['role']=='user' ){
				// send_notification($uniquecode, $user_data['role'], 'auto_out');
			}
			//========= update master txn ===========
			update_txn($txn['txn_id'], $txn['type'], $uniquecode, $user_data['role'], $device);
			//=======================================
			echo json_encode(array('status'=>'2', 'data'=> $master_txn.' Request id created successfully', 'message'=>'successful'));

		} else {
			exception($uniquecode, basename($_SERVER['PHP_SELF']), $json, 'Auto out fix request Failed', $user_data['role'], $device);
			echo json_encode(array('status'=>'0', 'message'=>'Auto out fix request Failed'));
		}

	} else {
	
	/*	$in_time = $selected_in_time;
		// echo "<br>";
		 $out_time = $selected_out_time;
		// echo "<br>";		
		
		$to_time = strtotime($in_time);
		$from_time = strtotime($out_time);

		$ss = round(abs($to_time - $from_time) / 60,2);
		$zz = $ss*60;
		$th = gmdate("H:i:s", $zz);
		*/
		//  echo "<br>";
		//	echo strtotime($th); 
		//  echo "<br>";
		//  echo strtotime("02:00:00");
		//  die;
		
	/*	$tch = $mysqli->query("update attendance set in_time = '$selected_in_time',out_time = '$selected_out_time',working_hours = '$th' where emp_code = '$uniquecode' AND date = '$attendance_date'");
		if( strtotime($th)<strtotime("02:00:00") ){			
			$q1 = $mysqli->query("update attendance set halfs = 'Absent' where emp_code = '$uniquecode' AND date = '$attendance_date'") or die($mysqli->error);
		} else if( strtotime($th)>=strtotime("02:00:00") && strtotime($th)<=strtotime("08:30:00") ){		
			
			$q1 = $mysqli->query("update attendance set halfs = 'Half day' where emp_code = '$uniquecode' AND date = '$attendance_date'") or die($mysqli->error);
		} else if( strtotime($th)>=strtotime("08:30:00") ){
			
			$q1 = $mysqli->query("update attendance set halfs = 'Full day' where emp_code = '$uniquecode' AND date = '$attendance_date'") or die($mysqli->error);
		}
		
		*/
	/*					
		$update_user_sql = "update `user` set can_mark_attendance ='yes' where `emp_code`='".$uniquecode."' ";	
		$update_user_check_sql = $mysqli->query($update_user_sql);
				
		$insert_absent_fix_array = array(
			"emp_code" => $uniquecode,
			"company_code" => $attendancetable_data_new['company_code'],
			"role" => $attendancetable_data_new['role'],
			"date" => $attendancetable_data_new['date'],
			"time" => $in_time,
			"Status" => "IN",
			"place" => $place,			
			"master_txn" => $master_txn,
		);
		$absent_insert_entry = insert($insert_absent_fix_array, 'attendancetable');
				
		$update_sql112 = "update `attendancetable` set time ='".$selected_out_time."', place='".$place."' where emp_code='".$uniquecode."' and date = '".$attendance_date."' and status = 'OUT' ";	 
		$update_check_sql2 = $mysqli->query($update_sql112);
		
		*/
					
		exception($uniquecode, basename($_SERVER['PHP_SELF']), $json, 'Request already submitted', $user_data['role'], $device);
		echo json_encode(array('status'=>'1', 'message'=>'Request already submitted'));
	}
	
		} else 			
			{
				
			$absent_fix_array_new = array(
			"emp_code" => $uniquecode,
			"emp_name" => $user_data['name'],
			"for_date" => '',
			"in_time" => '',
			"out_time" => '',
			"place" => '',
			"request_date" => $today,
			"reason_for_absent" => $remarks,
			"manager_name" => $user_data['manager_name'],
			"manager_code" => $user_data['manager_code'],
			"status" => "pending",
			"master_txn" => $master_txn,
		);
			$absent_insert_new = insert($absent_fix_array_new, 'absent_fix_entries');
						
			if( $absent_insert_new > 0 ){
			
			$update_user_sql = "update `user` set can_mark_attendance ='yes' where `emp_code`='".$uniquecode."' ";	
			$update_user_check_sql = $mysqli->query($update_user_sql);
		

			if( $user_data['role']=='user' ){
				// send_notification($uniquecode, $user_data['role'], 'auto_out');
			}
			//========= update master txn ===========
			update_txn($txn['txn_id'], $txn['type'], $uniquecode, $user_data['role'], $device);
			//=======================================
			echo json_encode(array('status'=>'2', 'data'=> $master_txn.' Request id created successfully', 'message'=>'successful'));

		} else {
			exception($uniquecode, basename($_SERVER['PHP_SELF']), $json, 'Auto out fix request Failed', $user_data['role'], $device);
			echo json_encode(array('status'=>'0', 'message'=>'Auto out fix request Failed'));
		}
					
	}
	
	

} else {
	exception($uniquecode, basename($_SERVER['PHP_SELF']), $json, 'Invalid User', $user_data['role'], $device);
	echo json_encode(array('status'=>'0', 'message'=>'Invalid User'));
}
?>