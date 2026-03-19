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
	$data5 = $data6 = $data2 = array();
	
	$sql5 = "select `name`,`emp_code` from user where `manager_code`='".$uniquecode."' and role='user' and status='Approved' ";
	$result5 = $mysqli->query($sql5);
	if( $result5->num_rows  > 0 ) {
		while( $row5 = $result5->fetch_assoc() )
		{
		    $data2 = $data6 = array();
			$query = 'select `project_client_name` from `todo` where emp_code="'.$row5['emp_code'].'" and `project_deleted`="" and `task_deleted`="" and (`task_status`="" or `task_status`="up") group by project_client_name ';
            $result = $mysqli->query($query);
            if( $result->num_rows > 0 ){
                $data1 = $data3 = array();
                while( $row = $result->fetch_assoc() ){
                    $data = array();
                    $query1 = 'select id,task,bandwidth,date,task_status,created_at,updated_at from `todo` where emp_code="'.$uniquecode.'" and project_client_name="'.$row['project_client_name'].'" and `project_deleted`="" and `task_deleted`="" and (`task_status`="" or `task_status`="down") ';
                    $result1 = $mysqli->query($query1);
                    if( $result1->num_rows > 0 ){
                        $data = array();
                        while( $row1 = $result1->fetch_assoc() ){
                            $data[] = $row1;
                        }
                    }
                    $data1['project_name'] = $row['project_client_name'];
                    $data1['tasks'] = $data;
                    
                    $data3[] = $data1;
                }
                $data2['bandwidth'] = '1';
                $data2['projects'] = $data3;
                
                $data6[] = $data2;
            }
            $data5['emp_name'] = $row5['name'];
            $data5['emp_code'] = $row5['emp_code'];
		    $data5['emp_data'] = $data6;
		    
		    $data7[] = $data5;
		}
	}

	echo json_encode(array('status'=>'1', 'data'=>$data7, 'message'=>''));

} else {
	exception($uniquecode, basename($_SERVER['PHP_SELF']), $json, 'Invalid User', $user_data['role'], $device);
	echo json_encode(array('status'=>'0', 'data'=>'', 'message' => 'Invalid User'));
}


?>