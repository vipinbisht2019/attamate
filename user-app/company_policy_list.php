<?php  
include('../config/config.php');
include('../config/functions.php');

$uniquecode = blank($get->uniquecode);
$label = blank($get->label);
$token = blank($get->token);
// $device = '';

//======= Authinticate first =========
	authenticate($uniquecode, $token);
//====================================

$data = array();

$sql_user = "SELECT * from user where emp_code = '$uniquecode' ";
$result_user = $mysqli->query($sql_user);
if( $result_user->num_rows > 0 ) {

	$user_data = $result_user->fetch_assoc();
	$office_name_user = $user_data['office_name'];
	
	$sql_office = "SELECT * from user where role = 'office' and office_name = '$office_name_user' ";
	$result_office = $mysqli->query($sql_office);
	if( $result_office->num_rows > 0 ) {
		
	$office_data = $result_office->fetch_assoc();
	$office_id = $office_data['id'];
	
	$sql_policy = "SELECT * from company_policy where company_name_id = '$office_id' and label = '".ucwords($label)."' ";
	$result_policy = $mysqli->query($sql_policy);
	if( $result_policy->num_rows > 0 ) {
		while($row = $result_policy->fetch_assoc()){
					$data[] = $row['document'];
			}
		}
	
		$code = "COM";
		$txn = txn_id($code);
		update_txn($txn['txn_id'], $txn['type'], $uniquecode, '', $device);

		if( count($data) > 0 ) {
			echo json_encode(array('status'=>'1', 'data'=>$data, 'message'=>'Successful'));
		} else {
			echo json_encode(array('status'=>'2', 'message'=>'No Company Policy'));
		}
	
	} else {
		echo json_encode(array('status'=>'3', 'message'=>'No Office'));
	}		
	
} else {
	
	echo json_encode(array('status'=>'0', 'message' => 'Invalid User'));
}

?>