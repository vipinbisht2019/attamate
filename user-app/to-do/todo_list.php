<?php 
include('../../config/config.php');
include('../../config/functions.php');

$uniquecode = blank($get->uniquecode);
$emp_code = $get->emp_code;
$token = blank($get->token);
$device = blank($get->device);

//======= Authinticate first =========
	authenticate($uniquecode, $token);
//====================================

$user = user($uniquecode);
if( $user->num_rows > 0 ) {

	$user_data = $user->fetch_assoc();
	
	
	if( !empty($emp_code) ){
	    $cc = $emp_code;
	} else {
	    $cc = $uniquecode;
	}
    
    $query = 'select `project_client_name` from `todo` where emp_code="'.$cc.'" and `project_deleted`="" and `task_deleted`="" group by project_client_name ';//and (`task_status`="" or `task_status`="up")
    $result = $mysqli->query($query);
    if( $result->num_rows > 0 ){
        $data1 = array();
        while( $row = $result->fetch_assoc() ){
            $data = array();
            $query1 = 'select id,task,bandwidth,date,task_status,created_at,updated_at from `todo` where emp_code="'.$cc.'" and project_client_name="'.$row['project_client_name'].'" and `project_deleted`="" and `task_deleted`="" ';//and (`task_status`="" or `task_status`="down")
            $result1 = $mysqli->query($query1);
            if( $result1->num_rows > 0 ){
                while( $row1 = $result1->fetch_assoc() ){
                    if( (date('Y-m-d') != $row1['date']) && $row1['task_status'] == 'up' ){}else{
                        $data[] = $row1;
                    }
                }
            }
            $data1['project_name'] = $row['project_client_name'];
            $data1['tasks'] = $data;
            
            $data3[] = $data1;
        }
        
        $band_q = "select AVG(bandwidth) as band from `todo` where emp_code='".$cc."' and task_deleted='' and project_deleted='' and task_status in ('','down') ";
        $re_band_q = $mysqli->query($band_q);
        if( $re_band_q->num_rows > 0 ){
            $band_data = $re_band_q->fetch_assoc();
			$bandwidth = round($band_data['band'], 0);
        } else {
            $bandwidth = '0';
        }
        
        $data2['bandwidth'] = $bandwidth;
        $data2['projects'] = $data3;
        
        echo json_encode(array('status'=>'1', 'data'=>$data2, 'message'=>''));
        
    } else {
        $data2 = new stdClass;
        echo json_encode(array('status'=>'0', 'data'=>$data2, 'message'=>'No Data'));
    }

} else {
	exception($uniquecode, basename($_SERVER['PHP_SELF']), $json, 'Invalid User', $user_data['role'], $device);
	echo json_encode(array('status'=>'0', 'data'=>'', 'message' => 'Invalid User'));
}


?>