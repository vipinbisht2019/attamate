<?php
include('../config/config.php');
include('../config/functions.php');

$get_data = json_decode($_POST['all_data']);
$uniquecode = blank($get_data->uniquecode);
$device = blank($get_data->device);
$token = blank($get_data->token, 'Auth');

//======= Authinticate first =========
	authenticate($uniquecode, $token);
//====================================

$user_check = user($uniquecode);
if( $user_check->num_rows > 0 ){

	$user_data = $user_check->fetch_assoc();

	if(isset($_FILES["image"])){
		$img_name = $_FILES["image"]["tmp_name"];
		$image_name = $uniquecode.'_'.time().".png";
		move_uploaded_file($img_name, '../data/user-image/'.$image_name);
		$image_name = base_url."data/user-image/".$image_name;
	} else {
		$image_name = '';
	}

	$update_array = array(
		"image" => $image_name,
	);
	$where = "emp_code='".$uniquecode."'";
	$result = update($update_array, 'user', $where);

	if( $result ) { 
		echo  json_encode(array('status'=>'2', 'data'=>'Image updated successfully', 'message'=>''));
	} else { 
		exception($uniquecode, basename($_SERVER['PHP_SELF']), $_POST['all_data'], 'Image not updated', $user_data['role'], $device);
		echo  json_encode(array('status'=>'0', 'data'=>'', 'message'=>'Image not updated'));
	}

} else {
	exception($uniquecode, basename($_SERVER['PHP_SELF']), $_POST['all_data'], 'Invalid User', $user_data['role'], $device);
	echo json_encode(array('status'=>'0', 'data'=>'', 'message'=>'Invalid User'));
	exit();
}
