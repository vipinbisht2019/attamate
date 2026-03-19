<?php 
include('../../config/config.php');
include('../../config/functions.php');

$uniquecode = blank($get->uniquecode);
$project_name = $get->project_name;
$date = blank($get->date, 'Date');
$team = blank($get->team, 'Team');
$task = $get->task;
$bandwidth = blank($get->bandwidth, 'Bandwidth');
$token = blank($get->token, 'Auth');
$device = blank($get->device);

//======= Authinticate first =========
	authenticate($uniquecode, $token);
//====================================

$user = user($uniquecode);
if( $user->num_rows > 0 ) {

	$user_data = $user->fetch_assoc();

    foreach($task as $key => $tas){
        
	    if( count($tas) > 0 ){
	        
	        foreach( $tas as $ta ){
	            $txn = txn_id("TOD");
        	    $master_txn = $txn['txn_id'];
	            $task_array = array(
        			"emp_code" => $uniquecode,
        			"emp_name" => $user_data['name'],
        			"team" => $team,
        			"project_client_name" => $project_name[$key],
        			"task" => trim($ta),
        			"bandwidth" => $bandwidth,
        			"date" => date('Y-m-d', strtotime($date)),
        			"month" => date('m'),
        			"year" => date('Y'),
        			"txn_id" => $master_txn,
        			"created_at" => date('Y-m-d H:i:s'),
        		);
        		$task_array_insert = insert($task_array, 'todo');
        		
        		update_txn($txn['txn_id'], $txn['type'], $uniquecode, $user_data['role'], $device);
	        }
	        
	    } else {
	        $txn = txn_id("TOD");
    	    $master_txn = $txn['txn_id'];
	        $task_array = array(
    			"emp_code" => $uniquecode,
    			"emp_name" => $user_data['name'],
    			"team" => $team,
    			"project_client_name" => $project_name[$key],
    			"task" => trim($tas[0]),
    			"bandwidth" => $bandwidth,
    			"date" => date('Y-m-d', strtotime($date)),
    			"month" => date('m'),
    			"year" => date('Y'),
    			"txn_id" => $master_txn,
    			"created_at" => date('Y-m-d H:i:s'),
    		);
    		$task_array_insert = insert($task_array, 'todo');
    		
    		update_txn($txn['txn_id'], $txn['type'], $uniquecode, $user_data['role'], $device);
	    }
    }
		
// 	if( $task_array_insert > 0 ){
		echo json_encode(array('status'=>'2', 'data'=> 'To do added successfully', 'message'=>''));
// 	} else {
// 		exception($uniquecode, basename($_SERVER['PHP_SELF']), $json, 'To do add request Failed', $user_data['role'], $device);
// 		echo json_encode(array('status'=>'0', 'data'=>'', 'message'=>'To do add request Failed'));
// 	}

} else {
	exception($uniquecode, basename($_SERVER['PHP_SELF']), $json, 'Invalid User', $user_data['role'], $device);
	echo json_encode(array('status'=>'0', 'data'=>'', 'message' => 'Invalid User'));
}


?>