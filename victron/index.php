<style>
body{background:lightgray;}
table{border:1px solid gray;}
tr{border-bottom:1px solid gray;}
td{border:0px solid gray;background:#8A8A8A;}
</style>
<?php
error_reporting(0);

//settings
	
	//host
	 $test_host_ip = "home.arnekaas.nl";
	 if(isset($_GET['victron_ip'])){ $test_host_ip = $_GET['victron_ip'];}
	 else echo "Change the url to http://".$_SERVER['HTTP_REQUEST']."?victron_ip=your_ip to load your system data.<br>";

	 //mysql
	 $user			= "arne_victron";
	 $password		= "Victron123";
	 $database		= "arne_victron";
	 $table 		= $test_host_ip;
	 
	 // get victron parameters list
	 $lines = explode("\n",file_get_contents("victron ccgx parameter list - Field list.csv"));
	 $headers = explode(",",str_replace("\n","",$lines[0]));unset($lines[0]); // var_dump($headers);
	 foreach ($lines as $key => $line) {
	 	foreach (explode(",",$line) as $i => $colum) {
	 		// dbg($headers);
	 		// echo htmlspecialchars($colum);
	 		$colum = str_replace("\"","",$colum);
	 		$vars[$key][$headers[$i]] = $colum;
	 		$state = explode(";",$colum);
	 		if(count($state)>1)
	 			foreach ($state as $key2 => $value) {
	 				// echo ($key+2)." ".$value."<br>";
	 				list($id,$explenation) = explode("=",$value);
	 				$states[$key+2][$id] = $explenation;
	 			}
	 	}
	 }
	 
 // include required functions

	 require_once dirname(__FILE__) . '/../Phpmodbus/ModbusMaster.php';
	 require_once dirname(__FILE__) . '/../Phpmodbus/ModbusMasterTcp.php';
	 require_once dirname(__FILE__) . '/../functions.php';


//connect to DB and create table
	mysql_connect('localhost',$user,$password);
	 @mysql_select_db($database) or die( "Unable to select database");
	 mysql_query("CREATE TABLE `$table"."_lood` (`datetime` DATETIME NOT NULL , PRIMARY KEY (`datetime`) ,  UNIQUE INDEX `datetime_UNIQUE` (`datetime` ASC) );");
	 mysql_query("CREATE TABLE `$table"."_lithium` (`datetime` DATETIME NOT NULL , PRIMARY KEY (`datetime`) ,  UNIQUE INDEX `datetime_UNIQUE` (`datetime` ASC) );");
	 
	
	 echo "Starting...<br>";
// Create Modbus object
$modbus = new ModbusMasterTcp($test_host_ip,502);

// victron_set_status($modbus,3); echo "<b>The inverter will switch on!</b>";
// $data = victron_get_status($modbus);
// victron_save_status(status());
// dbg($data);die();


for ($i=0; $i < 3; $i++) { 
	$t = microtime();
	$log[$i] = victron_get_status($modbus,246);
	victron_save_status($log[$i],"_lood");
	$log[$i]['datetime'] = date("Y-m-d H:i:s");
	
	$log2[$i] = victron_get_status($modbus,0);
	victron_save_status($log2[$i],"_lithium");
	$log2[$i]['datetime'] = date("Y-m-d H:i:s");

	// battery monitor?
	// test($modbus,246,259,1);
	// $log3[$i] = victron_get_status($modbus,256,0,259,1);
	// echo "loading this data took ".(1000*(microtime()-$t))."ms<br>";
	sleep(1);
}
echo "<h3>lood?</h3>";
foreach ($vars as $key => $var) {
	echo "<strong>".($log[0][($key-1)]/$var['Scalefactor'])." ".$var['dbus-unit']."</strong> >> ".$var['description'].", unit: ".$var['dbus-unit'].", scaled factor: ".$var['Scalefactor']."<br>";
	if($key>31) break;
}
dbg2table($log);

echo "<h3>Lithium</h3>";
foreach ($vars as $key => $var) {
	echo "<strong>".($log2[0][($key-1)]/$var['Scalefactor']).$var['dbus-unit']."</strong> > ".$var['description'].", unit: ".$var['dbus-unit'].", scaled factor: ".$var['Scalefactor']."<br>";
	if($key>31) break;
}


// dbg2table($vars);
dbg2table($log2);
echo "bms702";
dbg2table($log3);

die("die...");

echo "<b>Trying to send 100W to the grid!</b>Showing readout every second or so:<br>";
victron_set_power($modbus,-100); //only works if hub4 assistant is installed.

// die();
// victron_set_status($modbus,1);die(); //shut down

function victron_save_status($data,$ext=""){
 // dbg($data);
 global $test_host_ip;
 global $vars;
 
 // create table headers
 foreach (array_keys($data) as $key => $header) {
 	mysql_query("ALTER TABLE  `$test_host_ip".$ext."` ADD  `".$header."` FLOAT DEFAULT NULL;");
 }
 
 //insert the data
 mysql_query("INSERT INTO `$test_host_ip".$ext."` (`datetime`,`".implode(array_keys($data),"`,`")."`) VALUES (now(),'".implode($data,"','")."');");
 
}

function status($dbg=0){
	global $data;
	global $vars;
	global $states;
	if($dbg)
	{
		echo "<table>";
		foreach ($data as $key => $value) {
			if($data[25] == 1) 
				if(strpos($vars[$key+1]['description'],'2')+strpos($vars[$key+1]['description'],'3')>0) continue;
			echo "<tr><td>";
			echo ($key+3)."</td><td>";
			// echo $vars[$key+1]['Scalefactor']."</td><td>";
			echo $vars[$key+1]['description']."</td>
				<td><b>".($value/$vars[$key+1]['Scalefactor'])."</b></td>";
			if(isset($states[$key+3])) echo "<td> ".$states[$key+3][$value]."</td></tr>";
			else echo "<td> ".$vars[$key+1]['dbus-unit']."</td></tr>";
		
		}
		echo "</table>";
	}
	//save status
	foreach ($data as $key => $value) {
		$status[$vars[$key+1]['description']] = ($value/$vars[$key+1]['Scalefactor']);
	}
	
	return $status;	
}

function victron_get_status($modbus,$id=246,$dbg=0,$start=3,$points=31){
	global $states;
	global $vars;
	try {
	    // FC 3
	    // read 3 words (20 bytes) from device ID=?, address=31
	    $recData = $modbus->readMultipleRegisters($id, $start, $points); //id, start_register, amount 31 to 33 
		//VE.Bus state	31 VE.Bus Error code	32 Switch Position	33
	}
	catch (Exception $e) {
	    // Print error information if any
	    echo $modbus;
	    echo $e;
	    exit;
	}


	// Print status information
	if($dbg) echo "<b>Connection details:</b>" . $modbus;

	// Print te results string
	if($dbg) echo "Results:";
	$values = array_chunk($recData, 2);
	foreach($values as $i => $bytes)
	{
		// $data[$vars[$i+1]['description']] = PhpType::bytes2signedInt($bytes);
		$data[$i] = PhpType::bytes2signedInt($bytes);
	    // echo $i." ".PhpType::bytes2signedInt($bytes) . "</br>";
	}
	// echo "Grid: ".($data[0]/10)."V, ".($data[6]/100)."Hz, AC lim:".($data[19]/10)."A, AC in:".($data[9]*10)."W,<br>
		// Load: AC out:".($data[20]*10)."W, status: ".$states[33][$data[30]]."<br>
		// Bat: DC ".($data[11]/100)."V, ".($data[12]/10)."V<br>";
	return $data;
}

function victron_set_status($modbus,$status=1){
	// Data to be writen
	$data = array($status);
	$dataTypes = array("INT");

	try {
	    // FC16
	    $modbus->writeMultipleRegister(246, 33, $data, $dataTypes);
	}catch (Exception $e) {
	    // Print error information if any
	    echo $modbus;
	    echo $e;
	    exit;
	}
}

function victron_set_power($modbus,$status=-10){
	// Data to be writen
	$data = array($status);
	$dataTypes = array("INT");

	try {
	    // FC16
	    $modbus->writeMultipleRegister(246, 37, $data, $dataTypes);
	}catch (Exception $e) {
	    // Print error information if any
	    echo $modbus;
	    echo $e;
	    exit;
	}
}

function victron_get_power($modbus,$dbg=0){
	global $states;
	try {
	    // FC 3
	    // read 3 words (20 bytes) from device ID=?, address=31
	    $recData = $modbus->readMultipleRegisters(246, 37, 3); //id, start_register, amount 31 to 33 
		//VE.Bus state	31 VE.Bus Error code	32 Switch Position	33
	}
	catch (Exception $e) {
	    // Print error information if any
	    echo $modbus;
	    echo $e;
	    exit;
	}
	$values = array_chunk($recData, 2);
	foreach($values as $i => $bytes)
	{
		$data[] = PhpType::bytes2signedInt($bytes);
		if($dbg) echo $i." ".PhpType::bytes2signedInt($bytes) . "</br>";
	}
	return $data;
}

// debug the modbus read
die();

function test($modbus,$id, $start, $points){

	$recData = $modbus->readMultipleRegisters($id, $start, $points); 
	// Print read data
	echo "</br>Data:</br>";
	var_dump($recData); 
	echo "</br>";


	// Received data
	echo "<b>Received Data</b>\n";
	print_r($recData);

	// Conversion
	echo "<h2>32 bits types</h2>\n";
	// Chunk the data array to set of 4 bytes
	$values = array_chunk($recData, 4);

	// Get float from REAL interpretation
	echo "<b>REAL to Float</b>\n";
	foreach($values as $bytes)
	    echo PhpType::bytes2float($bytes) . "</br>";

	// Get integer from DINT interpretation
	echo "<b>DINT to integer </b>\n";
	foreach($values as $bytes)
	    echo PhpType::bytes2signedInt($bytes) . "</br>";

	// Get integer of float from DINT interpretation
	echo "<b>DWORD to integer (or float) </b>\n";
	foreach($values as $bytes)
	    echo PhpType::bytes2unsignedInt($bytes) . "</br>";

	echo "<h2>16 bit types</h2>\n";
	// Chunk the data array to set of 4 bytes
	$values = array_chunk($recData, 2);

	// Get signed integer from INT interpretation
	echo "<b>INT to integer </b>\n";
	foreach($values as $bytes)
	    echo PhpType::bytes2signedInt($bytes) . "</br>";

	// Get unsigned integer from WORD interpretation
	echo "<b>WORD to integer </b>\n";
	foreach($values as $bytes)
	    echo PhpType::bytes2unsignedInt($bytes) . "</br>";

	// Get string from STRING interpretation
	echo "<b>STRING to string </b>\n";
	echo PhpType::bytes2string($recData) . "</br>";    
}
