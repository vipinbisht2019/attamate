<?php 
include('../config/config.php');
include('../config/functions.php');

$uniquecode = blank($get->uniquecode);
$token = blank($get->token);
$device = blank($get->device);

//======= Authinticate first =========
	authenticate($uniquecode, $token);
//====================================

$user = user($uniquecode);
if( $user->num_rows > 0 ) {

	$user_data = $user->fetch_assoc();

	$approve='0';
	$cancel='0';
	$reject='0';
	$pending='0';
	$prev_date = date('Y-m-d', strtotime('today - 365 days'));
	$data = array();
	$status = array();
	$leave_data = array();

$leave = $mysqli->query("SELECT leave_id,leave_type,leave_day_type,leave_from,leave_to,status,cancel_leave,leave_description as leave_reason,leave_time FROM leave_applied WHERE emp_code = '$uniquecode' and `date_of_request` >= '".$prev_date."'  ORDER BY id DESC");
	if( $leave->num_rows > 0 ) {
	while( $row = $leave->fetch_assoc() )
	{
		$leave_data[] = $row;  
	}	

	$sql = "select  `status`,COUNT(status) as COUNT from `leave_applied` where `emp_code`='".$uniquecode."' and `date_of_request` >= '".$prev_date."'  GROUP BY `status`";
	$result = $mysqli->query($sql);
	if( $result->num_rows > 0 ) {
		while ($row1 = $result->fetch_assoc() ) {
			if($row1['status']=='Approved') {
				$approve = $row1['COUNT']; 
			} elseif( $row1['status']=='Cancelled' ) {
				$cancel = $row1['COUNT'];
			} elseif( $row1['status']=='Rejected' ) {
				$reject = $row1['COUNT'];
			} elseif( $row1['status']=='Pending' ) {
				$pending = $row1['COUNT'];
			}
		}  
	}

	$sta_q = $mysqli->query("select distinct status as st from `leave_applied` where emp_code = '$uniquecode' and `date_of_request` >= '".$prev_date."' ");
	if( $sta_q->num_rows > 0 ) {
		while($row2 = $sta_q->fetch_assoc() ) {
			$status[] = $row2['st'];
		}
	}
	
	$code = "LEA";
	$txn = txn_id($code);
	update_txn($txn['txn_id'], $txn['type'], $uniquecode, $user_data['role'], $device);
	
	$data = array("leaves"=>$leave_data, 'approved'=>$approve, 'cancel'=>$cancel, 'reject'=>$reject, 'pending'=>$pending, 'status'=>$status, 'leave_balance'=>'0');
	echo json_encode(array('status'=>'1', 'data'=>$data, 'message'=>'User Leave'));
	
    } else 
	{
		echo json_encode(array('status'=>'0', 'message'=>'No leave applied'));
	}
	
	
} else {
	exception($uniquecode, basename($_SERVER['PHP_SELF']), $json, 'Invalid User', $user_data['role'], $device);
	echo json_encode(array('status'=>'0', 'message'=>'Invalid User'));
}

?>