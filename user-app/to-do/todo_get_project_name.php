<?php 
include('../../config/config.php');
include('../../config/functions.php');

$uniquecode = blank($get->uniquecode);
$token = blank($get->token);
$device = blank($get->device);

//======= Authinticate first =========
	//authenticate($uniquecode, $token);
//====================================

$user = user($uniquecode);
if( $user->num_rows > 0 ) {

	$user_data = $user->fetch_assoc();
	$data = array();
    
    $query = 'select `project_name` from `todo_project` where emp_code="'.$uniquecode.'" ';
    $result = $mysqli->query($query);
    if( $result->num_rows > 0 ){
        while( $row = $result->fetch_assoc() ){
            $data[] = $row;
        }
    }

	echo json_encode(array('status'=>'1', 'data'=>$data, 'message'=>''));

} else {
	exception($uniquecode, basename($_SERVER['PHP_SELF']), $json, 'Invalid User', $user_data['role'], $device);
	echo json_encode(array('status'=>'0', 'data'=>'', 'message' => 'Invalid User'));
}


?>