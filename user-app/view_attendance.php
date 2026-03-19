<?php 
include('../config/config.php');
include('../config/functions.php');

$uniquecode = blank($get->uniquecode);
$month = $get->month;
$token = blank($get->token); 
$device = blank($get->device);
$data = array(); 
//======= Authenticate first =========
	authenticate($uniquecode, $token);
//====================================

date_default_timezone_set('Asia/Kolkata');
$curr_dates = date("Y-m-d");

$user = user($uniquecode);
if( $user->num_rows > 0 ) {

	$user_data = $user->fetch_assoc(); 
	$start_date = date('Y-m').'-01'; 
	 $end_date = $today;

	if( !empty($month) ) {

	/*	if (date('m', strtotime(date('Y-m')." -1 month")) == $month) { 
		
			$month1 = 1;
			 $start_date = date("Y-m-d", strtotime("first day of -$month1 month"));
			$end_date = date("Y-m-d", strtotime("last day of -$month1 month")) ; 
		}else{
			
			$start_date = date('Y-m').'-01'; 
			$end_date = $today;  
		
		}
		
	*/
		
		 $monthNum = $month;
		 $monthName = date("F", mktime(0, 0, 0, $monthNum, 10));
		
		$timestamp  = strtotime($monthName);
		$start_date = date('Y-m-01', $timestamp);
		$end_date   = date('Y-m-t', $timestamp);
		
	}


 // $q = "SELECT `date`,`in_time`,`out_time`,halfs as compliance, `user`.`name`,`working_hours` FROM attendance left join `user` ON `user`.`emp_code`=`attendance`.`emp_code` WHERE `attendance`.`emp_code` = '$uniquecode' and `attendance`.`date`>='".$start_date."' and `attendance`.`date`<='".$end_date."' order by `date` desc ";  


 $q = "SELECT `date`,`in_time`,`out_time`,halfs as compliance, `user`.`name`,`working_hours` FROM attendance left join `user` ON `user`.`emp_code`=`attendance`.`emp_code` WHERE `attendance`.`emp_code` = '$uniquecode' and `attendance`.`date`>='".$start_date."' and MONTH(`attendance`.`date`)<='".$month."' and `attendance`.`date`<='".date('Y-m-d')."' order by `date` desc ";  


	$att = $mysqli->query($q);
	if( $att->num_rows  > 0 ) {
		while( $row = $att->fetch_assoc() )
		{
			$data[] = $row;
		}
	}

	if( count($data) > 0 ){
		echo json_encode(array('status'=>'1', 'data'=>$data, 'message'=>'Monthly Attendance'));
	} else {
		echo json_encode(array('status'=>'0', 'data'=>$data, 'message'=>'No Data'));
	}

} else {
	exception($uniquecode, basename($_SERVER['PHP_SELF']), $json, 'Invalid User', 'kre', $device);
	echo json_encode(array('status'=>'0', 'message' => 'Invalid User'));
}


?>