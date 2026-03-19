<?php 

include('config/config.php');
include('config/functions.php');

$json = file_get_contents('php://input');
file_put_contents('log_request.txt', $json);

// $SecretKey = date('Y')."t3Qo7xfdH1";

$rand_number = rand(111111111111111,999999999999999);
$ios = empty($get->iOS)?'':$get->iOS;
if(@$get->type != 'postman') {
    if( empty($ios) ){ 
    	//$imei = blank($get->imei, 'IMEI');
    	//$imei = !empty($get->imei) ? $get->imei : $rand_number;
    	$login_id = blank($get->login_id, $key, 'Username'); 
    	$passwords = blank($get->password, $key, 'Password');
    	$password=trim(md5($passwords)); 
    } else {
    	//$imei = blank($get->uuid, 'UUID');
    	//$imei = !empty($get->uuid) ? $get->uuid : $rand_number;
    	$login_id = blank($key, $get->login_id, 'Username');
    	$passwords = blank($get->password, $key, 'Password');
    	$password=trim(md5($passwords));
    }
} else {
    $login_id = blank($get->login_id);
    $passwords = blank($get->password, $key, 'Password');
    $password=trim(md5($passwords));
}
$device = empty($get->device)?'No device data':$get->device;
$token = empty($get->token)?'':$get->token; 

/*
$login_type = blank($get->login_type, 'Type');
if($login_type == 'splash' ) {
	$code = "LOS";
} else {
	$code = "LOD";
}
*/

$code = "LOD";
 $location_data=array('latitude'=>'','longitude'=>'');

