<?php  
include('../config/config.php');
include('../config/functions.php');

//error_log(json_encode($json));
if(array_key_exists('iOS',$get)){
	$ios = $get->iOS;
}else{
	$ios = '';
}

if( empty($ios)){
//	echo "hello"; die;
   //  $old_password = blank(md5(decrypt($get->old_password, $key)));
   //  $new_password = blank(md5(decrypt($get->new_password, $key)));
	
	 $old_password = blank(md5($get->old_password));
     $new_password = blank(md5($get->new_password));
} else {
	
	// $old_password = blank(md5(ios_decrypt($key, $get->old_password)));
	// $new_password = blank(md5(ios_decrypt($key, $get->new_password)));
	
	$old_password = blank(md5($get->old_password));
    $new_password = blank(md5($get->new_password));
}
$uniquecode = blank($get->uniquecode);
$device = blank($get->device);
$token = blank($get->token);

//======= Authinticate first =========
	authenticate($uniquecode, $token);
//====================================


$user = user( $uniquecode );
if( $user->num_rows > 0 ) { 

	$user_data = $user->fetch_assoc();
	
	$fetch = $mysqli->query("SELECT * FROM user WHERE password = '$old_password' AND emp_code = '$uniquecode' ");
	if( $fetch->num_rows > 0 )
	{
		$txn = txn_id("CPD");

		$result = $mysqli->query("update user set password = '$new_password',first_login='0' where emp_code = '$uniquecode' ");
		if( $mysqli->affected_rows > 0 ) {

			//========= master txn -==================
			update_txn($txn['txn_id'], $txn['type'], $uniquecode, $user_data['role'], $device);
			//========================================
			echo  json_encode(array('status'=>'2', 'message'=>'Password update successfully'));

		} else {
			exception($uniquecode, basename($_SERVER['PHP_SELF']), $json, 'Password not update try again', $user_data['role'], $device);
		    echo  json_encode(array('status'=>'0', 'message'=>'Password not update try again'));
		}

	} else {
		
		exception($uniquecode, basename($_SERVER['PHP_SELF']), $json, 'Wrong old password inserted', $user_data['role'], $device);
		echo  json_encode(array('status'=>'0', 'message'=>'Wrong old password inserted'));
	}

} else {
	exception($uniquecode, basename($_SERVER['PHP_SELF']), $json, 'Invalid User', $user_data['role'], $device);
	echo  json_encode(array('status'=>'0', 'message'=>'Invalid User'));
}



?>