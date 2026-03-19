<?php 

//======== Real escape string ============
function escape($text) {
	global $mysqli;
	return $mysqli->real_escape_string($text);
}

//========= Empty check ==========
function blank($var, $name='', $check='', $uniquecode='', $token='') {
	if( !empty($name) ){
		if( empty($var) ) {
			echo json_encode(array('status'=>'0', 'data'=>'', 'message'=>"Check Your Input (".$name.")"));
			exit;
		}  else {
			return escape($var);
		}
	} else {
		if( empty($var) ) {
			echo json_encode(array('status'=>'0', 'data'=>'', 'message'=>"Check Your Input."));
			exit;
		} else {
			return escape($var);
		}
	}
}

//============ Uniquecode from token ==============
function kre_code($token){
	global $mysqli;
	$sql = "select `kre_code` from `user` where `token`='".$token."' ";
	$result = $mysqli->query($sql);
	if( $result->num_rows > 0 ){
		$row = $result->fetch_assoc();
		$kre_code = $row['kre_code'];
	} else {
		$kre_code = '';
	}
	return $kre_code;
}

//============ Device data from kre code ==============
function device_data($kre_code){
	global $mysqli;
	$sql = "select `device_info` from `transaction` where `uniquecode`='".$kre_code."' order by id desc limit 1 ";
	$result = $mysqli->query($sql);
	if( $result->num_rows > 0 ){
		$row = $result->fetch_assoc();
		$device = $row['device_info'];
	} else {
		$device = 'No device data';
	}
	return $device;
}

//=========== Exception entry =========
function exception($code='', $api='', $input='', $msg='', $role='', $device='') {
	$insert_array = array(
		"api" => $api,
		"json_input" => $input,
		"error_msg" => $msg,
		"uniquecode" => $code,
		"role" => $role,
		"date" => date("Y-m-d"),
		"time" => date("H:i:s"),
		"device_info" => $device,
		"ip" => $_SERVER['REMOTE_ADDR'],
	);
	$exception = insert($insert_array, 'exception_log');
	return true;
}
//=========== Query Exception entry =========
function query_exception($code='', $api='', $query='', $input='', $msg='', $role='', $device='', $master_txn='') {
	$insert_array = array(
		"uniquecode" => $code,
		"role" => $role,
		"api" => $api,
		"query" => json_encode($query),
		"json_input" => $input,
		"message" => $msg,
		"date" => date("Y-m-d"),
		"time" => date("H:i:s"),
		"device_info" => $device,
		"status" => "Pending",
		"group_txn" => $master_txn,
		"ip" => $_SERVER['REMOTE_ADDR'],
	);
	$query_exception = insert($insert_array, 'query_log');
	send_mail('Query Exception', 'Query Exception occured in api '.$api.' kindly check.');
	return true;
}

//======== Authentication Function =========
function authenticate($unique_code, $token) {
	global $mysqli;
	if( empty($unique_code) ) {
		echo json_encode(array('status'=>'0', 'data'=>'', 'message'=>'Check Your Input.'));
		exit;
	}
	$sql = "select * from `user` where emp_code='".$unique_code."' and `token`='".$token."' ";
	$result = $mysqli->query($sql);
	if( $result->num_rows > 0 ) {
		return true;
	} else {
		$data = array('status'=>'-1', 'data'=>'', 'message'=>'Authentication Failed!');
		echo json_encode($data);
		exit;
	}
}

//============ ios decrypt ============
function ios_decrypt($key, $data) {
	$data = base64_decode($data);
	if(16 !== strlen($key)) $key = hash('MD5', $key, true);
	$data = mcrypt_decrypt(MCRYPT_RIJNDAEL_128, $key, $data, MCRYPT_MODE_CBC, str_repeat("\0", 16));
	$padding = ord($data[strlen($data) - 1]); 
	return substr($data, 0, -$padding); 
}

