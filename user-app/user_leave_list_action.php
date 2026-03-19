<?php 
include('../config/config.php');
include('../config/functions.php');

$uniquecode = blank($get->uniquecode);
$leave_id = blank($get->leave_id, 'Leave Id');
$action = blank($get->action, 'Action');
$remarks = 'Done by Manager';
$token = blank($get->token);
$device = blank($get->device);

//======= Authinticate first =========
	authenticate($uniquecode, $token);
//====================================

$user = user($uniquecode);
if( $user->num_rows > 0 ) {

	$user_data = $user->fetch_assoc();
	$leave = "select * from `leave_applied` where `leave_id`='".$leave_id."' ";
	$result_leave = $mysqli->query($leave);
	$leave_data = $result_leave->fetch_assoc();

	$u = user($leave_data['emp_code']);
	$u_data = $u->fetch_assoc();

	//================= Insert entry in attendance table =================
	if( $action == 'Approved' ) {

		$att_q  = "select * from `attendancetable` where `date`>='".$leave_data['leave_from']."' and `date`<='".$leave_data['leave_to']."' and `emp_code`='".$leave_data['emp_code']."' order by `id` desc limit 1 ";
		$result_att = $mysqli->query($att_q);
		if( $result_att->num_rows > 0 ){

			exception($uniquecode, basename($_SERVER['PHP_SELF']), $json, 'Cannot approve leave user marked attendance.', $user_data['role'], $device);
			echo json_encode(array('status'=>'0', 'data'=>'', 'message' => 'Cannot approve leave user marked attendance.'));
			exit();

		} else {

			$date_array = create_date_range_array($leave_data['leave_from'], $leave_data['leave_to']);
			
			for( $i=0; $i<count($date_array); $i++ ){
				$attendance_array = array(
					'emp_code' => $leave_data['emp_code'],
					'company_code' => $u_data['office_code'],
					'role' => $u_data['role'],
					'date' => $date_array[$i],
					'halfs' => 'Leave',
					'in_time' => date('H:i:s'),
					'out_time' => date('H:i:s'),
					'marked_by' => $user_data['role'],
					'msg' => $remarks,
					'manager_name' => $user_data['name'],
					'manager_code' => $user_data['emp_code'],
				);
				$kre_wise = insert($attendance_array, 'attendance');	

				$attendancetable_array = array(
					'emp_code' => $leave_data['emp_code'],
					'company_code' => $u_data['office_code'],
					'role' => $u_data['role'],
					'date' => $date_array[$i],
					'marked_by' => $user_data['role'],
					'msg' => $remarks,
					'status' => 'OUT',
					'place' => 'Leave',
					'time' => date('H:i:s'),
					'master_txn' => $leave_data['leave_id'],
				);
				$attendancetable = insert($attendancetable_array, 'attendancetable');

			}
		}
	}
	//==================================================================

	//======== Delete entry from kre wise and attendancetable if rejected ===========
	if( $action == 'Rejected' && $leave_data['status']=='Approved' ) {

		if( $leave_data['leave_from'] < $today ) {

			exception($uniquecode, basename($_SERVER['PHP_SELF']), $json, 'Cannot reject leave for past date.', $user_data['role'], $device);
			echo json_encode(array('status'=>'0', 'data'=>'', 'message' => 'Cannot reject leave for past date.'));
			exit();

		} else {

			$date_array = create_date_range_array($leave_data['leave_from'], $leave_data['leave_to']);

			for( $i=0; $i<count($date_array); $i++ ){

				$del_kre_wise = "delete from `attendance` where `emp_code`='".$leave_data['emp_code']."' and `date`='".$date_array[$i]."' and `halfs`='Leave' ";
				$result_kre_wise = $mysqli->query($del_kre_wise);	

				$del_attendancetable = "delete from `attendancetable` where `date`='".$date_array[$i]."' and `emp_code`='".$leave_data['emp_code']."' and `place`='Leave' and `status`='OUT' ";
				$result_attendancetable = $mysqli->query($del_attendancetable);

			}
		}
		
	}
	//===============================================================================	

	$sql1 = "update leave_applied set status='".$action."',changed_by='".$uniquecode."',changed_by_name='".$user_data['name']."',changed_by_role='".$user_data['role']."',changed_datetime='".date('Y-m-d H:i:s')."' where leave_id='$leave_id'";
	$result1 = $mysqli->query($sql1);
	if( $mysqli->affected_rows > 0 ) {
		
		//send_notification($row['uniquecode'],'kre','leave_status', $leave_id, $status);
		echo json_encode(array('status'=>'2', 'data'=>'Leave status changed successfully.', 'message' => ''));

	} else {

		exception($uniquecode, basename($_SERVER['PHP_SELF']), $json, 'Leave status changed successfully.', $user_data['role'], $device);
		echo json_encode(array('status'=>'0', 'data'=>'', 'message' => 'Leave status changed successfully.'));
	}


} else {
	exception($uniquecode, basename($_SERVER['PHP_SELF']), $json, 'Invalid User', $user_data['role'], $device);
	echo json_encode(array('status'=>'0', 'data'=>'', 'message' => 'Invalid User'));
}

?>