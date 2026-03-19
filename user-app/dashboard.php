<?php 
include('../config/config.php');
include('../config/functions.php');

$uniquecode= blank($get->uniquecode, 'Uniquecode');
$token = blank($get->token, 'Token');
$device = blank($get->device, 'Device');
$current_month = date('Y-m').'-01';
$current_date = date("Y-m-d");

//======= Authinticate first =========
	authenticate($uniquecode, $token);
//====================================

$user_data = user($uniquecode);
if( $user_data->num_rows > 0 ) {
	
	$row = $user_data->fetch_assoc();
	$data = array();

	$sql = "select A as mtd, B as full_day, C as half_day, D as week_off, E as `leave`, F as absent, G as auto_out, H as sunday, I as holiday, J as avg_in, K as avg_out, L as avg_working_hours
		FROM ( SELECT COUNT(`date`) AS `A` FROM attendance WHERE emp_code = '$uniquecode' AND `date` >= '$current_month' and `halfs` NOT IN ('Absent','Leave')  ) A 
		CROSS JOIN ( SELECT COUNT(`date`) AS `B` FROM attendance WHERE emp_code = '$uniquecode' AND halfs = 'Full day' AND `date` >= '$current_month' ) B 
		CROSS JOIN ( SELECT COUNT(`date`) AS `C` FROM attendance WHERE emp_code = '$uniquecode' AND halfs = 'Half day' AND `date` >= '$current_month' ) C 
		CROSS JOIN ( SELECT COUNT(`date`) AS `D` FROM attendance WHERE emp_code = '$uniquecode' AND halfs = 'Week off' AND `date` >= '$current_month' ) D 
		CROSS JOIN ( SELECT COUNT(`date`) AS `E` FROM attendance WHERE emp_code = '$uniquecode' AND halfs = 'Leave' AND `date` >= '$current_month' ) E 
		CROSS JOIN ( SELECT COUNT(`date`) AS `F` FROM attendance WHERE emp_code = '$uniquecode' AND halfs = 'Absent' AND `date` >= '$current_month' ) F 
		CROSS JOIN ( SELECT COUNT(`date`) AS `G` FROM attendance WHERE emp_code = '$uniquecode' AND halfs = 'Auto out' AND `date` >= '$current_month' ) G
		CROSS JOIN ( SELECT COUNT(`date`) AS `H` FROM attendance WHERE emp_code = '$uniquecode' AND halfs = 'Sunday' AND `date` >= '$current_month' ) H
		CROSS JOIN ( SELECT COUNT(`date`) AS `I` FROM attendance WHERE emp_code = '$uniquecode' AND halfs = 'Holiday' AND `date` >= '$current_month' ) I
		CROSS JOIN ( SELECT SEC_TO_TIME(AVG(TIME_TO_SEC(`in_time`))) AS `J` FROM attendance WHERE emp_code = '$uniquecode' AND halfs IN ('Half day','Full day') AND `date` >= '$current_month' ) J
		CROSS JOIN ( SELECT SEC_TO_TIME(AVG(TIME_TO_SEC(`out_time`))) AS `K` FROM attendance WHERE emp_code = '$uniquecode' AND halfs IN ('Half day','Full day') AND `date` >= '$current_month' ) K
		CROSS JOIN ( SELECT SEC_TO_TIME(AVG(TIME_TO_SEC(`working_hours`))) AS `L` FROM attendance WHERE emp_code = '$uniquecode' AND halfs IN ('Half day','Full day') AND `date` >= '$current_month' ) L";
	$result = $mysqli->query($sql);
	if( $result->num_rows > 0 ){
		$row = $result->fetch_assoc();

		$mtd = $row['mtd'];
		$full_day = $row['full_day'];
		$half_day = $row['half_day'];
		$week_off = $row['week_off'];
		$leave = $row['leave'];
		$absent = $row['absent'];
		$auto_out = $row['auto_out'];
		$sunday = $row['sunday'];
		$holiday = $row['holiday'];
		$avg_in = (substr($row['avg_in'], 0, 8)==false)?'00:00:00':substr($row['avg_in'], 0, 8);
		$avg_out = (substr($row['avg_out'], 0, 8)==false)?'00:00:00':substr($row['avg_out'], 0, 8);
		$avg_working_hours = (substr($row['avg_working_hours'], 0, 8)==false)?'00:00:00':substr($row['avg_working_hours'], 0, 8);
		$cent = (1-($absent/$mtd))*100;
		$cent = "$cent";
	} else {

		$mtd = '0';
		$full_day = '0';
		$half_day = '0';
		$week_off = '0';
		$leave = '0';
		$absent = '0';
		$auto_out = '0';
		$sunday = '0';
		$holiday = '0';
		$avg_in = '00:00:00';
		$avg_out = '00:00:00';
		$avg_working_hours = '00:00:00';
		$cent = '0';
	}

	$attend = array(
		'MTD' => $mtd,
		'full_day' => $full_day,
		'half_day' => $half_day,
		'week_off' => $week_off,
		'leave' => $leave,
		'absent' => $absent,
		'auto_out' => $auto_out,
		'sunday' => $sunday,
		'holiday' => $holiday,
		'average_in' => $avg_in,
		'average_out' => $avg_out,
		'average_working_hour' => $avg_working_hours,
		'current_date' => $current_date,
		'percentage' => $cent,
	);

 	echo json_encode(array('status'=>'1', 'data'=>$attend, 'message'=>''));

} else {
	exception($uniquecode, basename($_SERVER['PHP_SELF']), $json, 'Invalid User', $row['role'], $device);
	echo json_encode(array('status'=>'0', 'data'=>'', 'message' => 'Invalid User'));
}

?>