$login_query = "select * from `user` where `username`='".$login_id."'  and `password`='".$password."' ";
$login_result = $mysqli->query($login_query);
if( $login_result->num_rows > 0 ) {

	//======== get data ============
	$row = $login_result->fetch_assoc();
	
	//======= update token =========
	$token = bin2hex(openssl_random_pseudo_bytes(16));
	// $t=$SecretKey.$token;
	$token_update = $mysqli->query("update user set `token`='".$token."' where username='$login_id' AND password ='$password'");
	
	$txn = txn_id($code);

	//======== Check Approved ===========
	if( $row['status']=='Special' OR $row['status']=='Approved' ) {

		if( $row['role']=='user' OR $row['role']=='manager' OR $row['role']=='admin' ) {

			//==== creating data array =====
			$data = array(
				'username' => $login_id,
				'role' => $row['role'],
				'department' => $row['department'],
				'designation' => $row['designation'],
				'reporting_time' => $row['reporting_time'],
				'relaxation' => $row['relaxation'],
				'nature_of_job' => $row['nature_of_job'],
				'emp_code' => $row['emp_code'],
				'name' => $row['name'],
				'office_name' => $row['office_name'],
				'office_code' => $row['office_code'],
				'manager_name' => $row['manager_name'],
				'phone' => $row['phone'],
				'email' => $row['email'],
				'address' => $row['address'],
				'status' => $row['status'],
				'first_login' => $row['first_login'],
				'gender' => $row['gender'],
				'image' => $row['image'],
			);
// print_r($data); die;
			// ======== notification counter ===========
			$counter_query = "select count(*) as notification_counter FROM `notifications` WHERE emp_code = '".$row['emp_code']."' and view_status != 'read' ";
			$counter_query = $mysqli->query($counter_query);
			if( $counter_query->num_rows > 0 ) {

				$counter_data = $counter_query->fetch_assoc();
				$counter = $counter_data['notification_counter'];
				
			} else {
				$counter = '0';
			}
            
            //========== last 3 image array for Aws rekognition on mobile side =============
            $image_query = "select image from attendancetable where image!='' and emp_code = '".$row['emp_code']."' ORDER BY id DESC LIMIT 3 ";
            $image_result = $mysqli->query($image_query);
            if( $image_result->num_rows > 0 ){
                while( $img_arr = $image_result->fetch_assoc() ){
        			$img_data_array[] = $img_arr;
        		}
            } else {
                $img_data_array = array();
            }
            $data['image_array'] = $img_data_array;
            //==============================================================================
            
            $auto_out_time = "23:59:59";
	    	$current_auto_out_time = date('H:i:s');

			$location_query = "select * from `user` WHERE office_code = '".$row['office_code']."' and role = 'office'";
			$location_result = $mysqli->query($location_query);
			$location_data = $location_result->fetch_assoc();

			$attendence_query = "select * from attendancetable WHERE emp_code = '".$row['emp_code']."' ORDER BY id DESC LIMIT 1";
			$attendence_result = $mysqli->query($attendence_query);
			if( $attendence_result->num_rows > 0 ) {

				$attendence_data = $attendence_result->fetch_assoc();

				$qq = "select * from `attendancetable` where `emp_code`='".$row['emp_code']."'  and date='".$today."' ";
				$re_qq = $mysqli->query($qq);
				if( $re_qq->num_rows > 0 ){
				 $dd_old = $today;
					// echo "aa"; die;
				} else {
			    //  echo "hh"; die;
				//  $dd_old = date('Y-m-d', strtotime($attendence_data['date']));
					$dd_old = date('Y-m-d', strtotime('today - 1 days'));
					
					$holiday_sql = "SELECT * FROM `holiday` WHERE `start_date`<='".$dd_old."' and `end_date`>='".$dd_old."' ";
					$result_holiday = $mysqli->query($holiday_sql);
						
					if( $result_holiday->num_rows > 0 ) 
					{
					$row_holiday = $result_holiday->fetch_assoc();
					$holidays = $row_holiday['start_date'];
									
					} 
					else 
					{
						$holidays="";
					}
						
										
				$day = date('l', strtotime($dd_old));
				
				if($day != "Sunday" && $day!="Saturday" && $dd_old != $holidays)
					{
					//   	echo "qq"; die;
			//	   $dd= date('Y-m-d', strtotime($attendence_data['date']));
				   
				   $at_11 = "select * from `attendance` where `emp_code`='".$row['emp_code']."' ORDER BY `id` DESC ";				
					$result_at11 = $mysqli->query($at_11);
						
							while($rowUser11 = $result_at11->fetch_assoc())
								{
																							
									if($rowUser11['in_time']=='00:00:00'){  
										if($rowUser11['halfs']!='Leave'){
											
											if($rowUser11['halfs']!='Sunday'){
											if($rowUser11['halfs']!='Week off'){
											if($rowUser11['halfs']!='Absent'){
											
											  $dd = date('Y-m-d', strtotime($rowUser11['date']));
											break;
											}
											
										  }
									
										}
										}else{	
										
											continue;
										}
									}
									else{
									//	$dd=$dd_old;
										// echo "dd"; die;
										$dd = $rowUser11['date'];

										// $dd = date('Y-m-d', strtotime($attendence_data['date']));
										break;
									}
									
							
									 									
								}
				   
				   				
					} 
				else if($dd_old == $holidays)
					{
									
					$at_11 = "select * from `attendance` where `emp_code`='".$row['emp_code']."' ORDER BY `id` DESC ";				
					$result_at11 = $mysqli->query($at_11);
						
							while($rowUser11 = $result_at11->fetch_assoc())
								{
									if($rowUser11['in_time']=='00:00:00'){
										if($rowUser11['halfs']=='Absent'){
											$dd = date('Y-m-d', strtotime($rowUser11['date']));
											break;
										}else{	
											continue;
										}
									}else{
										$dd = date('Y-m-d', strtotime($rowUser11['date']));
										break;
									}
									 									
								}										
									
					}
				else if($day == "Sunday")
					{
						
						//	echo "yy"; die;
						
						$at = "select * from `attendance` where `emp_code`='".$row['emp_code']."' ORDER BY `id` DESC ";					
						$result_at = $mysqli->query($at);
						
							while($rowUser22 = $result_at->fetch_assoc())
								{
									if($rowUser22['in_time']=='00:00:00'){
										if($rowUser22['halfs']=='Absent'){
											$dd = date('Y-m-d', strtotime($rowUser22['date']));
											break;
										}else{	
											continue;
										}
									}else{
										$dd = date('Y-m-d', strtotime($rowUser22['date']));
										break;
									}
									 
									
								}
							
							// echo $$dd;	die;
								
									
					}
				else if($day == "Saturday")
					{
						
						//	echo "zz"; die;
													
					$at33 = "select * from `attendance` where `emp_code`='".$row['emp_code']."' ORDER BY `id` DESC ";				
					$result_at33 = $mysqli->query($at33);
						
							while($rowUser33 = $result_at33->fetch_assoc())
								{
									if($rowUser33['in_time']=='00:00:00'){
										if($rowUser33['halfs']=='Absent'){
											$dd = date('Y-m-d', strtotime($rowUser33['date']));
											break;
										}else{	
											continue;
										}
									}else{
										$dd = date('Y-m-d', strtotime($rowUser33['date']));
										break;
									}
									 									
								}					
									
					}
					
				}
								 
				 $att_query = "select * from attendancetable where `emp_code`='".$row['emp_code']."'  and `date`='".$dd_old."' order by id desc limit 1 ";  
				$att_result = $mysqli->query($att_query);
				$att_row = $att_result->fetch_assoc();  
				
				if($att_row['time']!='00:00:00')
				{
								
							//	echo "d"; die;
				if( $att_result->num_rows > 0 ){ 
				
				/*
					if($att_row['time']=='00:00:00' && $att_row['place']!='Sunday')
						{ echo "er"; die;									
					 if($att_row['time']=='00:00:00' && $att_row['place']!='Week off')
					{
					if($att_row['time']=='00:00:00' && $att_row['place']!='Holiday')
						{
					if($att_row['time']=='00:00:00' && $att_row['status']!='Leave')
						{
				*/
				
					$place = strtolower($att_row['place']);
					$status =  $att_row['status'];
					$date2 =  date("Y-m-d", strtotime($att_row['date']));
					$date =  date("Y-m-d", strtotime($att_row['date']));
					$time =  date("H:i:s", strtotime($att_row['time']));
					$dd_old = date("Y-m-d", strtotime($att_row['date']));
					
							/*		}
								  }
								 }
								}
								
								*/
					
				}else {
					$dd_old = $dd_old;
					$place = strtolower('No place');
					$status = strtolower('No status');
					$date = '0000-00-00';
					$date2 = '0000-00-00';
					$time = '00:00:00';
					$last_attendance_status = '';
					$can_mark_attendance = 'yes';
				}
				
				
			} else {
				
				$att_query = "select * from attendancetable where `emp_code`='".$row['emp_code']."' order by id desc ";  
				$att_result = $mysqli->query($att_query);
				// $att_row = $att_result->fetch_assoc(); 
				
				while($att_row = $att_result->fetch_assoc())
								{
									
						if($att_row['time']=='00:00:00') {
							 if($att_row['place']!='Leave'){
									if($att_row['place']!='Sunday'){
									if($att_row['place']!='Week off'){
									if($att_row['place']!='Absent'){				
										//	$dd = date('Y-m-d', strtotime($att_row['date']));
										//	echo "dsdssdsds";
										//	echo $att_row['date'];
											$place = strtolower($att_row['place']);
											$status =  $att_row['status'];
											$date2 =  date("Y-m-d", strtotime($att_row['date']));
											$date =  date("Y-m-d", strtotime($att_row['date']));
											$time =  date("H:i:s", strtotime($att_row['time']));
											
											$dd_old = date("Y-m-d", strtotime($att_row['date']));
											
									break;
										 }
										 
										 else
											{
											$place = strtolower($att_row['place']);
											$status =  $att_row['status'];
											$date2 =  date("Y-m-d", strtotime($att_row['date']));
											$date =  date("Y-m-d", strtotime($att_row['date']));
											$time =  date("H:i:s", strtotime($att_row['time']));
											$dd_old = date('Y-m-d', strtotime($att_row['date']));
											break;
											}
										 
										 
										 
										 
										}
									 }							 
									}
								 }else{
									if($att_row['place']!='Leave'){
									if($att_row['place']!='Sunday'){
									if($att_row['place']!='Week off'){
									if($att_row['place']!='Absent'){				
										//	$dd = date('Y-m-d', strtotime($att_row['date']));
										//	echo "dsdssdsds";
										//	echo $att_row['date'];
											$place = strtolower($att_row['place']);
											$status =  $att_row['status'];
											$date2 =  date("Y-m-d", strtotime($att_row['date']));
											$date =  date("Y-m-d", strtotime($att_row['date']));
											$time =  date("H:i:s", strtotime($att_row['time']));
											
											$dd_old = date("Y-m-d", strtotime($att_row['date']));
											
									break;
										 }
										}
									 }							 
									}

									 
									// continue;
								}
										
						}
				
			}
				
							
		date_default_timezone_set("Asia/kolkata");

			$attendance_query = "select * from `attendance` WHERE emp_code = '".$row['emp_code']."' and `date`='".$dd_old."' order by id desc limit 1";
			$attendance_result = $mysqli->query($attendance_query);
			
			
			$autofix_query = "select * from `auto_out_fix_entries` WHERE emp_code = '".$row['emp_code']."' and `for_date`='".$dd_old."' order by id desc limit 1";
		
			$autofix_result = $mysqli->query($autofix_query);
			$autofix_data = $autofix_result->fetch_assoc();
			
				if( $autofix_result->num_rows > 0 ){
					
					$newdata_autofix = 1;
				} else {
					$newdata_autofix = 0;
				}
				
			
			$absentfix_query = "select * from `absent_fix_entries` WHERE emp_code = '".$row['emp_code']."' and `for_date`='".$dd_old."' order by id desc limit 1";
		
			$absentfix_result = $mysqli->query($absentfix_query);
			$absentfix_data = $absentfix_result->fetch_assoc();
			
				if( $absentfix_result->num_rows > 0 ){
					
					$newdata_absentfix = 1;
				} else {
					$newdata_absentfix = 0;
				}	
				
			
			if( $attendance_result->num_rows > 0 ){
												
				$attendance_data = $attendance_result->fetch_assoc();
				 $dates = $attendance_data['date'];				
				 $in_time = $attendance_data['in_time'];
				 $out_time = $attendance_data['out_time'];
				 $halfs = strtolower($attendance_data['halfs']);
				// echo $last_attendance_status1 =  $attendance_data['status'];
									
				
			if($in_time != "00:00:00" && $out_time == "23:59:59" && $halfs =="auto out")
			{					

	 				
			    if($newdata_autofix == 1)
					{
						$can_mark_attendance = "yes";
					} else {
						$can_mark_attendance = "no";
					}
					
					
				//	$can_mark_attendance = "no";				 
					$last_attendance_status ="Auto Out";				
								
				
			}
			elseif($in_time == "00:00:00" && $out_time == "00:00:00" && $halfs=="absent") {
					
				$att_query_ats = "select * from attendancetable where `emp_code`='".$row['emp_code']."' and date = '".$dates."' order by id desc ";  
				$att_result_ats = $mysqli->query($att_query_ats); 
				
				$att_row22 = $att_result_ats->fetch_assoc();
				$place = strtolower($att_row22['place']);
				$status =  $att_row22['status'];
				$date2 =  date("Y-m-d", strtotime($att_row22['date']));
				$date =  date("Y-m-d", strtotime($att_row22['date']));
				$time =  date("H:i:s", strtotime($att_row22['time']));	
				
				/*  if(strtotime($current_auto_out_time) <= strtotime($auto_out_time))
				{				
					$can_mark_attendance = "no";				 
					$last_attendance_status ="Absent";				
				} else {				
					 $can_mark_attendance = "no";
					 $last_attendance_status = $status;				
				}		
				
				*/
				
				if($newdata_absentfix == 1)
					{
						$can_mark_attendance = "yes";
					} else {
						$can_mark_attendance = "no";
					}
					
				
				 // $can_mark_attendance = "no";
				 $last_attendance_status = "Absent";	
				
				
			} else {
					
				// echo "cc"; die;
				 $can_mark_attendance = "yes";
				 $last_attendance_status = $status;
			  }
								
			}
											
			} else {
				$place = '';
				$status = '';
				$date = '';
				$date2 = '';
				$time = '';
				$last_attendance_status = '';
				$can_mark_attendance = 'yes';
			}
			
// echo  $date; die;
			$data['imei'] = $row['imei'];
			$data['uuid'] = $row['imei'];
			$data['last_attendance_place'] = $place;
			$data['last_attendance_status'] = $last_attendance_status;
			$data['last_attendance_date'] = $date;
			$data['last_attendance_date_2'] = $date2;
			$data['last_attendance_time'] = $time;
			$data['latitude'] = $location_data['latitude'] != '' ? $location_data['latitude'] : '';
			$data['longitude'] = $location_data['longitude'] != '' ? $location_data['longitude'] : '';
			$data['notification_counter'] = $counter;
			$data['current_date'] = date('Y-m-d');
			$data['current_time'] = date('H:i:s');
			$data['can_mark_attendance'] = $can_mark_attendance;
			$data['task_check'] = task_check($row['emp_code'], $today);
			$data['mumbai_latitude'] = '19.117528';
			$data['mumbai_longitude'] = '72.8714693';
			$data['workshop_latitude'] = '28.609625';//28.6087885 -- new workshop ,  - 
			$data['workshop_longitude'] = '77.032494';//77.043985

			//============ master log==================
			update_txn($txn['txn_id'], $txn['type'], $row['emp_code'], $row['role'], $device);
			
		$update_sql = "update `user` set can_mark_attendance ='".$can_mark_attendance."' where `emp_code`='".$row['emp_code']."' ";	
		$update_check_sql = $mysqli->query($update_sql);
			//=========================================
			$data['token'] = $token; 
			echo json_encode(array('status'=>'1', 'data'=>$data, 'message'=>'Login Successfully!'));

		} else {
			echo  json_encode(array('status'=>'0', 'message'=>'Not Approved'));
		}

	} else {
		echo  json_encode(array('status'=>'0', 'message'=>'Not Approved'));
	}

} else {
	echo  json_encode(array('status'=>'0', 'message'=>'Invalid Combination of UserId and Password.'));
}
?>