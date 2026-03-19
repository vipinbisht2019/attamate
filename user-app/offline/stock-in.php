<?php
include('../../config/config.php');
include('../../config/functions.php');
include('../../config/stock_in_functions.php');

echo $sender = $mysqli->real_escape_string($_GET['from']);
echo ":::";
echo $s = $_SERVER["REQUEST_URI"];
echo ":::";

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
	echo $message = trim($message);
	//$message = strtoupper($message);

	$results = explode(' ', $message, 3);
	// $total_g = count($results);
	$key1 = $results[0];
	$key2 = $results[1];
	$results[2] = str_replace(' ', '+', $results[2]);
	$key3 = decrypt(urldecode($results[2]), $key);
	$data = explode('-', $key3);

	if( $key1 == 'AMZKDL'  && $key2 == 'SIN' ){

		$uniquecode = $data[0];
		$fsn = $data[1];
		$msg = $data[2];
		$json = json_encode(array('uniquecode'=>$uniquecode, 'fsn'=>$fsn, 'msg'=>$msg));
		$device = 'Offline';

		//========== 16 Digit check ==============
		if( strlen($fsn)!='16' ){
			send_reply($sender, "Invalid FSN Length");
			exit;
		}

		//========= Kre Check ====================
		$sql = "select * from `user` where `kre_code`='".$uniquecode."' and `status`='Approved' ";
		$result = $mysqli->query($sql);
		if( $result->num_rows > 0 ){

			$txn = txn_id("ADS");

			$fsn_q = fsn_exist($fsn);
			if( $fsn_q->num_rows > 0 ) {

				$fsn_data = $fsn_q->fetch_assoc();
				//----- check for sold out --------
				if( $fsn_data['sale_done'] != "done") {
					//----- check for sellable --------
					if($fsn_data["sellable"] == "yes"){

						//------ check for issued by admin ---------
						if( $fsn_data['admin'] != '' ) {

							//----- check for fsn already issued to any retailer or not-------
							if( $fsn_data['retailer'] != '' ) { // if already issued

								//---- check for kre is mapped to retailer or not ----
								$kre = kre_mapped($uniquecode); 
								if( $kre->num_rows > 0 ) {
									$kre_data = $kre->fetch_assoc();

								} else {
									exception($uniquecode, basename($_SERVER['PHP_SELF']), $json, 'You are not mapped to any retailer.', 'kre', $device);
									send_reply($sender, 'You are not mapped to any retailer.');
									exit;
								}

								//----- retailer detail which is mapped to kre ------
								$retailer = retailer_detail($kre_data["retailer_code"]);
								if( $retailer->num_rows > 0 ) {

									//------ if both retailer is same -----
									$retailer_detail = $retailer->fetch_assoc();
									if( $retailer_detail["retailer_code"] == $fsn_data['retailer'] ){
										exception($uniquecode, basename($_SERVER['PHP_SELF']), $json, 'This FSN is already stocked in.', 'kre', $device);
										send_reply($sender, 'This FSN is already stocked in.');
										exit;
									}

								} else {
									exception($uniquecode, basename($_SERVER['PHP_SELF']), $json, 'You are mapped to unknown retailer.', 'kre', $device);
									send_reply($sender, 'You are mapped to unknown retailer.');
									exit;
								}

								//----- retailer data -----
								$retailer = retailer_data($fsn_data['retailer']);
								if( $retailer->num_rows > 0 ) {
									$retailer_data = $retailer->fetch_assoc();
									
									//----- group code check same or not -----
									if($retailer_detail["group_code"] != $retailer_data["group_code"]){
										exception($uniquecode, basename($_SERVER['PHP_SELF']), $json, 'This FSN is mapped to a different retailer chain.', 'kre', $device);
										send_reply($sender, 'This FSN is mapped to a different retailer chain.');
										exit;
									}

									//------ insert log --------
									$sql_log = $mysqli->prepare("insert into stock_all_log(fsn, model_number, admin, admin_sell_date, nd, nd_location, nd_sell_date, rd, rd_location, rd_sell_date, retailer, retailer_sell_date, kre, kre_sell_date, customer, sellable, sale_done, stock_return, return_dt, return_reason, stock_in_reason, txn_group, am_code, upload_date, beat_plan_txn, tpr_id, admin_username, nd_username, rd_username) select fsn, model_number, admin, admin_sell_date, nd, nd_location, nd_sell_date, rd, rd_location, rd_sell_date, retailer, retailer_sell_date, kre, kre_sell_date, customer, sellable, sale_done, stock_return, return_dt, return_reason, stock_in_reason, txn_group, am_code, upload_date, beat_plan_txn, tpr_id, admin_username, nd_username, rd_username from stock_all where fsn=?");
									$sql_log->bind_param("s",$fsn);
									$sql_log->execute();

									//--------- check for same trade type ---------
									//===== for MT ======
									if( $retailer_data["trade_type"] == "MT" && $retailer_detail["trade_type"] == "MT" ) {

										//----- if both come under same admin -----
										if($retailer_detail["admin_code"] != "" && $retailer_detail["admin_code"] == $fsn_data["admin"]){

											$update_stock_all_array = array(
												"retailer" => $retailer_detail["retailer_code"],
												"kre" => $uniquecode,
												"stock_in_reason" => $msg,
											);
											$stock_all_where = "fsn='".$fsn."'";
											$stock_all = update($update_stock_all_array, 'stock_all', $stock_all_where);

											//------------Query exception--------------
											if( !$stock_all ) {
												query_exception($uniquecode, basename($_SERVER['PHP_SELF']), $update_stock_all_array, $json, 'Stock in - stock all update failed', 'kre', $device, $txn['txn_id']);
											}
											//-----------------------------------------

											$update_stock_admin_array = array(
												"channel_code" => $retailer_detail["retailer_code"],
												"channel_name" => $retailer_detail["retailer_name"],
												"channel_city" => $retailer_detail["city"],
												"channel_state" => $retailer_detail["state"],
												"channel_region" => $retailer_detail["zone"],
												"kre" => $uniquecode,
											);
											$stock_admin_where = "fsn='".$fsn."'";
											$stock_admin = update($update_stock_admin_array, 'stock_admin', $stock_admin_where);

											//------------Query exception--------------
											if( !$stock_admin ) {
												query_exception($uniquecode, basename($_SERVER['PHP_SELF']), $update_stock_admin_array, $json, 'Stock in - stock admin update failed', 'kre', $device, $txn['txn_id']);
											}
											//-----------------------------------------

											$update_stock_retailer_array = array(
												"retailer_id" => $retailer_detail["retailer_code"],
												"receive_date" => $today,
												"kre" => $uniquecode,
												"stock_in_reason" => $msg,
												"stock_in_tran_id" => $txn['txn_id'],
												"offline_stock_in" => "yes",
											);
											$stock_retailer_where = "fsn='".$fsn."'";
											$stock_retailer = update($update_stock_retailer_array, 'stock_retailer', $stock_retailer_where);

											//------------Query exception--------------
											if( !$stock_retailer ) {
												query_exception($uniquecode, basename($_SERVER['PHP_SELF']), $update_stock_retailer_array, $json, 'Stock in - stock retailer update failed', 'kre', $device, $txn['txn_id']);
											}
											//-----------------------------------------

											if( $stock_all && $stock_admin && $stock_retailer ){
												$sql_log->execute();
												$sql_log->close();
												//========= master txn -==================
							                    update_txn($txn['txn_id'], $txn['type'], $uniquecode, 'kre', $device);
												//========================================
												send_reply($sender, 'Stock in successful');
												exit;
											} else {
												exception($uniquecode, basename($_SERVER['PHP_SELF']), $json, 'Stock In failed.', 'kre', $device);
												send_reply($sender, 'Stock In failed.');
												exit;
											}

										} else {
											exception($uniquecode, basename($_SERVER['PHP_SELF']), $json, 'This retailer does not come under Amazon.', 'kre', $device);
											send_reply($sender, 'This retailer does not come under Amazon.');
											exit;
										}
									} else if ($retailer_data["trade_type"] == "LFR" && $retailer_detail["trade_type"] == "LFR") {
										//===== for LFR retailer ========
										if( $fsn_data["nd"] != "" ){ // if nd is present in stock_all
				 
											$nd = nd_check($retailer_detail["nd_code"]);
											if( $nd->num_rows > 0 ) {
												if($retailer_detail["nd_code"] == $fsn_data["nd"]){

													$update_stock_all_array = array(
														"retailer" => $retailer_detail["retailer_code"],
														"kre" => $uniquecode,
														"stock_in_reason" => $msg,
													);
													$stock_all_where = "fsn='".$fsn."'";
													$stock_all = update($update_stock_all_array, 'stock_all', $stock_all_where);

													//------------Query exception--------------
													if( !$stock_all ) {
														query_exception($uniquecode, basename($_SERVER['PHP_SELF']), $update_stock_all_array, $json, 'Stock in - stock all update failed', 'kre', $device, $txn['txn_id']);
													}
													//-----------------------------------------

													$update_stock_nd_array = array(
														"channel_code" => $retailer_detail["retailer_code"],
														"channel_name" => $retailer_detail["retailer_name"],
														"channel_city" => $retailer_detail["city"],
														"channel_state" => $retailer_detail["state"],
														"channel_region" => $retailer_detail["zone"],
														"kre" => $uniquecode,
													);
													$stock_nd_where = "fsn='".$fsn."'";
													$stock_nd = update($update_stock_nd_array, 'stock_nd', $stock_nd_where);

													//------------Query exception--------------
													if( !$stock_nd ) {
														query_exception($uniquecode, basename($_SERVER['PHP_SELF']), $update_stock_nd_array, $json, 'Stock in - stock nd update failed', 'kre', $device, $txn['txn_id']);
													}
													//-----------------------------------------									

													$update_stock_retailer_array = array(
														"retailer_id" => $retailer_detail["retailer_code"],
														"receive_date" => $today,
														"kre" => $uniquecode,
														"stock_in_reason" => $msg,
														"stock_in_tran_id" => $txn['txn_id'],
														"offline_stock_in" => "yes",
													);
													$stock_retailer_where = "fsn='".$fsn."'";
													$stock_retailer = update($update_stock_retailer_array, 'stock_retailer', $stock_retailer_where);

													//------------Query exception--------------
													if( !$stock_retailer ) {
														query_exception($uniquecode, basename($_SERVER['PHP_SELF']), $update_stock_retailer_array, $json, 'Stock in - stock retailer update failed', 'kre', $device, $txn['txn_id']);
													}
													//-----------------------------------------

													if( $stock_all && $stock_nd && $stock_retailer ){
														$sql_log->execute();
														$sql_log->close();
														//========= master txn -==================
														update_txn($txn['txn_id'], $txn['type'], $uniquecode, 'kre', $device);
														//========================================
														send_reply($sender, 'Stock in successful');
														exit;
													} else {
														exception($uniquecode, basename($_SERVER['PHP_SELF']), $json, 'Stock In failed.', 'kre', $device);
														send_reply($sender, 'Stock In failed.');
														exit;
													}

												} else {
													exception($uniquecode, basename($_SERVER['PHP_SELF']), $json, 'This FSN is not alloted to your National Distributor', 'kre', $device);
													send_reply($sender, 'This FSN is not alloted to your National Distributor.');
													exit;
												}
											} else {
												exception($uniquecode, basename($_SERVER['PHP_SELF']), $json, 'Retailer is not mapped to valid National Distributor.', 'kre', $device);
												send_reply($sender, 'Retailer is not mapped to valid National Distributor.');
												exit;
											}

										}

									} else if( $retailer_data["trade_type"] == "GT" ) { // GT k liye
										exception($uniquecode, basename($_SERVER['PHP_SELF']), $json, 'This FSN is already issued to another retailer.', 'kre', $device);
										send_reply($sender, 'This FSN is already issued to another retailer.');
										exit;
									} else {
										exception($uniquecode, basename($_SERVER['PHP_SELF']), $json, 'Stock transfer is allowed in same trade types only.', 'kre', $device);
										send_reply($sender, 'Stock transfer is allowed in same trade types only.');
										exit;
									}

								} else {
									exception($uniquecode, basename($_SERVER['PHP_SELF']), $json, 'Previous retailer not found.', 'kre', $device);
									send_reply($sender, 'Previous retailer not found.');
									exit;
								}

							} else { // fsn kisi retailer pe nhi h, toh ye chelga
								//---- check kre mapped -------
								$kre = kre_mapped($uniquecode); 
								if( $kre->num_rows > 0 ) {
									$kre_data = $kre->fetch_assoc();
									$retailer_code = $kre_data["retailer_code"];

									//----- retailer data - kre mapped to ----
									$retailer = fsn_retailer_data($retailer_code); 
									if( $retailer->num_rows > 0 ) {
										$retailer_data = $retailer->fetch_assoc();
										$trade_type = $retailer_data["trade_type"];

										if( $trade_type == "MT" ) {
											exception($uniquecode, basename($_SERVER['PHP_SELF']), $json, 'Stock in denied, FSN not mapped to MT channel.', 'kre', $device);
											send_reply($sender, 'Stock in denied, FSN not mapped to MT channel.');
											exit;
										} else if( $trade_type == "LFR" ) {

											if(!empty($fsn_data["nd"]) && empty($fsn_data["nd_sell_date"]) && empty($fsn_data["rd"]) && $fsn_data["nd"] == $retailer_data["nd_code"]) {
												// Allow Stock In

												$update_stock_all_array = array(
													"nd_sell_date" => $today,
													"retailer" => $retailer_code,
													"kre" => $uniquecode,
													"stock_in_reason" => $msg,
												);
												$stock_all_where = "fsn='".$fsn."'";
												$stock_all = update($update_stock_all_array, 'stock_all', $stock_all_where);	

												//------------Query exception--------------
												if( !$stock_all ) {
													query_exception($uniquecode, basename($_SERVER['PHP_SELF']), $update_stock_all_array, $json, 'Stock in - stock all update failed', 'kre', $device, $txn['txn_id']);
												}
												//-----------------------------------------

												$update_stock_nd_array = array(
													"channel_code" => $retailer_code,
													"channel_name" => $retailer_data["retailer_name"],
													"channel_city" => $retailer_data["city"],
													"channel_state" => $retailer_data["state"],
													"channel_region" => $retailer_data["zone"],
													"sale_date" => $today,
													"kre" => $uniquecode,
												);
												$stock_nd_where = "fsn='".$fsn."'";
												$stock_nd = update($update_stock_nd_array, 'stock_nd', $stock_nd_where);

												//------------Query exception--------------
												if( !$stock_nd ) {
													query_exception($uniquecode, basename($_SERVER['PHP_SELF']), $update_stock_nd_array, $json, 'Stock in - stock nd update failed', 'kre', $device, $txn['txn_id']);
												}
												//-----------------------------------------

												$insert_stock_retailer_array = array(
													"retailer_id" => $retailer_code,
													"received_from" => $fsn_data["nd"],
													"model_number" => $fsn_data["model_number"],
													"fsn" => $fsn,
													"receive_date" => $today,
													"kre" => $uniquecode,
													"sellable" => "yes",
													"stock_in_reason" => $msg,
													"stock_in_tran_id" => $txn['txn_id'],
													"offline_stock_in" => "yes",
												);
												$stock_retailer = insert($insert_stock_retailer_array, 'stock_retailer');

												//------------Query exception--------------
												if( !$stock_retailer ) {
													query_exception($uniquecode, basename($_SERVER['PHP_SELF']), $insert_stock_retailer_array, $json, 'Stock in - stock retailer update failed', 'kre', $device, $txn['txn_id']);
												}
												//-----------------------------------------

												$all_query = $stock_all && $stock_nd && $stock_retailer;

											} else {
												// Deny Stock In
												exception($uniquecode, basename($_SERVER['PHP_SELF']), $json, 'Stock in denied, FSN not mapped to ND channel.', 'kre', $device);
												send_reply($sender, 'Stock in denied, FSN not mapped to ND channel.');
												exit;
											}
										} else if( $trade_type == 'GT' ) {

											if( !empty($fsn_data["nd"]) && !empty($fsn_data["nd_sell_date"]) && !empty($fsn_data["rd"]) && empty($fsn_data["rd_sell_date"]) && $fsn_data["rd"] == $retailer_data["rd_code"] ) {
												// Allow Stock In --------- This FSN will be received from RD

												$update_stock_all_array = array(
													"rd_sell_date" => $today,
													"retailer" => $retailer_code,
													"kre" => $uniquecode,
													"stock_in_reason" => $msg,
												);
												$stock_all_where = "fsn='".$fsn."'";
												$stock_all = update($update_stock_all_array, 'stock_all', $stock_all_where);

												//------------Query exception--------------
												if( !$stock_all ) {
													query_exception($uniquecode, basename($_SERVER['PHP_SELF']), $update_stock_all_array, $json, 'Stock in - stock all update failed', 'kre', $device, $txn['txn_id']);
												}
												//-----------------------------------------

												$update_stock_rd_array = array(
													"channel_code" => $retailer_code,
													"channel_name" => $retailer_data["retailer_name"],
													"channel_city" => $retailer_data["city"],
													"channel_state" => $retailer_data["state"],
													"channel_region" => $retailer_data["zone"],
													"sale_date" => $today,
													"kre" => $uniquecode,
												);		
												$stock_rd_where = "fsn='".$fsn."'";
												$stock_rd = update($update_stock_rd_array, 'stock_rd', $stock_rd_where);

												//------------Query exception--------------
												if( !$stock_rd ) {
													query_exception($uniquecode, basename($_SERVER['PHP_SELF']), $update_stock_rd_array, $json, 'Stock in - stock rd update failed', 'kre', $device, $txn['txn_id']);
												}
												//-----------------------------------------

												$insert_stock_retailer_array = array(
													"retailer_id" => $retailer_code,
													"received_from" => $fsn_data["rd"],
													"model_number" => $fsn_data["model_number"],
													"fsn" => $fsn,
													"receive_date" => $today,
													"kre" => $uniquecode,
													"sellable" => "yes",
													"stock_in_reason" => $msg,
													"stock_in_tran_id" => $txn['txn_id'],
													"offline_stock_in" => "yes",
												);
												$stock_retailer = insert($insert_stock_retailer_array, 'stock_retailer');

												//------------Query exception--------------
												if( !$stock_retailer ) {
													query_exception($uniquecode, basename($_SERVER['PHP_SELF']), $insert_stock_retailer_array, $json, 'Stock in - stock rd update failed', 'kre', $device, $txn['txn_id']);
												}
												//-----------------------------------------
												$all_query = $stock_all && $stock_rd && $stock_retailer;

											} else if(!empty($fsn_data["nd"]) && empty($fsn_data["nd_sell_date"]) && empty($fsn_data["rd"])){

												// Allow Stock In --------- Here Chain will be repaired

												// UPDATE: New checks for hierarchy mapping added on 2016-12-02
												$sql = "select id from user where role='rd' and rd_code='".$retailer_data["rd_code"]."' and nd_code='".$fsn_data["nd"]."'";
												$result = $mysqli->query($sql);
												if($result->num_rows){
													$stc = '';
													$result_loc = $mysqli->query("select shipped_to_code,shipped_to_location from location_code where role='RD' and entity_code='".$retailer_data["rd_code"]."'");
													if($result_loc->num_rows > 0){
														while($row_loc = $result_loc->fetch_assoc()){
															if($row_loc["shipped_to_location"] == $retailer_data["city"]){
																$stc = $row_loc["shipped_to_code"];
																break;
															}
														}
														if(empty($stc)){
															$stc = $retailer_data["rd_code"]."_UNK";
														}
													} else {
														$stc = $retailer_data["rd_code"]."_UNK";
													}

													$update_stock_all_array = array(
														"nd_sell_date" => $today,
														"rd" => $retailer_data["rd_code"],
														"rd_location" => $stc,
														"rd_sell_date" => $today,
														"retailer" => $retailer_code,
														"kre" => $uniquecode,
														"stock_in_reason" => $msg,
													);
													$stock_all_where = "fsn='".$fsn."'";
													$stock_all = update($update_stock_all_array, 'stock_all', $stock_all_where);

													//------------Query exception--------------
													if( !$stock_all ) {
														query_exception($uniquecode, basename($_SERVER['PHP_SELF']), $update_stock_all_array, $json, 'Stock in - stock all update failed', 'kre', $device, $txn['txn_id']);
													}
													//-----------------------------------------


													$update_stock_nd_array = array(
														"channel_code" => $retailer_data["rd_code"],
														"rd_location" => $stc,
														"sale_date" => $today,
													);
													$stock_nd_where = "fsn='".$fsn."'";
													$stock_nd = update($update_stock_nd_array, 'stock_nd', $stock_nd_where);

													//------------Query exception--------------
													if( !$stock_nd ) {
														query_exception($uniquecode, basename($_SERVER['PHP_SELF']), $update_stock_nd_array, $json, 'Stock in - stock nd update failed', 'kre', $device, $txn['txn_id']);
													}
													//-----------------------------------------

													$insert_stock_rd_array = array(
														"rd_id" => $retailer_data["rd_code"],
														"received_from" => $fsn_data["nd"],
														"model_number" => $fsn_data["model_number"],
														"fsn" => $fsn_data["fsn"],
														"channel_code" => $retailer_code,
														"channel_name" => $retailer_data["retailer_name"],
														"channel_city" => $retailer_data["city"],
														"channel_state" => $retailer_data["state"],
														"channel_region" => $retailer_data["zone"],
														"sale_date" => $today,
														"kre" => $uniquecode,
														"receive_date" => $today,
														"sellable" => "yes",
														"rd_location" => $stc,
													);
													$stock_rd = insert($insert_stock_rd_array , 'stock_rd');

													//------------Query exception--------------
													if( !$stock_rd ) {
														query_exception($uniquecode, basename($_SERVER['PHP_SELF']), $insert_stock_rd_array, $json, 'Stock in - stock rd update failed', 'kre', $device, $txn['txn_id']);
													}
													//-----------------------------------------

													$insert_stock_retailer_array = array(
														"retailer_id" => $retailer_code,
														"received_from" => $retailer_data["rd_code"],
														"model_number" => $fsn_data["model_number"],
														"fsn" => $fsn,
														"receive_date" => $today,
														"kre" => $uniquecode,
														"sellable" => "yes",
														"stock_in_reason" => $msg,
														"stock_in_tran_id" => $txn['txn_id'],
														"offline_stock_in" => "yes",
													);
													$stock_retailer = insert($insert_stock_retailer_array, 'stock_retailer');

													//------------Query exception--------------
													if( !$stock_retailer ) {
														query_exception($uniquecode, basename($_SERVER['PHP_SELF']), $insert_stock_retailer_array, $json, 'Stock in - stock rd update failed', 'kre', $device, $txn['txn_id']);
													}
													//-----------------------------------------
													$all_query = $stock_all && $stock_nd && $stock_rd && $stock_retailer;
												} else {
													// Deny Stock In
													exception($uniquecode, basename($_SERVER['PHP_SELF']), $json, "Your retailers RD does not come under the ND who has this FSN.", 'kre', $device);
													send_reply($sender, 'Your retailers RD does not come under the ND who has this FSN.');
													exit;
												}
											} else {
												// Deny Stock In
												exception($uniquecode, basename($_SERVER['PHP_SELF']), $json, 'Stock in denied, FSN not mapped to RD channel.', 'kre', $device);
												send_reply($sender, 'Stock in denied, FSN not mapped to RD channel.');
												exit;
											}
										}
										$sql_log = $mysqli->prepare("insert into stock_all_log(fsn, model_number, admin, admin_sell_date, nd, nd_location, nd_sell_date, rd, rd_location, rd_sell_date, retailer, retailer_sell_date, kre, kre_sell_date, customer, sellable, sale_done, stock_return, return_dt, return_reason, stock_in_reason, txn_group, am_code, upload_date, beat_plan_txn, tpr_id, admin_username, nd_username, rd_username) select fsn, model_number, admin, admin_sell_date, nd, nd_location, nd_sell_date, rd, rd_location, rd_sell_date, retailer, retailer_sell_date, kre, kre_sell_date, customer, sellable, sale_done, stock_return, return_dt, return_reason, stock_in_reason, txn_group, am_code, upload_date, beat_plan_txn, tpr_id, admin_username, nd_username, rd_username from stock_all where fsn=?");
										$sql_log->bind_param("s",$fsn);

										// if(isset($sql_individual_tbl2)){
										//   	$conn->query($sql_individual_tbl2);
										// }

										$sql_log->execute();
										if( $all_query ){
											$sql_log->execute();
											$sql_log->close();
											//========= master txn -==================
						                    update_txn($txn['txn_id'], $txn['type'], $uniquecode, 'kre', $device);
						                    //========================================
											send_reply($sender, 'Stock in successful');
											exit;
										} else {
											exception($uniquecode, basename($_SERVER['PHP_SELF']), $json, 'Stock In failed.', 'kre', $device);
											send_reply($sender, 'Stock In failed.');
											exit;
										}

									} else {
										exception($uniquecode, basename($_SERVER['PHP_SELF']), $json, 'The retailer you are currently mapped to could not be found.', 'kre', $device);
										send_reply($sender, 'The retailer you are currently mapped to could not be found.');
										exit;
									}

								} else {
									exception($uniquecode, basename($_SERVER['PHP_SELF']), $json, 'Store not registered.', 'kre', $device);
									send_reply($sender, 'Store not registered.');
									exit;
								}

							}

						} else {
							exception($uniquecode, basename($_SERVER['PHP_SELF']), $json, 'Raise FSN issue.', 'kre', $device);
							send_reply($sender, 'Raise FSN issue.');
							exit;
						}
					} else {
						exception($uniquecode, basename($_SERVER['PHP_SELF']), $json, 'FSN entered is not sellable.', 'kre', $device);
						send_reply($sender, 'FSN entered is not sellable.');
						exit;
					}

				} else {
					exception($uniquecode, basename($_SERVER['PHP_SELF']), $json, 'FSN entered is already sold.', 'kre', $device);
					send_reply($sender, 'FSN entered is already sold.');
					exit;
				}

			} else {
				exception($uniquecode, basename($_SERVER['PHP_SELF']), $json, 'FSN entered does not exist, please put correct FSN. Else raise FSN issue.', 'kre', $device);
				send_reply($sender, 'FSN entered does not exist, please put correct FSN. Else raise FSN issue.');
				exit;
			}

		} else {
			send_reply($sender, 'Invalid Kre Id');
		}

	} else {
		send_reply($sender, 'Invalid Message');
	}
}
?>