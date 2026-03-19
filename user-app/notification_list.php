<?php  
include('../config/config.php');
include('../config/functions.php');

$uniquecode = blank($get->uniquecode);
$token = blank($get->token);

//======= Authinticate first =========
	authenticate($uniquecode, $token);
//====================================

$data = array();

$result = $mysqli->query("SELECT *,date as date_time FROM notifications WHERE emp_code = '$uniquecode' order by id desc");
$update = $mysqli->query("UPDATE notifications set view_status='read' where emp_code= '$uniquecode'");
if( $result->num_rows > 0 ) {
	while( $row = $result->fetch_assoc() )
	{
		$data[] = $row;
	}
}

if( count($data) > 0 ) {
	echo json_encode(array('status'=>'1', 'data'=>$data, 'message'=>''));
} else {
	echo json_encode(array('status'=>'2', 'data'=>'No notifications', 'message'=>''));
}


?>