//========= Decrypt =====================
function decrypt($Str, $Key) {
	$decrypted= mcrypt_decrypt(
		MCRYPT_RIJNDAEL_128,
		$Key,
		base64_decode($Str),
		MCRYPT_MODE_ECB
	);
	$dec_s = strlen($decrypted);
	$padding = ord($decrypted[$dec_s-1]);
	$decrypted = substr($decrypted, 0, -$padding);
	return $decrypted;
}

//============= Master Log txn id ============== 
function txn_id($code) { 
	global $mysqli;
	$type = txn_type($code);
	$sql = "select `txn_id` from `transaction` where `txn_id` LIKE '$code%' order by `id` desc limit 1"; 
	$result = $mysqli->query($sql); 
	if( $result->num_rows > 0  ) { 
		$row = $result->fetch_assoc();
		$arr = array(
			"txn_id" => $code.date("dmy",time()).(substr($row['txn_id'], -7)+1),
			"type" => $type,
		); 
		return $arr; 
	} else { 
		$arr = array(
			"txn_id" => $code.date("dmy",time()).'1000001',
			"type" => $type,
		); 
		return $arr;
	} 
}

//========== txn type ==========================
function txn_type($code) {
	global $mysqli;
	$sql = "select `type` from `transaction_code` where `code`='".$code."' "; 
	$result = $mysqli->query($sql); 
	if( $result->num_rows > 0  ) { 
		$row = $result->fetch_assoc();
		return $row['type']; 
	} else { 
		return $type=""; 
	}
}

//============= Update master txn table ======== 
function update_txn($txn_id, $type, $uniquecode, $role, $device_info, $status='',$platform='App') { 
	$insert_array = array( 
		"date_created" => date("Y-m-d"), 
		"time_created" => date("H:i:s"), 
		"txn_id" => $txn_id, 
		"txn_type" => $type, 
		"uniquecode" => $uniquecode, 
		"role" => $role, 
		"platform" => $platform,
		"device_info" => $device_info, 
		"status" => $status,
		"ip_add" => $_SERVER['REMOTE_ADDR'],
	); 
	$transaction = insert($insert_array, 'transaction'); 
	return true; 
}

//=========== User =====================
function user( $uniquecode ) {
	global $mysqli;
	$kre = $mysqli->query("SELECT * FROM user WHERE emp_code = '$uniquecode'  ");
	return $kre;
}

//============== Kre data query ========
function kre_data( $uniquecode ) {
	global $mysqli;
	$kre_data = $mysqli->query("SELECT * FROM user WHERE kre_code = '$uniquecode' AND role = 'kre'");
	return $kre_data;
}

//=========== Kre =====================
function kre( $uniquecode ) {
	global $mysqli;
	$kre = $mysqli->query("SELECT * FROM user WHERE kre_code = '$uniquecode' AND role = 'kre' ");
	return $kre;
}

//=========== retailer ================
function retailer( $uniquecode ) {
	global $mysqli;
	$retailer = $mysqli->query("SELECT * FROM user WHERE retailer_code = '$uniquecode' AND role = 'retailer' ");
	return $retailer;
}

//=========== am =============
function am( $uniquecode ) {
	global $mysqli;
	$am = $mysqli->query("SELECT * FROM user WHERE am_code = '$uniquecode' AND role = 'am'");
	return $am;
}

//========= Insert query function ======
function insert($array, $table) {
	global $mysqli;
	$query = "";
			
	if(! is_array($array) ) {
		die("ERROR: Invalid Operation.");
	}
	foreach($array as $key => $value ) {
	  $query .= "`$key`='$value',";   		
	}
	$query = " insert into `$table` set ".substr($query, 0, -1);

	$mysqli->query($query) or die($mysqli->error);
	return $mysqli->insert_id;
	//return true;
} 

