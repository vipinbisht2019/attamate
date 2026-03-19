<?php 
include('../../config/config.php');
include('../../config/functions.php');

$uniquecode = blank($get->uniquecode);
$token = blank($get->token);
$device = blank($get->device);

//======= Authinticate first =========
	authenticate($uniquecode, $token);
//====================================

$user = user($uniquecode);
if( $user->num_rows > 0 ) {

	$user_data = $user->fetch_assoc();

	$d = date('d');
	if( $d <= '1' ){
	    $date = date("Y-m-d", strtotime("first day of last month"));
	    $date1 = date("Y-m-d", strtotime("last day of last month"));
	} else {
	    $date = date('Y-m').'-01';
        $date1 = date('Y-m-d');
	}
	
	$list = array();
	$request = array();

    
	$sql = "select * from `attendance` where emp_code='".$uniquecode."' and `date`>='".$date."' and `date`<='".$date1."' and `halfs`='Auto out' order by `date` desc ";
	$result = $mysqli->query($sql);
	if( $result->num_rows > 0 ){
		while( $row = $result->fetch_assoc() ){
		    
		  /*  $sql2 = "select * from `auto_out_fix_entries` where emp_code='".$uniquecode."' and `for_date`='".$row['date']."' ";
		    $result2 = $mysqli->query($sql2);
		    if( $result2->num_rows > 0 ){
		        
		    } else {
		        */
		        $list[] = $row;
		  //  }
			
		}
	}

	$sql1 = "select * from `auto_out_fix_entries` where `emp_code`='".$uniquecode."' and `for_date`>='".$date."' and `for_date`<='".$date1."' order by `request_date` desc ";
	$result1 = $mysqli->query($sql1);
	if( $result1->num_rows > 0 ){
		while( $row1 = $result1->fetch_assoc() ){
			$request[] = $row1;
		}
	}
	
    

	$data = array('auto_out_list'=>$list, 'request'=>$request);
	echo json_encode(array('status'=>'1', 'data'=>$data, 'message'=>'successful'));

} else {
	exception($uniquecode, basename($_SERVER['PHP_SELF']), $json, 'Invalid User', $user_data['role'], $device);
	echo json_encode(array('status'=>'0', 'data'=>'', 'message' => 'Invalid User'));
}


?>