<?php 

$mysqli = new mysqli("localhost","root","","attamate");

if($mysqli->connect_errno) {

	trigger_error('Connection failed: '.$mysqli->error);

} else {
	
	//======= Common Php setting =============
	header('Content-Type: application/json');
	date_default_timezone_set('Asia/Kolkata');
	
	$key = "1234567891234567";
	$datebyweb = date("Y-m-d");
	$timebyweb = date("H:i:s");
	$today = date("Y-m-d");
	
	//============= constant ===============
//	define('base_url', 'http://triad01.com/api-new/');
	define('base_url', 'http://localhost/attamate/api-new-attamate/');

	//========= Get Json Data ==============
	$json = file_get_contents('php://input');
	$get = json_decode($json);
	
	//========= For run on arms enterprise server or php version 5.6 =========
	$mysqli->query("SET NAMES 'utf8'");
    //$mysqli->query("SET CHARACTER SET utf8");
    
}

?>