//============= Update query ==========================
function update($array, $table, $where) {
	global $mysqli;
	$query = "";	

	if( !is_array($array) ) {
		die("Invalid Array");
	} else {
		foreach($array as $key => $value ) {
			$query .= "`$key`='$value',";   		
		}
	}
	
	if( !is_array($where) ) {
		$where = " where ".$where;
	} else {
		foreach($where as $key => $value ) {
			$where .= "`$key`='$value' and";
		}
		$where = substr($where, 0, -3);
	}

	$query = "update `$table` set ".substr($query,0,-1).$where;
	$mysqli->query($query) or die($mysqli->error);
	if( $mysqli->affected_rows > 0 ) {
		return true;
	} else {
		return false;
	}
} 

//============== Send Notification ===================
function send_notification($uniquecode,$role,$type) {
	global $mysqli;
	$user = user($uniquecode);
	$user_data = $user->fetch_assoc();

	$manager = user($user_data['manager_code']);
	$manager_data = $manager->fetch_assoc();

	$registrationIds[] = $manager_data['device_token'];

	if( $type == 'leave' ){
		$title = 'Leave Applied';
		$message = 'A new leave request is applied by '.$user_data['name'];
	}

	$insert_array = array(
		"date" => date("Y-m-d"),
		"title" => $title,
		"message" => $message,
		"emp_code" => $user_data['manager_code'],
		"emp_name" => $user_data['manager_name'],
		"view_status" => '',
	);
	$notif = insert($insert_array, 'notifications');

	define( 'API_ACCESS_KEY', 'AAAAjq1Pl4I:APA91bFRGPykeQOwJSX5_E5VqSxOh07RDoUeH4oZuySCag1D9gzVr1x7VLErVwnA0tlCKyh6ZMdcEWXHusbJ10dmg0mzzSoL_JL1p-1kwmO3EY2XNpkcTqrHgkTc_vM9XS3xWmW4dipF' );

	$msg = array(
		'message'   => $message,
		'title'     => $title
	);
	
	$msg1 = array(
        "body" => $message,
        "message" => $message,
        "title" => $title,
        "sound" => 1,
        "vibrate" => 1,
        "badge" => 1,
        "mutable-content" => 1,
    );
	
	$fields = array(
		'registration_ids'  => $registrationIds,
		'data'          => $msg,
	    'priority' => 'high',
		'notification' => $msg1
	);
	 	
	$headers = array(
		'Authorization: key=' . API_ACCESS_KEY,
		'Content-Type: application/json'
	);
		
	 
	$ch = curl_init();
	curl_setopt( $ch,CURLOPT_URL, 'https://android.googleapis.com/gcm/send' );
	curl_setopt( $ch,CURLOPT_POST, true );
	curl_setopt( $ch,CURLOPT_HTTPHEADER, $headers );
	curl_setopt( $ch,CURLOPT_RETURNTRANSFER, true );
	curl_setopt( $ch,CURLOPT_SSL_VERIFYPEER, false );
	curl_setopt( $ch,CURLOPT_POSTFIELDS, json_encode( $fields ) );
	$result = curl_exec($ch );
	//print_r($result);
	curl_close( $ch );
}

//============== create date range array ================
function create_date_range_array($strDateFrom,$strDateTo) {
	$aryRange = array();
	$iDateFrom = mktime(1,0,0,substr($strDateFrom,5,2), substr($strDateFrom,8,2), substr($strDateFrom,0,4));
	$iDateTo = mktime(1,0,0,substr($strDateTo,5,2), substr($strDateTo,8,2), substr($strDateTo,0,4));

	if ($iDateTo>=$iDateFrom) {
		array_push($aryRange,date('Y-m-d',$iDateFrom)); // first entry
		while ($iDateFrom<$iDateTo)
		{
			$iDateFrom+=86400; // add 24 hours
			array_push($aryRange,date('Y-m-d',$iDateFrom));
		}
	}
	return $aryRange;
}

