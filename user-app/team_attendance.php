<?php 
include('../config/config.php');
include('../config/functions.php');

$uniquecode = blank($get->uniquecode);
$emp_code = blank($get->emp_code, 'Emp code');
$month = $get->month;
$token = blank($get->token);
$device = blank($get->device);
$data = array();

//======= Authinticate first =========
	authenticate($uniquecode, $token);
//====================================

$user = user($uniquecode);
if( $user->num_rows > 0 ) {

	$user_data = $user->fetch_assoc();
	$start_date = date('Y-m').'-01';
	$end_date = $today;

	if( !empty($month) ) {
		$start_date = date("Y-m-d", strtotime("first day of -$month month"));
		$end_date = date("Y-m-d", strtotime("last day of -$month month"));
	}


    $q = "SELECT `date`,`in_time`,`out_time`,halfs as compliance, `user`.`name`,`working_hours` FROM attendance left join `user` ON `user`.`emp_code`=`attendance`.`emp_code` WHERE `attendance`.`emp_code` = '$emp_code' and `attendance`.`date`>='".$start_date."' and `attendance`.`date`<='".$end_date."' order by `date` desc ";
	$att = $mysqli->query($q);
	if( $att->num_rows  > 0 ) {
		while( $row = $att->fetch_assoc() )
		{
			$data[] = $row;
		}
	}

	if( count($data) > 0 ){
		echo json_encode(array('status'=>'1', 'data'=>$data, 'message'=>''));
	} else {
		echo json_encode(array('status'=>'0', 'data'=>$data, 'message'=>'No Data'));
	}

} else {
	exception($uniquecode, basename($_SERVER['PHP_SELF']), $json, 'Invalid User', 'kre', $device);
	echo json_encode(array('status'=>'0', 'data'=>'', 'message' => 'Invalid User'));
}


?>