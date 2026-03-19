<?php

include('../config/s3_config.php');
include('../config/config.php');
include('../config/functions.php');
date_default_timezone_set('Asia/Kolkata');

$get_data = json_decode($_POST['askinout']);
$uniquecode = blank($get_data->uniquecode);
$office_code = blank($get_data->office_code, 'Office Code');
$status = blank(strtoupper($get_data->status), 'Status');
$lat = blank($get_data->lat, 'Latitude');
$lon = blank($get_data->lon, 'Longitude');
$msg = escape($get_data->msg);
$devia = $get_data->dev;
$place = blank($get_data->place, 'Place');
$device = blank($get_data->device);
$token = blank($get_data->token, 'Auth');
$actualpath = '';
$image_data = json_encode(array());

//======= Authinticate first =========
	authenticate($uniquecode, $token);
//====================================

$user_check = user($uniquecode);
if( $user_check->num_rows > 0 ){

	$user_data = $user_check->fetch_assoc();
	/*------------------------------------------------------*/
	if( $place != 'Week off' ) {

		//============= Check For week off mark =================
		$week_off = $mysqli->query("select * from attendance where emp_code='$uniquecode' AND date='$today' AND halfs = 'Week off' order by id DESC LIMIT 1");
		if($week_off->num_rows == 0) {

			$leave_check = check_leave($uniquecode, $today);
			if( $leave_check->num_rows == 0 ){

				$result = $mysqli->query("select * from attendancetable where emp_code = '$uniquecode' AND date='$today' order by id DESC LIMIT 1");
				if($result->num_rows > 0) {

					$row = $result->fetch_assoc();
					$datebyweb_2 = $row["date"];
					$status_2 = $row["status"];
					$timebyweb_2 = $row["time"];

					if(isset($_FILES["image"]) && !empty($_FILES["image"]) ) {
						$tmp_name = $_FILES["image"]["tmp_name"];
						$new_name = $uniquecode."_".$datebyweb."_".$timebyweb.".png";
						//move_uploaded_file($tmp_name, '../data/attendance/'.$new_name);
						
						$s3file = "app-data/att-data/".$new_name;
						s3_upload($tmp_name, $s3file); 
						$actualpath = 'http://'.$bucket.'.s3.amazonaws.com/'.$s3file;
						$image_data = image_data($_FILES["image"]);
						
						//============================ face detect code data 20-08-2019 ========================
						$check_img_data = json_decode($image_data);
						if( isset($check_img_data[0]->Confidence) && ($check_img_data[0]->Confidence > 80) ){}else{
						    exception($uniquecode, basename($_SERVER['PHP_SELF']), $_POST['askinout'], 'Face not detected! Try again.', $user_data['role'], $device);
						    echo json_encode(array('status'=>'0', 'message'=>'Face not detected! Try again.'));
						    exit();
						}
					}

					if( $datebyweb != $datebyweb_2 ) {

						$insert_user_attendance = array(
							'emp_code' => $uniquecode,
							'company_code' => $office_code,
							'role' => $user_data['role'],
							'date' => $datebyweb,
							'in_time' => $timebyweb,
							'msg' => $msg, 
							'manager_code' => $user_data['manager_code'],
							'manager_name' => $user_data['manager_name'],
							'admin_code' => $user_data['admin_code'],
							'admin_name' => $user_data['admin_name'],
						);
						$user_attendance = insert($insert_user_attendance, 'attendance');

					}

					if($status == "IN" && $row["status"] == "IN"){
						exception($uniquecode, basename($_SERVER['PHP_SELF']), $_POST['askinout'], 'You are already In.', $user_data['role'], $device);
						echo json_encode(array('status'=>'3', 'message'=>'You are already In.'));
						exit();
					} else if($status == "OUT" && $row["status"] == "OUT"){
						exception($uniquecode, basename($_SERVER['PHP_SELF']), $_POST['askinout'], 'You are already Out.', $user_data['role'], $device);
						echo json_encode(array('status'=>'4', 'message'=>'You are already Out.'));
						exit();
					} else if($status == "OUT" && $row["status"] == "IN" && $place != $row["place"]){
						exception($uniquecode, basename($_SERVER['PHP_SELF']), $_POST['askinout'], 'Please mark your OUT attendance from the same option ONLY', $user_data['role'], $device);
						echo json_encode(array('status'=>'6', 'message'=>'Please mark your OUT attendance from the same option ONLY'));
						exit();
					} else {

						$txn = txn_id("ATT");

						if( $status == "IN" ){
							//---------- movrment entry ---------------
							if( $place == 'Other' || $place == 'Client visit' ){
								movement_entry($uniquecode, $user_data['name'], $place, $timebyweb, '', '', $msg, $lat, $lon, 'insert', $today);
							}
							//-----------------------------------------
						}

						if ($status == "OUT" && $status_2 == "IN") {

			    			$a1 = gmdate("H:i:s" , strtotime($timebyweb)-strtotime($timebyweb_2));	    
			    			$kl = $mysqli->query("select * from attendance where emp_code = '$uniquecode' AND date='$today' order by id DESC LIMIT 1") or die($mysqli->error);
							$row2 = $kl->fetch_assoc();
							if( $row2["halfs"] != "Week off" ){

								if($row2["working_hours"]==""){
									$th = $a1;
								} else {
			     					//$th = gmdate("H:i:s" , strtotime($row2["working_hours"]) + strtotime($a1));
			     					$arra = array($row2["working_hours"], $a1);
									$i = 0;
									foreach ($arra as $time) {
										sscanf($time, '%d:%d:%d', $hour, $min,$sec);
										$i += ($hour * 60 + $min)*60+$sec;
									}
									if ($h = floor($i / 3600)) {
										$i %= 3600;
										if ($m = floor($i / 60)) {
											$i %= 60;
										}
									}
									$l = mktime($h, $m, $i);
									$th = date("H:i:s" , $l);
								}

								$tch = $mysqli->query("update attendance set out_time = '$timebyweb',working_hours = '$th' where emp_code = '$uniquecode' AND date = '$datebyweb'") or die($mysqli->error);
								if( strtotime($th)<strtotime("03:00:00") ){
									$q1 = $mysqli->query("update attendance set halfs = 'Absent' where emp_code = '$uniquecode' AND date = '$datebyweb'") or die($mysqli->error);
								} else if( strtotime($th)>=strtotime("03:00:00") && strtotime($th)<=strtotime("08:45:00") ){
									$q1 = $mysqli->query("update attendance set halfs = 'Half day' where emp_code = '$uniquecode' AND date = '$datebyweb'") or die($mysqli->error);
								} else if( strtotime($th)>=strtotime("08:45:00") ){
									$q1 = $mysqli->query("update attendance set halfs = 'Full day' where emp_code = '$uniquecode' AND date = '$datebyweb'") or die($mysqli->error);
								}

								//---------- movrment entry ---------------
								if( $place == 'Other' || $place == 'Client visit' ){
									movement_entry($uniquecode, $user_data['name'], $place, '', $timebyweb, $a1, $msg, $lat, $lon, 'update', $today);
								}
								//-----------------------------------------
			    			}
						}

						$attendancetable_insert = array(
							'date' => $datebyweb,
							'time' => $timebyweb,
							'status' => $status,
							'image' => $actualpath,
							'lat' => $lat,
							'lon' => $lon,
							'emp_code' => $uniquecode,
							'company_code' => $office_code,
							'role' => $user_data['role'],
							'place' => $place,
							'msg' => $msg,
							'deviation' => $devia,
							'master_txn' => $txn['txn_id'],
							'image_data' => $image_data
						);
						$attendancetable = insert($attendancetable_insert, 'attendancetable');
						send_att_mail($uniquecode);//mail

						if( $attendancetable > 0 ){
							$attendance_data = fetch_attendance($uniquecode);	
							if( $attendance_data->num_rows > 0 ){

								$data = $attendance_data->fetch_assoc();
								$data['time'] = date('H:i', strtotime($data['time']));
								//================ Master txn ==================
							    update_txn($txn['txn_id'], $txn['type'], $uniquecode, $user_data['role'], $device, $status);
								//==============================================

								echo json_encode(array('status'=>'1', 'data'=>$data, 'message'=>'Attendance marked successfully'));
								exit();
								
							} else {
								exception($uniquecode, basename($_SERVER['PHP_SELF']), $_POST['askinout'], 'Failed to fetch fresh attendance details.', $user_data['role'], $device);
								echo json_encode(array('status'=>'0', 'message'=>'Failed to fetch fresh attendance details.'));
								exit();
							}
						} else {
							exception($uniquecode, basename($_SERVER['PHP_SELF']), $_POST['askinout'], 'Failed to mark your attendance.', $user_data['role'], $device);
							echo json_encode(array('status'=>'0', 'message'=>'Failed to mark your attendance.'));
						}

					}		

				} else {
				    
				    //=== Only 3 Late is allowed date-31-07-2018 ==================
                // 	$late_sql = "select * from `attendancetable` where `emp_code`='".$uniquecode."' and `date` like '".date('Y-m')."%' and `msg`!='' and place!='Week off' and msg!='Auto out' and status='IN' group by `date` ";
                // 	$result_late_sql = $mysqli->query($late_sql);
                // 	if( $result_late_sql->num_rows > 3 ){
                // 	    echo json_encode(array('status'=>'0', 'data'=>'', 'message'=>'Limit exhausted for Late Attendance, Contact Manager/HR.'));
                // 		exit();
                // 	}

				    $txn = txn_id("ATT");
                    
					//======= Check for image ==========
					if(isset($_FILES["image"]) && !empty($_FILES["image"]) ) {
						$tmp_name = $_FILES["image"]["tmp_name"];
						$new_name = $uniquecode."_".$datebyweb."_".$timebyweb.".png";
						//move_uploaded_file($tmp_name, "../data/attendance/".$new_name);

						$s3file = "app-data/att-data/".$new_name;
						s3_upload($tmp_name, $s3file);
						$actualpath = 'http://'.$bucket.'.s3.amazonaws.com/'.$s3file;
						$image_data = image_data($_FILES["image"]);
						
						//============================ face detect code data 20-08-2019 ========================
						$check_img_data = json_decode($image_data);
						if( isset($check_img_data[0]->Confidence) && ($check_img_data[0]->Confidence > 80) ){}else{
						    exception($uniquecode, basename($_SERVER['PHP_SELF']), $_POST['askinout'], 'Face not detected! Try again.', $user_data['role'], $device);
						    echo json_encode(array('status'=>'0', 'message'=>'Face not detected! Try again.'));
						    exit();
						}
					}

					$insert_attendance = array(
						'emp_code' => $uniquecode,
						'company_code' => $office_code,
						'role' => $user_data['role'],
						'date' => $datebyweb,
						'msg' => $msg,
						'in_time' => $timebyweb,
						'manager_code' => $user_data['manager_code'],
						'manager_name' => $user_data['manager_name'],
						'admin_code' => $user_data['admin_code'],
						'admin_name' => $user_data['admin_name'],
					);
					$attendance = insert($insert_attendance, 'attendance');
					//============== query exception ================
					if( !$attendance ){
						query_exception($uniquecode, basename($_SERVER['PHP_SELF']), $insert_attendance, $_POST['askinout'], 'Attendance 1 user insert failed', $user_data['role'], $device, $txn['txn_id']);
					}
					//===============================================

					$attendancetable_insert = array(
						'date' => $datebyweb,
						'time' => $timebyweb,
						'status' => $status,
						'image' => $actualpath,
						'lat' => $lat,
						'lon' => $lon,
						'emp_code' => $uniquecode,
						'company_code' => $office_code,
						'role' => $user_data['role'],
						'place' => $place,
						'msg' => $msg,
						'deviation' => $devia,
						'master_txn' => $txn['txn_id'],
						'image_data' => $image_data
					);
					$attendancetable = insert($attendancetable_insert, 'attendancetable');
					//============== query exception ================
					if( !$attendancetable ){
						query_exception($uniquecode, basename($_SERVER['PHP_SELF']), $attendancetable_insert, $_POST['askinout'], 'Attendance 2 attendancetable insert failed', $user_data['role'], $device, $txn['txn_id']);
					}
					//===============================================
					send_att_mail($uniquecode);//mail

					if( $attendance > 0 && $attendancetable > 0 ) {

						$attendance_data = fetch_attendance($uniquecode);	
						if( $attendance_data->num_rows > 0 ){

							$row = $attendance_data->fetch_assoc();
							$row['time'] = date('H:i', strtotime($row['time']));
							//================ Master txn ==================
						    update_txn($txn['txn_id'], $txn['type'], $uniquecode, $user_data['role'], $device, $status);
							//==============================================

						    //---------- movrment entry ---------------
							if( $place == 'Other' || $place == 'Client visit' ){
								movement_entry($uniquecode, $user_data['name'], $place, $timebyweb, '', '', $msg, $lat, $lon, 'insert', $today);
							}
							//-----------------------------------------

							echo json_encode(array('status'=>'1', 'data'=>$row, 'message'=>'Attendance marked successfully')); 
							exit();
							
						} else {
							exception($uniquecode, basename($_SERVER['PHP_SELF']), $_POST['askinout'], 'Failed to fetch fresh attendance details.', $user_data['role'], $device);
							echo json_encode(array('status'=>'0', 'message'=>'Failed to fetch fresh attendance details.'));
							exit();
						}

					} else {
						exception($uniquecode, basename($_SERVER['PHP_SELF']), $_POST['askinout'], 'Attendance mark failed Try again.', $user_data['role'], $device);
						echo json_encode(array('status'=>'0', 'message'=>'Attendance mark failed Try again.'));
						exit();
					}
				}

			} else {
				exception($uniquecode, basename($_SERVER['PHP_SELF']), $_POST['askinout'], 'Cannot mark attendance leave already applied for the day.', $user_data['role'], $device);
				echo json_encode(array('status'=>'0', 'message'=>'Cannot mark attendance leave already applied for the day.'));
				exit();
			}	

		} else {
			exception($uniquecode, basename($_SERVER['PHP_SELF']), $_POST['askinout'], 'Week off already marked for today', $user_data['role'], $device);
			echo json_encode(array('status'=>'5' ,'message'=>'Week off already marked for today'));
			exit();
		} 

	} else {

		//==================== Check Today Entry =====================
		$check = $mysqli->query("SELECT * from attendance where date_of_in = '$datebyweb' and emp_code = '$uniquecode'");
		if( $check->num_rows == 0 ) {

			$txn = txn_id("ATT");
		    
			$insert_attendance = array(
				'emp_code' => $uniquecode,
				'company_code' => $office_code,
				'role' => $user_data['role'],
				'date' => $datebyweb,
				'halfs' => 'Week off',
				'msg' => $msg,
				'in_time' => $timebyweb,
				'out_time' => $timebyweb,
				'working_hours' => '00:00:00',
				'manager_name' => $user_data['manager_name'],
				'manager_code' => $user_data['manager_code'],
				'admin_code' => $user_data['admin_code'],
				'admin_name' => $user_data['admin_name'],
			);
			$attendance = insert($insert_attendance, 'attendance');
			//============== query exception ================
			if( !$attendance ){
				query_exception($uniquecode, basename($_SERVER['PHP_SELF']), $insert_attendance, $_POST['askinout'], 'Attendance Week off user insert failed', $user_data['role'], $device, $txn['txn_id']);
			}
			//===============================================

			$insert_attendance_table = array(
				'date' => $datebyweb,
				'time' => $timebyweb,
				'emp_code' => $uniquecode,
				'company_code' => $office_code,
				'role' => $user_data['role'],
				'place' => $place,
				'msg' => $msg,
				'status' => 'OUT',
				'master_txn' => $txn['txn_id'],
			);
			$attendancetable = insert($insert_attendance_table, 'attendancetable');
			//============== query exception ================
			if( !$attendancetable ){
				query_exception($uniquecode, basename($_SERVER['PHP_SELF']), $insert_attendance_table, $_POST['askinout'], 'Attendance Week off attendancetable insert failed', $user_data['role'], $device, $txn['txn_id']);
			}
			//===============================================

			if( $attendance > 0 && $attendancetable > 0 ){
				$attendance_data = fetch_attendance($uniquecode);	
				if( $attendance_data->num_rows > 0 ){

					$row = $attendance_data->fetch_assoc();
					$row['time'] = date('H:i', strtotime($row['time']));
					//================ Master txn ==================
					update_txn($txn['txn_id'], $txn['type'], $uniquecode, $user_data['role'], $device, 'Week off');
					//==============================================
					
					echo json_encode(array('status'=>'1', 'data'=>$row, 'message'=>'')); 
					exit();
					
				} else {
					exception($uniquecode, basename($_SERVER['PHP_SELF']), $_POST['askinout'], 'Failed to fetch fresh attendance details.', $user_data['role'], $device);
					echo json_encode(array('status'=>'0', 'message'=>'Failed to fetch fresh attendance details.'));
					exit();
				}

			} else {
				exception($uniquecode, basename($_SERVER['PHP_SELF']), $_POST['askinout'], 'Attendance mark failed Try again.', $user_data['role'], $device);
				echo json_encode(array('status'=>'0', 'message'=>'Attendance mark failed Try again.'));
				exit();
			}	

		} else {
			exception($uniquecode, basename($_SERVER['PHP_SELF']), $_POST['askinout'], 'You Cannot Mark Week Off Today', $user_data['role'], $device);
			echo json_encode(array('status'=>'0', 'message'=>'You Cannot Mark Week Off Today'));
			exit();
		}
	}

} else {
	exception($uniquecode, basename($_SERVER['PHP_SELF']), $_POST['askinout'], 'Invalid User', $user_data['role'], $device);
	echo json_encode(array('status'=>'0', 'message'=>'Invalid User'));
	exit();
}

function check_leave($uniquecode, $date){
	global $mysqli;
	$sql = "select * from `attendancetable` where `place`='Leave' and `date`='".$date."' and `emp_code`='".$uniquecode."' ";
	$result = $mysqli->query($sql);
	return $result;
}

function fetch_attendance($uniquecode){
	global $mysqli;
	//global $today;
	$sql = "select * from attendancetable where emp_code = '".$uniquecode."'  order by id DESC LIMIT 1";
	$result = $mysqli->query($sql);
	return $result;
}