//================== Distance calculator ===================
function distance($lat1, $lon1, $lat2, $lon2, $unit) {

	$theta = $lon1 - $lon2;
	$dist = sin(deg2rad($lat1)) * sin(deg2rad($lat2)) +  cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * cos(deg2rad($theta));
	$dist = acos($dist);
	$dist = rad2deg($dist);
	$miles = $dist * 60 * 1.1515;
	$unit = strtoupper($unit);

	if ($unit == "K") {
		return ($miles * 1.609344);
	} else if ($unit == "N") {
		return ($miles * 0.8684);
	} else {
		return $miles;
	}
}

//=================== Get extention =================
function get_extention($file) {
	return pathinfo($file['name'], PATHINFO_EXTENSION);
}

//================= Send Mail ======================
function send_mail($subject,$body) {
	require 'phpmailer/PHPMailerAutoload.php';

	$mail = new PHPMailer;
    $mail->isSMTP();
    $mail->Host = 'smtp.gmail.com';
    $mail->Port = 587;
    $mail->SMTPSecure = 'tls';
    $mail->SMTPAuth = true;
    $mail->Username = 'teamaddes@gmail.com';
    $mail->Password = 'triadweb.in';
    $mail->setFrom('info@triad01.com', 'Triad01');
    $mail->addReplyTo('info@triad01.com', 'Triad01');
    $mail->addAddress('ashish@triadmarketingservices.com');
    $mail->addCC('nishant@triadmarketingservices.com','agoel@triadmarketingservices.com','rohit@triadweb.in');
    $mail->Subject = $subject;
    $mail->msgHTML($body);
    if (!$mail->send()) {
       $error = "Mailer Error: " . $mail->ErrorInfo;
    }
}

//============= Send In out mail ==============
function send_att_mail($emp_code){

    global $mysqli;
    if( $emp_code == 'TMS044' ){//
    
        $sql = "select * from attendancetable where emp_code='".$emp_code."' order by id desc limit 1";
        $result = $mysqli->query($sql);
        $row = $result->fetch_assoc();
        
        $time = $row['time'];
        $image = $row['image'];
        $lat = $row['lat'];
        $lon = $row['lon'];
    
        if( $row['status'] == 'IN' ){
        
$str = <<<EOD
In time : $time
<br>
In Image :  $image 
<br>
In Address : https://www.google.com/maps/search/?api=1&query=$lat,$lon
EOD;
        
        } else {
        
$str = <<<EOD
Out Time : $time
<br>
Out Image : $image
<br>
Out Address : https://www.google.com/maps/search/?api=1&query=$lat,$lon
EOD;
        
        }
        
        send_mail('Nitin Attendance', $str);
        
    }
	
}

//=========== Movement register entry =========
function movement_entry($emp_code, $emp_name, $place, $in_time='', $out_time='', $working_hours='', $remarks='', $latitude='', $longitude='', $type, $date) {
	global $mysqli;
	
	if( $type == 'insert' ){
		$insert_array = array(
			"emp_code" => $emp_code,
			"emp_name" => $emp_name,
			"date" => $date,
			"in_time" => $in_time,
			"remarks" => $remarks,
			"place" => $place,
			"latitude" => $latitude,
			"longitude" => $longitude,
			"app" => "app",
		);
		$movement = insert($insert_array, 'movement_register');
	} else {

		$sql = "select * from `movement_register` where `emp_code`='".$emp_code."' and `date`='".$date."' order by `id` desc limit 1 ";
		$result = $mysqli->query($sql);
		if( $result->num_rows > 0 ){
			$row = $result->fetch_assoc();
			$id = $row['id'];

			$update_array = array(
				'out_time' => $out_time,
				'working_hours' => $working_hours,
			);
			$where = " id='".$id."' ";
			$update = update($update_array, 'movement_register', $where);
		}
	}
	return true;
} 


function task_check($uniquecode, $date) {
	global $mysqli;
	global $today;
	
	$d = '0';
	
	$qq = "select * from task where `emp_code`='".$uniquecode."' and `date`='".$date."' ";
	$re_qq = $mysqli->query($qq);
	if( $re_qq->num_rows > 0 ){
		$d = '0';
	} else {
		$d = '1';
		return $d;
	}
	
	return $d;
}

?>