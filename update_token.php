<?php  
include('config/config.php');
include('config/functions.php');

$channel_code = $get->uniquecode;
$device_token = $get->firebase_token;
$token = "";
// error_log($json);
if(!empty($channel_code)){
	$sql = "update user set device_token='$device_token' where emp_code='$channel_code' ";
	if( $mysqli->query($sql) ){
		echo "success";
		exit();
	} else {
		echo "fail";
		exit();
	}
} else {
	echo "Uniquecode is required.";
	exit();
}


?>