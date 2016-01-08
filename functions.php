<?php
function file_get_with_post($path,$post,$header) {
  $context = stream_context_create(array( 
    'http' => array( 
      'method'  => 'POST', 
      'header'  => $header, 
      'content' => http_build_query($post),
      'timeout' => 5,
    ), 
  )); 
  return file_get_contents($path, false, $context); 
}


function create_link($settings)
{
	//get the old url
	if(strpos($_SERVER['REQUEST_URI'],'?'))
		$current_url = substr($_SERVER['REQUEST_URI'],strpos($_SERVER['REQUEST_URI'],'?')+1);
	elseif(strpos($_SERVER['REQUEST_URI'],'/'))
		$current_url = substr($_SERVER['REQUEST_URI'],strpos($_SERVER['REQUEST_URI'],'/')+1);
	else
		$current_url = '';
		
	if(strlen($current_url))
	{
		foreach (explode('&',$current_url) as $key => $current_setting)
		{
			list($key,$value) = explode('=',$current_setting);
			$current_values[$key] = $value;
		}
	}
	
	//merge in new data
	foreach ($settings as $key => $value) {$current_values[$key] = $value;}
	
	//create new url
	if(strpos($_SERVER['REQUEST_URI'],'?'))
		$link  = substr($_SERVER['REQUEST_URI'],0,strpos($_SERVER['REQUEST_URI'],'?'));
	else
		$link  = substr($_SERVER['REQUEST_URI'],0,strrpos($_SERVER['REQUEST_URI'],'/'));
	foreach ($current_values as $key => $setting)
	{
			if(!strpos($link,"?")) $link .= '?';
			else $link .= '&';
			$link .= $key."=".$setting;
	}
	// return $_SERVER[SERVER_NAME];
	return $link;
}

function deep_asort($array,$colum_name){
	
	//creat array with colum to sort
	foreach ($array as $key => $row){$temp_array[$key] = $row[$colum_name];}

	//sort the array
	asort($temp_array);
	
	//replace arrays in ordered array
	foreach ($temp_array as $key => $value) {$ordered_array[$key] = $array[$key];}
	return $ordered_array;
}
function array_implode( $glue, $separator, $array ) {
    if ( ! is_array( $array ) ) return $array;
    $string = array();
    foreach ( $array as $key => $val ) {
        if ( is_array( $val ) )
            $val = implode( ',', $val );
        $string[] = "{$key}{$glue}{$val}";
        
    }
    return implode( $separator, $string );
    
}
function echo_array($array,$echo_id=true){
	echo "<table><tr>";
	if($echo_id) echo "<th></th>";
	foreach(array_keys(current($array)) as $i => $key)
	{
			echo "<th>";
			echo $key;
			echo "</th>";
	}
	echo "</tr>";foreach($array as $i => $row)
	{
		echo "<tr>";
		if($echo_id) echo "<th>$i</th>";
		foreach($row as $i => $value)
		{
				echo "<td>";
				echo ($value);
				echo "</td>";
		}
		echo "</tr>";
	}
	echo "</table>";
}
function echo_table($table_name,$condition='',$colums='*',$searchfields=false){
	echo "<table width='100%'>\n\t<tr>";
	//get alle the top item and start a loop
	$titles = get_colums($table_name,$colums);
	{
		foreach($titles as $name => $item)
		{
			//display the item
			echo "\t\t<th style='text-align:center;'>".$item."</th>";
		}
	}
	echo "</tr>";
	$table = table_array($table_name,$condition,$colums);
	
	//diplay all sub items()
	foreach($table as $i => $row)
	{
		echo  "<tr>\n";
		foreach($row as $j => $item)
		{
			echo  "\t<td>".$item."</td>\n";
		}
		echo "</tr>\n";
	}	

		$previous = $page['id'];

	echo "</table>";

}

