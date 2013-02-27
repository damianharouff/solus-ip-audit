<?php

$API_url = "https://your solus api:5656/api/admin";		//URL for Solus API
$API_id = "";	//ID for Solus API
$API_key = "";	//key for Solus API

$db_database = ""; 	//your WHMCS database
$db_user = "";		//a database user that has SELECT capability for your WHMCS database
$db_password = "";		//database user's password
$db_host = "localhost";			//probably 'localhost', change if you know it needs to be changed
$db_charset = "latin1";			//our WHMCS database' charset is latin1, not sure if this is default or what

//shouldn't need to change anything below here
//==============================================================================================

$virttypes = array(	"openvz",
			"xen",
			"xen hvm",
			"kvm");

$nodelist = array();
$vmlist = array();
$iplist = array();
$tempiplist = array();
$badcount = 0;

//================================

function getAPIData($variableArray) {

	global $API_url, $API_id, $API_key;

	//shystered from Solus' website
        $postfields["id"] = $API_id;
        $postfields["key"] = $API_key;
	$postfields = array_merge($postfields, $variableArray);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $API_url . "/command.php");
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT, 20);
        curl_setopt($ch, CURLOPT_FRESH_CONNECT, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array("Expect:"));
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postfields);
        $data = curl_exec($ch);
        curl_close($ch);

	return($data);
}

//================================

//get a list of nodeids
foreach ($virttypes as $data) {

	$apimethods = Array("type" => $data, "action" => "node-idlist");
	$data = getAPIData($apimethods);
	preg_match_all('/<(.*?)>([^<]+)<\/\\1>/i', $data, $match);
	$result = array();
	foreach ($match[1] as $x => $y)	{
		$result[$y] = $match[2][$x];
	}

	if ($result["status"] === "success") {
		$nodelist = array_merge($nodelist, explode("," , $result["nodes"]));
	}

} 

print "Found 11 nodes." . PHP_EOL;

//get a list of assigned IPs for each node
foreach ($nodelist as $nodeid) {
 
        $listVMmethods = Array(	"nodeid" => $nodeid, "action" => "node-virtualservers");
        $vmlist = getAPIData($listVMmethods);
	preg_match_all('/<ipaddress>(.+)<\/ipaddress>/', $vmlist, $tempiplist);
	$iplist = array_merge($tempiplist[1], $iplist);
}

print "Found " . count($iplist) . " IP addresses." . PHP_EOL;

//begin a PDO connection thingy
$db = new PDO('mysql:host='.$db_host.';dbname='.$db_database.';charset='.$db_charset, $db_user, $db_password);
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);

print PHP_EOL . "The following IP addresses are not in WHMCS: " . PHP_EOL . PHP_EOL;

foreach($iplist as $ip) {

	$stmt = $db->query('SELECT 1 as "Found" FROM tblhosting WHERE domainstatus IN ("Active", "Suspended") and (dedicatedip = "'.$ip.'" OR 
assignedips LIKE "%'.$ip.'%\n")');
	$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

	if (!$results) {
		print $ip . PHP_EOL;
		$badcount++;
	}
}

print PHP_EOL . $badcount . " addresses found." . PHP_EOL . PHP_EOL;

?>
