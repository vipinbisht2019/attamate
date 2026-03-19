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

	if( $key1 == 'AMZKDL'  && $key2 == 'SOUT' ){
		$uniquecode = $data[0];
		$fsn = $data[1];
		$product_serial_no = $fsn;
		$cus_name = $data[2];
		$json = json_encode(array('uniquecode'=>$uniquecode, 'fsn'=>$fsn, 'cus_name'=>$cus_name));
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

			$txn = txn_id("SEO");
			$txnid = $txn['txn_id'];
			$naa = $txn['txn_id'];
			$up1 = '0';
			$up2 = '0';
			$up3 = '0';

			$row = $result->fetch_assoc();
			$q2 = $mysqli->query("select * from stock_retailer where retailer_id = '$row[retailer_code]' AND fsn = '$product_serial_no'");	
			if( $q2->num_rows > 0 ) {
				$q3 = $mysqli->query("select * from stock_retailer where retailer_id = '$row[retailer_code]' AND fsn = '$product_serial_no' AND sale_done != 'done'");
				$rs2 = $q3->fetch_assoc();
				if( $q3->num_rows > 0 ) {

					// if(isset($_FILES["sale_proof"])){
					// 	$tmp_name = $_FILES["sale_proof"]["tmp_name"];
					// 	$new_name = $uniquecode.'_'.time().".png";
					// 	move_uploaded_file($tmp_name, '../data/sell_out/kre/'.$new_name);
					// 	$new_name = base_url."data/sell_out/kre/".$new_name;
					// } else {
					// 	$new_name = '';
					// }

					//==================== TPR CHECK AND ENTRY ========================
					$retailer = retailer($row['retailer_code']);
					$retailer_data = $retailer->fetch_assoc();
					$tpr = "select `master_txn` FROM scheme_management where ( `audience`='retailer' or `audience`='".$retailer_data['trade_type']."' ) and (`region`='".$retailer_data['zone']."' or `region`='all' ) and ( `state`='".$retailer_data['state']."' or `state`='all' ) and `start_date`<='".$today."' and `end_date`>='".$today."' and `tpr`='yes' and `asin`='".$rs2['model_number']."' ";
					$result_tpr = $mysqli->query($tpr);
					if( $result_tpr > 0 ) {
						$sch_data = $result_tpr->fetch_assoc();
						$sch_id = $sch_data['master_txn'];
					} else {
						$sch_id = '';
					}
					//========================== TPR CLOSE ============================

					$register_product_claim_array = array(
						"product_serial_no" => $product_serial_no,
						"model_number" => $rs2[model_number],
						"cus_name" => $cus_name,
						"kre_code" => $uniquecode,
						"tran_id" => $txnid,
						"dt" => $today,
						"retailer_code" => $row[retailer_code],
						"offline_sell_out" => "yes",
					);
					$succ = insert($register_product_claim_array, 'register_product_claim');
					if( $succ > 0 ) {
						$up1 = '1';
					} else {
						query_exception($uniquecode, basename($_SERVER['PHP_SELF']), $register_product_claim_array, $json, 'Product sellout failed', 'kre', $device, $txnid);
					}


					$stock_retailer_array = array(
						"sale_done" => "done",
						"tran_id" => $txnid,
						"kre" => $uniquecode,
						"sale_date" => $today,
						"tpr_id" => $sch_id,
						"offline_sell_out" => "yes",
					);
					$where = "fsn='".$product_serial_no."'";
					$swr = update($stock_retailer_array, 'stock_retailer', $where);
					if( $swr ) {
						$up2 = '1';
					} else {
						query_exception($uniquecode, basename($_SERVER['PHP_SELF']), $stock_retailer_array, $json, 'Product sellout - stock retailer update failed', 'kre', $device, $txnid);
					}

					$stock_all_array = array(
						"sale_done" => "done",
						"retailer_sell_date" => $today,
						"kre_sell_date" => $today,
						"customer" => $cus_name,
						"tpr_id" => $sch_id,
					);
					$where1 = "fsn='".$product_serial_no."'";
					$swrr = update($stock_all_array, 'stock_all', $where1);
					if( $swrr ) {
						$up3 = '1';
					} else {
						query_exception($uniquecode, basename($_SERVER['PHP_SELF']), $stock_retailer_array, $json, 'Product sellout - stock all update failed', 'kre', $device, $txnid);
					}

					if( $up1=='1' && $up2=='1' && $up3=='1' ) {

						$model_sql = "select * from tbl_model where `asin`='".$rs2['model_number']."' ";
						$model_result = $mysqli->query($model_sql);
						if( $model_result->num_rows > 0 ) {
							$model_data = $model_result->fetch_assoc();
							$model_points = $model_data['level'.$row['level']];
						} else {
							$model_points = '0';
						}

						//======================== passbook entry and update points ========================
						if( $row['total_points'] != ($row['total_points']+$model_points) )
						{
							$points_array = array(
								"total_points" => $row['total_points']+$model_points,
							);
							$where2 = "kre_code='".$uniquecode."' and role='kre' ";
							$points = update($points_array, 'user', $where2);
							if( !$points ) {
								query_exception($uniquecode, basename($_SERVER['PHP_SELF']), $points_array, $json, 'Kre sellout - user table points update failed', 'kre', $device, $txnid);
							}
			
							$insert_array = array(
								'uniquecode' => $uniquecode,
								'role' => 'kre',
								'master_txn' => $txnid,
								'points' => $model_points,
								'txn_type' => 'Credit',
								'total_points' => $row['total_points']+$model_points,
								'date' => date("Y-m-d"),
								'time' => date("H:i:s"),
								'device_info' => $device,
							);
							$success = insert($insert_array, 'passbook');
							if( !$success > 0 ) {
								query_exception($uniquecode, basename($_SERVER['PHP_SELF']), $insert_array, $json, 'Kre Sellout - passbook entry failed', 'kre', $device, $txnid);
							}
						}
						//========= master txn -==================
						update_txn($txn['txn_id'], $txn['type'], $uniquecode, 'kre', $device);
						//========================================
						send_reply($sender, "Sellout marked successfully");
					} else {
						exception($uniquecode, basename($_SERVER['PHP_SELF']), $json, 'sellout failed', 'kre', $device);
						send_reply($sender, "Sellout failed.");
					}

				} else {
					exception($uniquecode, basename($_SERVER['PHP_SELF']), $json, 'FSN already submitted', 'kre', $device);
					send_reply($sender, "FSN already submitted");
				}

			} else {
				exception($uniquecode, basename($_SERVER['PHP_SELF']), $json, 'FSN not available', 'kre', $device);
				send_reply($sender, "FSN not available");
			}

		} else {
			send_reply($sender, "Invalid Kre Id");
		}

	} else {
		send_reply($sender, "Invalid Message");
	}
}
?>