function search_fields($table,$colums='*',$condition='',$searchbutton='Search',$row_only=true){
if($row_only){	echo "<table width='100%'>";}
	$query 		= "SELECT $colums FROM `$table` $condition";
	$result		= mysql_query($query);
	$num		= mysql_numrows($result);
	
	//get the colums of this table
	$colums = array();
	for($i=0;$i<mysql_num_fields($result);$i++)
	{
	    $colums = array_merge($colums,array($i=>mysql_fetch_field($result, $i)->name));
	}

	echo "<tr><form action='?".$_SERVER['QUERY_STRING']."' method='POST' enctype='multipart/form-data'>";
	foreach($colums as $i => $colum)
	{
		echo "<td style='text-align:center;' colspan='1'>
		<input type='text' style='width:100px;' name='$colum' value='".$_POST[$colum]."'/></td>";
	}
	// echo "</tr><tr><td colspan='$num'><input type='submit' type='button' value='search'></td></tr>";
	echo "
		<tr>
		<th colspan='4' style='text-align:right;padding:0px 30px 0px 50px;'>
			<input type='submit' name='submit' value='Search users' style='width:200px;'/>
			</form>
		</th>
		</tr>";
if($row_only){		"</table>";}
}
function search_condition(){
	//create the search condition
	foreach($_POST as $col_name => $value)
	{
		if($value != '')
		{
			if(!$first)
			{
			$condition = "WHERE `".$col_name."` LIKE '%".$value."%'";
			$first = true;
			}else{
			$condition .= " AND `".$col_name."` LIKE '%".$value."%'";
			}
		}
	}
	return $condition;
}

function get_colums($table,$colums='*'){
	$query 		= "SELECT $colums FROM `$table`";
	$result		= mysql_query($query);
	$num		= mysql_numrows($result);	
	
	//get the colums of this table
	$colums = array();

	for($i=0;$i<mysql_num_fields($result);$i++)
	{
	    $colums = array_merge($colums,array($i=>mysql_fetch_field($result, $i)->name));
	}
	return $colums;
}
function ta($table,$condition='',$colums='*') {return table_array($table,$condition='',$colums='*');}
function table_array($table,$condition='',$colums='*',$dbg=false){
	$results_array = array();
	
	$query 		= "SELECT $colums FROM `".$table."` ".$condition;
	if($dbg) echo $query."<br><br>";	
	$result		= mysql_query($query);
	// dbg($result);
	if($result)
	{
	
		//put all the results of the table in an array
		for($j=0;$j<mysql_num_fields($result);$j++)
		{
			$colum =  mysql_fetch_field($result, $j)->name;
			for($i=0;$i<mysql_numrows($result);$i++)
			{
			 	$results_array[$i][$colum] = mysql_result($result,$i,$colum);	
			}
		}
	}else{
		if($dbg) echo "error: ".$query;
	}
	
	return $results_array;
}


function dbg($value, $extended = false){
echo "<pre>";
if (!is_object($value) && !is_array($value))
{
	var_dump($value);
}else{
	if($extended)
		{var_dump($value);}
	else
		{print_r($value);}

}
echo "</pre>";
}

function users_in_group($group_id)
{
	$users = table_array("Peev_user_group","WHERE group_id=".$group_id);
	foreach($users as $i => $user)
	{
		$user_array = table_array("Peev_users","WHERE id=".$user['user_id']);
		if(count($user_array)) $users[$i] = array_merge($users[$i],$user_array[0]);
	}
	return $users;
}
function dbg2table($array,$header='',$max_dbg=5,$headers=true,$index=0)
{
	
	echo "<table>";
	if(strlen($header)) 
	echo "<tr><th colspan='".(count(next($array))+$index)."'><strong>$header</strong></th></tr>";
	if($headers) 		
	{
		echo "<tr>";
		if($index) echo "<th>ID</th>";
		foreach (current($array) as $key2 => $value) {
			echo "<th>$key2</th>";
		}
		echo "</tr>";
	}
	foreach ($array as $key => $values) {
		echo "<tr>";
		if($index) echo "<th>$key</th>";
		foreach ($values as $key2 => $value) {
			echo "<td>$value</td>";
		}
		echo "</tr>";
	}
	echo "</table>";
}
function array2table($array,$header='',$max_dbg=5){
	echo "<table>";
	if(strlen($header)) echo "<tr><th colspan='2'><strong>$header</strong></th></tr>";
	foreach ($array as $key => $value) {
		if(is_array($value) && count($value) <= $max_dbg)
		{
			echo "<tr><th>$key</th><td>";
			dbg($value);
			echo "</td></tr>";
		}else{
			echo "<tr><th>$key</th><td>$value</td></tr>";}
	}
	echo "</table>";
}
function dropdown($name,$array=array(1=>'leeg'),$label='',$selected='',$size='150px',$ajax=''){
	echo <<<END
		<select id="$name" name='$name' $ajax style='width:$size;'>	
END;
if(strlen($label))	echo "<option value=''>$label</option>";
	foreach($array as $value => $name)
	{
		echo "<option value=\"".$value."\"";
		if($selected == $value && $selected!='') echo " selected";
		echo ">";
		if(!is_array($name)) echo $name;
		elseif($name['name']) echo $name['name'];
		else echo $name;
		echo "</option>\n";
	}
	echo "</select>";
}
function average($array){
	return array_sum($array)/count($array);
}

