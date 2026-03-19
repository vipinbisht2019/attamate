<?php 
include('../config/config.php');
include('../config/functions.php');

//error_log(json_encode($get));

$uniquecode = blank($get->uniquecode, 'Uniquecode');
$leave_days = blank($get->Leave_days, 'Leave days');
$leave_day_type = blank($get->leave_type, 'leave type');
$leave_from = blank($get->Leave_from, 'Leave from');
$leave_to = blank($get->Leave_to, 'Leave to');
$leave_description = blank($get->Leave_reason, 'Leave Desc');
$leave_time = $get->Leave_time;
$device = blank($get->device, 'Device');
$token = blank($get->token, 'Token');

//======= Authinticate first =========
	authenticate($uniquecode, $token);
//====================================

$user = user($uniquecode);
if( $user->num_rows > 0 ) {

	$user_data = $user->fetch_assoc();

	if( $leave_from >= $today && $leave_to >= $today ){

		if( (strtotime($leave_from) > strtotime($leave_to))  && $leave_days == 'Multiple Days' ){
			exception($uniquecode, basename($_SERVER['PHP_SELF']), $json, 'Leave from cannot be greater than leave To.', $user_data['role'], $device);
			echo json_encode(array('status'=>'0', 'message'=>'Leave from cannot be greater than leave To'));
		}
		
		if($leave_days != 'Multiple Days')
			{				
				$leave_to = $leave_from;			
			} else 	{
				$leave_to = $leave_to;
			}

		$sql = "select * from `leave_applied` where ((`leave_from` BETWEEN '".$leave_from."' and '".$leave_to."') or (`leave_to` BETWEEN '".$leave_from."' and '".$leave_to."')) and `status` not in ('Cancelled') and emp_code='$uniquecode' and `delete`='0' ";
		$result = $mysqli->query($sql);
		if( $result->num_rows == 0 ){
		    
		    //====== Att chech today =======
			$att = "select * from `attendance` where `emp_code`='".$uniquecode."' and `date`='".$leave_from."' ";
			$att_result = $mysqli->query($att);
			if( $att_result->num_rows > 0 ){
				exception($uniquecode, basename($_SERVER['PHP_SELF']), $json, 'Leave can not apply attendance marked for the day', $user_data['role'], $device);
				echo json_encode(array('status'=>'0', 'message'=>'Leave can not apply attendance marked for the day'));
				exit;
			}

			$txn = txn_id("LEA");
			$newleave_id = $txn['txn_id'];

			$leave_applied_array = array(
				"emp_code" => $uniquecode,
				"company_code" => $user_data['office_code'],
				"leave_type" => $leave_days,
				"leave_day_type" => $leave_day_type,
				"leave_from" => $leave_from,
				"leave_to" => $leave_to,
				"leave_description" => $leave_description,
				"date_of_request" => $today,
				"leave_time" => $leave_time,
				"leave_id" => $newleave_id,
				"cancel_leave" => "No",
				"status" => "Pending",
			);
			$result = insert($leave_applied_array, 'leave_applied');

			if( $result > 0 ) { 
				if( $user_data['role']!='Office' && $user_data['role']!='manager' ){
					send_notification($uniquecode,$user_data['role'],'leave');
				}
				//========= update master txn ===========
				update_txn($txn['txn_id'], $txn['type'], $uniquecode, $user_data['role'], $device, 'Pending');
				//=======================================
				echo json_encode(array('status'=>'2', 'message'=>$newleave_id.' Leave id created successfully'));
			} else {
				exception($uniquecode, basename($_SERVER['PHP_SELF']), $json, 'Leave id not created', $user_data['role'], $device);
				echo json_encode(array('status'=>'0', 'message'=>'Leave id not created'));
			}

		} else {
			exception($uniquecode, basename($_SERVER['PHP_SELF']), $json, 'Leave already applied on selected date', $user_data['role'], $device);
			echo json_encode(array('status'=>'0', 'message'=>'Leave already applied on selected date'));
		}



	} else {
		exception($uniquecode, basename($_SERVER['PHP_SELF']), $json, 'You cannot apply leave for back date', $user_data['role'], $device);
		echo json_encode(array('status'=>'0', 'message'=>'You cannot apply leave for back date'));
	} 

} else {
	exception($uniquecode, basename($_SERVER['PHP_SELF']), $json, 'Invalid User', $user_data['role'], $device);
	echo json_encode(array('status'=>'0', 'message'=>'Invalid User'));
}
?>