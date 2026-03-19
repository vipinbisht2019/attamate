<?php
include('../../config/config.php');
include('../../config/functions.php');

$sender = $mysqli->real_escape_string($_GET['from']);
$s = $_SERVER["REQUEST_URI"];

$q = "insert into sms (`sender`,`message`) values ('".$sender."','".$s."')";
$res = $mysqli->query($q);

//============= Send Reply ==========
function send_reply($number, $reply){
	$reply = urlencode($reply);
	$uri = "http://smslogin.oxyzen.in/sms/api/http/send.php?api_key=186560BD8C237053787&numbers=$number&message=$reply&senderid=A-Kindle";
	$send = file_get_contents($uri);
}

if( !empty($sender) ){

	list($from, $message2) = explode('&', $s);
	$message = str_replace('message=','',$message2);
	$message = str_replace('%5Cn','',$message);
	$message = str_replace('%20',' ',$message);
	$message = str_replace('%5Cr','',$message);
	$message = str_replace('+',' ',$message);
	$message = trim($message);
	//$message = strtoupper($message);

	$results = explode(' ', $message, 3);
	// $total_g = count($results);
	$key1 = $results[0];
	$key2 = $results[1];
	$results[2] = str_replace(' ', '+', $results[2]);
	$key3 = decrypt(urldecode($results[2]), $key);
	$data = explode('-', $key3);

	if( $key1 == 'AMZKDL'  && $key2 == 'ATT' ){
		$uniquecode = $data[0];
		$status = strtoupper($data[1]);
		$mode = $data[2];
		$lat = $data[3];
		$lon = $data[4];
		$msg = 'Offline Attendance';
		$json = json_encode(array('uniquecode'=>$uniquecode, 'status'=>$status, 'mode'=>$mode, 'lat'=>$lat, 'lon'=>$lon));
		$device = 'Offline';

		//========== kre check =============
		$kre = kre($uniquecode);
		if( $kre->num_rows > 0 ){

			$kre_data = $kre->fetch_assoc();
			$retailer_code = $kre_data["retailer_code"];
			$email = $kre_data['username'];

			$retailer = retailer($retailer_code);
			$retailer_data = $retailer->fetch_assoc();
			$latitude = $retailer_data['latitude'];
			$longitude = $retailer_data['longitude'];

			function check_leave($uniquecode, $date){
				global $mysqli;
				$sql = "select * from `attendancetable` where `place`='Leave' and `datebyweb`='".$date."' and `uniquecode`='".$uniquecode."' ";
				$result = $mysqli->query($sql);
				return $result;
			}

			function fetch_attendance($uniquecode){
				global $mysqli;
				$sql = "select * from attendancetable where uniquecode = '".$uniquecode."' order by id DESC LIMIT 1";
				$result = $mysqli->query($sql);
				return $result;
			}

			//=========== calculate distance ============
			$distance = intval(distance($latitude, $longitude, $lat, $lon, "K"));
			if( $mode == 'At store' && $distance >= '1' ){
				exception($uniquecode, basename($_SERVER['PHP_SELF']), $json, 'You are not at store', 'kre', $device);
				send_reply($sender, 'You are not at store');
				exit;
			}

			if( $mode != 'Week off' ) {

				//============= Check For week off mark =================
				$week_off = $mysqli->query("select * from attendance_kre_wise where kre_code='$uniquecode' AND date_of_in='$datebyweb' AND halfs = 'Week off' order by id DESC LIMIT 1");	
				if($week_off->num_rows == 0) {

					$leave_check = check_leave($uniquecode, $today);
					if( $leave_check->num_rows == 0 ){

						$result = $mysqli->query("select * from attendancetable where uniquecode = '$uniquecode' order by id DESC LIMIT 1");
						if($result->num_rows > 0) {

							$row = $result->fetch_assoc();
							$datebyweb_2 = $row["datebyweb"];
							$status_2 = $row["status"];
							$timebyweb_2 = $row["timebyweb"];

							// if(isset($_FILES["image"])) {
							// 	$tmp_name = $_FILES["image"]["tmp_name"];
							// 	$new_name = $uniquecode."_".$datebyweb."_".$timebyweb.".png";
							// 	move_uploaded_file($tmp_name, '../data/attendance/'.$new_name);
							// 	$actualpath = base_url."data/attendance/".$new_name;
							// }

							if( $datebyweb != $datebyweb_2 ) {
								$insert_kre_wise = array(
									'kre_code' => $uniquecode,
									'store_id' => $retailer_code,
									'date_of_in' => $datebyweb,
									'in_time' => $timebyweb,
									'msg' => $msg, 
								);
								$kre_wise = insert($insert_kre_wise, 'attendance_kre_wise');
							}

							if($status == "IN" && $row["status"] == "IN"){
								exception($uniquecode, basename($_SERVER['PHP_SELF']), $json, 'You are already In.', 'kre', $device);
								send_reply($sender, 'You are already In.');
								exit();
							} else if($status == "OUT" && $row["status"] == "OUT"){
								exception($uniquecode, basename($_SERVER['PHP_SELF']), $json, 'You are already Out.', 'kre', $device);
								send_reply($sender, 'You are already Out.');
								exit();
							} else if($status == "OUT" && $row["status"] == "IN" && $place != $row["place"]){
								exception($uniquecode, basename($_SERVER['PHP_SELF']), $json, 'Please mark your OUT attendance from the same option ONLY', 'kre', $device);
								send_reply($sender, 'Please mark your OUT attendance from the same option ONLY');
								exit();
							} else {

								$txn = txn_id("ATT");

								if ($status == "OUT" && $status_2 == "IN") {

					    			$a1 = gmdate("H:i:s" , strtotime($timebyweb)-strtotime($timebyweb_2));	    
					    			$kl = $mysqli->query("select * from attendance_kre_wise where kre_code = '$uniquecode' order by id DESC LIMIT 1") or die($mysqli->error);
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

										$tch = $mysqli->query("update attendance_kre_wise set out_time = '$timebyweb',working_hours = '$th' where kre_code = '$uniquecode' AND date_of_in = '$datebyweb'") or die($mysqli->error);
										if( strtotime($th)<strtotime("02:00:00") ){
											$q1 = $mysqli->query("update attendance_kre_wise set halfs = 'Absent' where kre_code = '$uniquecode' AND date_of_in = '$datebyweb'") or die($mysqli->error);
										} else if( strtotime($th)>=strtotime("02:00:00") && strtotime($th)<=strtotime("07:45:00") ){
											$q1 = $mysqli->query("update attendance_kre_wise set halfs = 'Half day' where kre_code = '$uniquecode' AND date_of_in = '$datebyweb'") or die($mysqli->error);
										} else if( strtotime($th)>=strtotime("07:45:00") ){
											$q1 = $mysqli->query("update attendance_kre_wise set halfs = 'Full day' where kre_code = '$uniquecode' AND date_of_in = '$datebyweb'") or die($mysqli->error);
										}
					    			} 
								}

								$attendancetable_insert = array(
									'email' => $email,
									'datebyweb' => $datebyweb,
									'timebyweb' => $timebyweb,
									'status' => $status,
									'lat' => $lat,
									'lon' => $lon,
									'uniquecode' => $uniquecode,
									'store_id' => $retailer_code,
									'place' => $mode,
									'msg' => $msg,
									'master_txn' => $txn['txn_id'],
								);
								$attendancetable = insert($attendancetable_insert, 'attendancetable');

								if( $attendancetable > 0 ){
									$attendance_data = fetch_attendance($uniquecode);	
									if( $attendance_data->num_rows > 0 ){

										$data = $attendance_data->fetch_assoc();
										//================ Master txn ==================
									    update_txn($txn['txn_id'], $txn['type'], $uniquecode, 'kre', $device, $status);
										//==============================================

									    // if( $status == 'IN' ){
									    // 	$check = $first_in;
									    // 	$check_msg = '';
									    // } else {
									    // 	$check = 2;
									    // 	$check_msg = check_message($uniquecode, $today);
									    // }

										//=== yahan lagana hai check ====
										// $data['check'] = $check;
										// $data['check_msg'] = $check_msg;
										// echo json_encode(array('status'=>'1', 'data'=>$data, 'message'=>'Attendance marked successfully'));
										send_reply($sender, "Attendance ".$status." marked successfully");
										exit();
										
									} else {
										exception($uniquecode, basename($_SERVER['PHP_SELF']), $json, 'Failed to fetch fresh attendance details.', 'kre', $device);
										send_reply($sender, 'Failed to fetch fresh attendance details.');
										exit();
									}
								} else {
									exception($uniquecode, basename($_SERVER['PHP_SELF']), $json, 'Failed to mark your attendance.', 'kre', $device);
									send_reply($sender, 'Failed to mark your attendance.');
								}

							}		

						} else {

						    $txn = txn_id("ATT");

							//======= Check for image ==========
							// if(isset($_FILES["image"])) {
							// 	$tmp_name = $_FILES["image"]["tmp_name"];
							// 	$new_name = $uniquecode."_".$datebyweb."_".$timebyweb.".png";
							// 	move_uploaded_file($tmp_name, "../data/attendance/".$new_name);
							// 	$actualpath = base_url."data/attendance/".$new_name;
							// }

							$insert_kre_wise = array(
								'kre_code' => $uniquecode,
								'store_id' => $retailer_code,
								'date_of_in' => $datebyweb,
								'msg' => $msg,
								'in_time' => $timebyweb,
							);
							$kre_wise = insert($insert_kre_wise, 'attendance_kre_wise');
							//============== query exception ================
							if( !$kre_wise ){
								query_exception($uniquecode, basename($_SERVER['PHP_SELF']), $insert_kre_wise, $json, 'Attendance Week off kre wise insert failed', 'kre', $device, $txn['txn_id']);
							}
							//===============================================

							$insert_attendance_table = array(
								'email' => $email,
								'datebyweb' => $datebyweb,
								'timebyweb' => $timebyweb,
								'uniquecode' => $uniquecode,
								'store_id' => $retailer_code,
								'lat' => $lat,
								'lon' => $lon,
								'place' => $mode,
								'msg' => $msg,
								'status' => 'IN',
								'master_txn' => $txn['txn_id'],
							);
							$attendancetable = insert($insert_attendance_table, 'attendancetable');
							//============== query exception ================
							if( !$attendancetable ){
								query_exception($uniquecode, basename($_SERVER['PHP_SELF']), $insert_attendance_table, $json, 'Attendance Week off attendancetable insert failed', 'kre', $device, $txn['txn_id']);
							}
							//===============================================

							if( $kre_wise > 0 && $attendancetable > 0 ) {

								$attendance_data = fetch_attendance($uniquecode);	
								if( $attendance_data->num_rows > 0 ){

									$row = $attendance_data->fetch_assoc();
									//================ Master txn ==================
								    update_txn($txn['txn_id'], $txn['type'], $uniquecode, 'kre', $device, 'IN');
									//==============================================

									//=== Week off main bhi jarurat ni ====
									// $row['check']='1';
									// $row['check_msg']='';
									// echo json_encode(array('status'=>'1', 'data'=>$row, 'message'=>'Attendance marked successfully'));
									send_reply($sender, "Attendance ".$status." marked successfully");
									exit();
									
								} else {
									exception($uniquecode, basename($_SERVER['PHP_SELF']), $json, 'Failed to fetch fresh attendance details.', 'kre', $device);
									send_reply($sender, 'Failed to fetch fresh attendance details.');
									exit();
								}

							} else {
								exception($uniquecode, basename($_SERVER['PHP_SELF']), $json, 'Attendance mark failed Try again.', 'kre', $device);
								send_reply($sender, 'Attendance mark failed Try again.');
								exit();
							}
						}

					} else {
						exception($uniquecode, basename($_SERVER['PHP_SELF']), $json, 'Cannot mark attendance leave already applied for the day.', 'kre', $device);
						send_reply($sender, 'Cannot mark attendance leave already applied for the day.');
						exit();
					}

				} else {
					exception($uniquecode, basename($_SERVER['PHP_SELF']), $json, 'Week off already marked for today', 'kre', $device);
					send_reply($sender, 'Week off already marked for today');
					exit();
				} 

			} else {

				//==================== Check Today Entry =====================
				$check = $mysqli->query("SELECT * from attendance_kre_wise where date_of_in = '$datebyweb' and kre_code = '$uniquecode'");
				if( $check->num_rows == 0 ) {

					$txn = txn_id("ATT");

					//======= One week one week off =========================
					//removed on 2016-12-27 as discussed with rahul sir ===
					//one_week_one_week_off($uniquecode, $json, $device);
				    
					$insert_kre_wise = array(
						'kre_code' => $uniquecode,
						'store_id' => $retailer_code,
						'date_of_in' => $datebyweb,
						'halfs' => 'Week off',
						'msg' => $msg,
						'in_time' => $timebyweb,
						'out_time' => $timebyweb,
						'working_hours' => '00:00:00',
					);
					$kre_wise = insert($insert_kre_wise, 'attendance_kre_wise');
					//============== query exception ================
					if( !$kre_wise ){
						query_exception($uniquecode, basename($_SERVER['PHP_SELF']), $insert_kre_wise, $json, 'Attendance Week off kre wise insert failed', 'kre', $device, $txn['txn_id']);
					}
					//===============================================

					$insert_attendance_table = array(
						'email' => $email,
						'datebyweb' => $datebyweb,
						'timebyweb' => $timebyweb,
						'uniquecode' => $uniquecode,
						'store_id' => $retailer_code,
						'place' => $mode,
						'msg' => $msg,
						'status' => 'OUT',
						'master_txn' => $txn['txn_id'],
					);
					$attendancetable = insert($insert_attendance_table, 'attendancetable');
					//============== query exception ================
					if( !$attendancetable ){
						query_exception($uniquecode, basename($_SERVER['PHP_SELF']), $insert_attendance_table, $json, 'Attendance Week off attendancetable insert failed', 'kre', $device, $txn['txn_id']);
					}	
					//===============================================

					if( $kre_wise > 0 && $attendancetable > 0 ){
						$attendance_data = fetch_attendance($uniquecode);	
						if( $attendance_data->num_rows > 0 ){

							$row = $attendance_data->fetch_assoc();
							//================ Master txn ==================
							update_txn($txn['txn_id'], $txn['type'], $uniquecode, 'kre', $device, 'Week off');
							//==============================================
							
							//=== Week off main bhi jarurat ni ====
							// $row['check']='0';
							// $row['check_msg']='';
							send_reply($sender, $mode.' marked successfully');
							exit();
							
						} else {
							exception($uniquecode, basename($_SERVER['PHP_SELF']), $json, 'Failed to fetch fresh attendance details.', 'kre', $device);
							send_reply($sender, 'Failed to fetch fresh attendance details.');
							exit();
						}

					} else {
						exception($uniquecode, basename($_SERVER['PHP_SELF']), $json, 'Attendance mark failed Try again.', 'kre', $device);
						send_reply($sender, 'Attendance mark failed Try again.');
						exit();
					}	

				} else {
					exception($uniquecode, basename($_SERVER['PHP_SELF']), $json, 'You Cannot Mark Week Off Today', 'kre', $device);
					send_reply($sender, 'You Cannot Mark Week Off Today');
					exit();
				}
			}

		} else {
			send_reply($sender, 'Invalid User');
		}	

	} else {
		send_reply($sender, "Invalid Message");
	}
}
?>