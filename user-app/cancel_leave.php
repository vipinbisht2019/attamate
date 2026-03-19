<?php 
include('../config/config.php');
include('../config/functions.php');

$leave_id = blank($get->leave_id);
$uniquecode = blank($get->uniquecode);
$token = blank($get->token);
$device = blank($get->device);

//======= Authinticate first =========
	authenticate($uniquecode, $token);
//====================================

$user_check = user($uniquecode);
if( $user_check->num_rows > 0 ) {

	$user_data = $user_check->fetch_assoc();

	$leave_sql = "select * from `leave_applied` where `leave_id`='".$leave_id."' ";
	$result_leave_sql = $mysqli->query($leave_sql);
	if( $result_leave_sql->num_rows > 0 ){

		$leave_data = $result_leave_sql->fetch_assoc();

		if( $leave_data['leave_from'] >= $today  ){

			if( $leave_from == $today ){
				$att_q  = "select * from `attendancetable` where `date`>='".$row['leave_from']."' and `date`<='".$row['leave_to']."' and `emp_code`='".$row['uniquecode']."' order by `id` desc limit 1 ";
				$result_att = $mysqli->query($att_q);
				if( $result_att->num_rows > 0 ){

					exception($uniquecode, basename($_SERVER['PHP_SELF']), $json, 'Cannot cancel leave you marked attendance.', $user_data['role'], $device);
					echo  json_encode(array('status'=>'0', 'data'=>'', 'message'=>'Cannot cancel leave you marked attendance'));
					exit();
				}
			} 
			
			$update_array = array(
				"cancel_leave" => "Yes",
				"status" => "Cancelled",
				'changed_by' => $uniquecode,
			    'changed_by_role' => $user_data['role'],
			    'changed_by_name' => $user_data['name'],
			    'changed_datetime' => date('Y-m-d H:i:s'),
			);
			$where = "leave_id='".$leave_id."'";
			$result = update($update_array, 'leave_applied', $where);

			if( $result ) { 
				echo  json_encode(array('status'=>'2', 'data'=>'Leave cancelled successfully', 'message'=>''));
			} else { 
				exception($uniquecode, basename($_SERVER['PHP_SELF']), $json, 'Leave not cancelled', $user_data['role'], $device);
				echo  json_encode(array('status'=>'0', 'data'=>'', 'message'=>'Leave not cancelled'));
			}

		} else {
			exception($uniquecode, basename($_SERVER['PHP_SELF']), $json, 'You cannot cancel leave for back date', $user_data['role'], $device);
			echo json_encode(array('status'=>'0', 'data'=>'', 'message'=>'You cannot cancel leave for back date'));			
		}

	} else {
		exception($uniquecode, basename($_SERVER['PHP_SELF']), $json, 'Invalid Leave Id', $user_data['role'], $device);
		echo json_encode(array('status'=>'0', 'data'=>'', 'message'=>'Invalid Leave Id'));
	}

} else {
	exception($uniquecode, basename($_SERVER['PHP_SELF']), $json, 'Invalid User', $user_data['role'], $device);
	echo json_encode(array('status'=>'0', 'data'=>'', 'message'=>'Invalid User'));
}

?>