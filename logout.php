<?php  
include('config/config.php');
include('config/functions.php');

$uniquecode = blank($get->uniquecode, 'Unique Id');
$token = blank($get->token, 'Auth');
$role = blank($get->role, 'Role');
$device = blank($get->device, 'Device', 'device', $uniquecode);
$data = array();

//======= Authinticate first =========
	authenticate($uniquecode, $token, $role);
//====================================quotemeta(str)

// $SecretKey = date('Y')."t3Qo7xfdH1";
$token_new = bin2hex(openssl_random_pseudo_bytes(16));
// $t=$SecretKey.$token;
	

$sql = "update user set token='".$token_new."' where emp_code='$uniquecode' and role='".$role."' ";
if( $mysqli->query($sql) ){

	$txn = txn_id("LGT");
	
	update_txn($txn['txn_id'], $txn['type'], $uniquecode, $role, $device);
	echo json_encode(array('status'=>'1', 'message'=>'Logout Successfully'));
	exit();
} else {
	echo json_encode(array('status'=>'0', 'message'=>'Logout Failed'));
	exit();
}

?>