//peev functions
function mysql_insert($table,$check_fields,$update_fields='',$limit='1'){
	$condition = "WHERE ";
	foreach ($check_fields as $key => $value) 
	{
		if($first){ $condition .= " AND ";}else{$first=true;}
		$condition .= "`".$key."`='".$value."'";
	}$first=false;
	
	$results = table_array($table,$condition);

	if(!count($results)) #&& is_array($update_fields))
	{
		$fields = $check_fields;
		if(is_array($update_fields)) $fields = array_merge($check_fields,$update_fields);
		$query = "INSERT INTO `$table` (";
		foreach ($fields as $key => $value) 
		{
			if($first){ $query .= ",";}else{$first=true;}
			$query .= "`".$key."`";
		}$first=false;
		$query .= ") VALUES (";
	
		foreach ($fields as $key => $value) 
		{
			if($first){ $query .= ",";}else{$first=true;}
			$query .= "'".$value."'";
		}$first=false;
		$query .=  ")";
	
		if(!mysql_query($query)) echo $query;
		// /* MySQL 07:35:15 */ INSERT INTO `Peev_eval_logs` (`session_id`,`user_id`,`group_id`,`submitted`,`collected`,`results_type`) VALUES ('73','1','552',NULL,NOW(),NULL);
		
	}else{
		//update /* MySQL 16:46:16 */ UPDATE `Peev_eval_logs` SET `session_id`='73', `user_id`='1456495', `group_id`='552', `submitted`=NULL, `collected`=NOW(), `results_type`='1' WHERE `session_id` = '73' AND `user_id` = '1456495' AND `group_id` = '552' AND `submitted` IS NULL AND `collected` = '0000-00-00 00:00:00' AND `results_type` = '1' LIMIT 1;
		
		$query = "UPDATE `$table` SET ";
		foreach ($update_fields as $key => $value) 
		{
			if($first){ $query .= ",";}else{$first=true;}
			$query .= "`".$key."`='".$value."'";
		}$first=false;
		$query .=  " WHERE ";
		foreach ($check_fields as $key => $value) 
		{
			if($first){ $query .= " AND ";}else{$first=true;}
			$query .= "`".$key."` = '".$value."'";
		}$first=false;
		$query .=  " LIMIT ".$limit;
		if(!mysql_query($query)) mail('peev@tudelft.nl','peev@tudelft.nl','Query error in '.$_GET['page'],$query);
	}
}
function info($name,$display='none',$style=''){
	$info = current(table_array("Peev_info","WHERE name='".$name."'"));
	if(count($info))
	{	
		// echo "<div style='position:relative;left:96%;top:-34px;'>";
		$body = "<h1 style='text-align:center;'>".$info['title']."</h1>";
		$body.= "<p>".str_replace("\n","<br>",$info['info'])."</p>";
		$body = str_replace(array("<ul>","</ul>"),array("</p><ul>","</ul><p>"),$body);
		question($body,"info_".$info['name'],'',$display,$style);
		// echo "</div>";
	}
}
function question($body,$id,$link_text='',$display='none',$style=''){
	echo <<<END
	<div class='question' style='$style' onClick="javascript:document.getElementById('$id').style.display = '';changeOpac(90,'background_$id');document.getElementById('background_$id').style.display = '';">
		<img src="application/images/questionmark.gif" width="20px" height="20px" alt="Show">
		$link_text
	</div>
END;
	message($body,$id,$display);
}
function message($body,$id,$display='',$print=''){

echo <<<END
	<div id='$id' class='message'  style='display:$display;'>
			<div class='close_button' onClick="javascript:document.getElementById('$id').style.display = 'none';javascript:document.getElementById('background_$id').style.display = 'none'; $print">
				<img src="application/images/del.png" alt="Close">
			</div>
			$body
	</div>

	<div id='background_$id' class='background' style='display:$display;' onMouseOver="changeOpac(80,'background_$id');" onClick="javascript:document.getElementById('$id').style.display = 'none';javascript:document.getElementById('background_$id').style.display = 'none'; $print">
	<script>changeOpac(80,'background_$id');</script>
	</div>

END;
}


//old functions
function q_result($table,$condition,$colum='id'){
	$result = table_array($table,$condition,$colum);
	// dbg($result);
	return $result[0][$colum];
}

?>