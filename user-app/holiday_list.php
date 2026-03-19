<?php  
include('../config/config.php');
include('../config/functions.php');

$uniquecode = blank($get->uniquecode);
$token = blank($get->token);
$device = '';

//======= Authinticate first =========
	authenticate($uniquecode, $token);
//====================================

$data = array();
$year = date('Y');

$sql = "SELECT * from holiday where `start_date` like '$year%' and `end_date` like '$year%' ";
$result = $mysqli->query($sql);
if( $result->num_rows > 0 ) {
	while( $row = $result->fetch_assoc() )
	{
		$data[] = $row;
	}
}

$code = "HOL";
	$txn = txn_id($code);
	update_txn($txn['txn_id'], $txn['type'], $uniquecode, '', $device);

if( count($data) > 0 ) {
	echo json_encode(array('status'=>'1', 'data'=>$data, 'message'=>'All holiday'));
} else {
	echo json_encode(array('status'=>'2', 'message'=>'No Holiday'));
}


?>