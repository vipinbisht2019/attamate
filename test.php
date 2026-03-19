<?php  
include('config/config.php');
include('config/functions.php');

$lat = $get->lat;
$long = $get->long;
$address = $get->address;
$mock = $get->mock;
// error_log($json);
if(!empty($lat)){
    
    	$insert = array(
			'lat' => $lat,
			'lng' => $long,
			'address'=>$address,
			'mock'=>$mock
		);
		$query = insert($insert, 'test');
        if( $query > 0 ){
		    exit("success");
        } else {
		    exit("fail");
        }
	
} else {
	exit("Lat/Long is required.");
}
?>