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
	$date = date('Y-m').'-01';
	$self = array();
	$team = array();

	$sql = "select * from `movement_register` where emp_code='".$uniquecode."' and `movement_register`.`date`>='".$date."' and `movement_register`.`date`<='".$today."' order by `date` desc ";
	$result = $mysqli->query($sql);
	if( $result->num_rows > 0 ){
		while( $row = $result->fetch_assoc() ){
			$self[] = $row;
		}
	}

	if( $user_data['role'] == 'manager' ){

		$sql1 = "select movement_register.* FROM movement_register left join `user` ON `user`.`emp_code`=`movement_register`.`emp_code` WHERE `user`.`manager_code` = '$uniquecode' and `movement_register`.`date`>='".$date."' and `movement_register`.`date`<='".$today."' and user.role='user' order by `date` desc ";
		$result1 = $mysqli->query($sql1);
		if( $result1->num_rows > 0 ){
			while( $row1 = $result1->fetch_assoc() ){
				$team[] = $row1;
			}
		}
	}

	$data = array('self'=>$self, 'team'=>$team, 'myself'=>$self);
	echo json_encode(array('status'=>'1', 'data'=>$data, 'message'=>''));

} else {
	exception($uniquecode, basename($_SERVER['PHP_SELF']), $json, 'Invalid User', 'kre', $device);
	echo json_encode(array('status'=>'0', 'data'=>'', 'message' => 'Invalid User'));
}


?>