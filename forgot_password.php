<?php  
include('config/config.php');
include('config/functions.php');
include('config/class.phpmailer.php');

$email = blank($get->email);
$device = blank($get->device);
$data = array();

$sql = "select * from user where email='".$email."'  ";
$result = $mysqli->query($sql);
if( $result->num_rows > 0 ){

	$row = $result->fetch_assoc();
	$username = $row['username'];
	$password = $row['password'];
	$uniquecode = $row['emp_code'];
	$role = $row['role'];
	//$email = $row['email'];
	$mobile = $row['phone'];
	$txn = txn_id("FPW");
	$txn_id = $txn['txn_id'];
	
	$subject = "Reset Password Detail";
	
	$new_passwd = substr( str_shuffle( '012345698745247563258745' ), 0, 5 );

$body = '<!DOCTYPE html>
<html lang="en">
<head>
</head>
<body>
<p>Dear User,</p>
<p>You have requested the password please find Details below.</p>
<p>Username : '.$username.'</p>
<p>Updated Password : '.$new_passwd.'</p>
<br><br>
<p>Regards, <br>Team TRIAD</p>

</body>
</html>';

		$send_from_mail = 'testt1@gmail.com';
 		$send_from_name = 'Tech Team ';
		
		$data_email=array(
		'from' => $send_from_mail,
		'email' => $email,
		'subject' => $subject,
		'body' => $body		
		);
		
		
		send_mail($data_email);
		
		
            $pwd = md5($new_passwd);
		   
            $pass_sql = "update user set `password`='".$pwd."',first_login='1' where `email`='".$email."' ";
		   // die;
            $result_pass = $mysqli->query($pass_sql);

      
	if( $result_pass ){
		update_txn($txn['txn_id'], $txn['type'], $uniquecode, $role, $device);
		echo json_encode(array('status'=>'2', 'message'=>'Password details has been sent successfully to your email Id'));
	} else {
		echo json_encode(array('status'=>'0', 'message'=>'Send Failed.'));
	}

} else {
	echo json_encode(array('status'=>'0', 'message'=>'Email Id not Exist !'));
}

?>