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
	$prev_date = date('Y-m-d', strtotime('today - 30 days'));
	$data = array();
	$status = array();
	$leave_data = array();

	$leave = $mysqli->query("SELECT user.name,leave_id,leave_from,leave_to,leave_applied.status,cancel_leave,leave_description as leave_reason,leave_type as leave_days,leave_time FROM leave_applied left join user on `user`.`emp_code`=`leave_applied`.`emp_code` WHERE `user`.`manager_code` = '$uniquecode' and `date_of_request` >= '".$prev_date."'  ORDER BY leave_applied.id DESC");
	while( $row = $leave->fetch_assoc() )
	{
	    $row['leave_balance'] = '0';
		$leave_data[] = $row;  
	}	

	$sql = "select  leave_applied.`status`,COUNT(leave_applied.status) as COUNT from `leave_applied` left join user on `user`.`emp_code`=`leave_applied`.`emp_code` where `user`.`manager_code` = '$uniquecode' and `date_of_request` >= '".$prev_date."'  GROUP BY leave_applied.`status`";
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

	$sta_q = $mysqli->query("select distinct leave_applied.status as st from `leave_applied` left join user on `user`.`emp_code`=`leave_applied`.`emp_code` where `user`.`manager_code` = '$uniquecode' and `date_of_request` >= '".$prev_date."' ");
	if( $sta_q->num_rows > 0 ) {
		while($row2 = $sta_q->fetch_assoc() ) {
			$status[] = $row2['st'];
		}
	}

	$data = array("leaves"=>$leave_data, 'approved'=>$approve, 'cancel'=>$cancel, 'reject'=>$reject, 'pending'=>$pending, 'status'=>$status);
	echo json_encode(array('status'=>'1', 'data'=>$data, 'message'=>''));


} else {
	exception($uniquecode, basename($_SERVER['PHP_SELF']), $json, 'Invalid User', $user_data['role'], $device);
	echo json_encode(array('status'=>'0', 'data'=>'', 'message' => 'Invalid User'));
}

?>