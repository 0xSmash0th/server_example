<?php

require_once '../connect.php';

$colstr = "npc_id	group_id	npc_name	level	weight	officer_id	officer_grade	officer_level	command_total	s1_unit_id	s1_unit_qty	s2_unit_id	s2_unit_qty	s3_unit_id	s3_unit_qty	s4_unit_id	s4_unit_qty	s5_unit_id	s5_unit_qty	s6_unit_id	s6_unit_qty";
$headers = explode("\t", $colstr);

$dbinfo = queryparam_fetch('dbinfo');
if ( $dbinfo ) {
	//	elog("dbinfo: $dbinfo");

	$dbinfo = trim($dbinfo);
	$rows = explode("\r\n", $dbinfo);

	elog("got rows: " . count($rows));

	$npcs = array();
	$group_ids = array();
	foreach ($rows as $row) {
		$cols = explode("\t", $row);

		$count_cols = count($cols);
		$count_headers = count($headers);
		if ( $count_cols != $count_headers )
			render_error("count_cols,$count_cols != count_headers,$count_headers");

		$val = array();
		for ( $i = 0 ; $i < count($cols) ; $i++ ) {
			$val[$headers[$i]] = $cols[$i];
		}
		$group_id = $val['group_id'];

		if ( !isset($npcs[$group_id]) )
			$npcs[$group_id] = array();

		$npcs[$group_id][$val['npc_id']] = $val;
	}

	foreach( $npcs as $group_id => $group ) {
		// merge slots for each npc
		elog("processing group_id: $group_id");
		foreach ( $group as $npc_id => $npc ) {
			elog("processing npc_id: $npc_id");
			$slots = array();
			foreach ($npc as $k => $v) {
				// 				elog("key: $k");
				if ( strstr($k, '_unit_') ) {
					$slot_idx = $k[1];
					if ( strstr($k, 'unit_id') ) $slots[$slot_idx]['unit_id'] = $v;
					if ( strstr($k, 'unit_qty') ) $slots[$slot_idx]['unit_qty'] = $v;
				}
			}
			foreach ($npc as $k => $v) {
				if ( strstr($k, '_unit_') ) unset($npc[$k]);
			}
				
			elog("slots: " . pretty_json($slots));
			$npc['slots'] = $slots;
			$group[$npc_id] = $npc;
		}
		$npcs[$group_id] = $group;
	}
	// 	elog(pretty_json($npcs));
	// 	echo pretty_json($npcs);

	// transform to xml
	header("Content-Type:text/xml");
	$x = '<?xml version="1.0" encoding="utf-8"?>';
	
	$x = '';
	foreach( $npcs as $group_id => $group ) {
		$x .= "<npc_group group_id='$group_id'>";
		
		foreach ( $group as $npc_id => $npc ) {
			$attrs = array();
			foreach ( $npc as $k => $v ) {
				if ( !is_array($v) ) $attrs[] = "$k='$v'";
			}
			$attrlist = implode(' ', $attrs);
			$x .= "<npc $attrlist>";
			
			$x .= "<slots>";
			foreach ( $npc['slots'] as $slot_idx => $slot ) {
				$x .= sprintf("<slot slot_idx='$slot_idx' unit_id='%s' unit_qty='%s' />", $slot['unit_id'], $slot['unit_qty']);
			}
			$x .= "</slots>";
			
			$x .= "</npc>\n";
		}
		
		$x .= "</npc_group>";
	}
	
	$x = "<npc_info>$x</npc_info>";
	$x = "<?xml version='1.0' encoding='utf-8'?>\n\n$x";
	
	$x = str_replace('\'', '"', $x);
	echo $x;
	
	exit;
}

?>

<html>
<head>
<meta http-equiv="Content-Type" content="text/html;charset=UTF-8">
</head>
<body>

	<form name='form_dbinfo' action="" method=post>
		<textarea name='dbinfo' rows=20 cols=100>copy table from EXCEL and paste here</textarea>
		<br /> <input type=submit>
	</form>

</body>
</html>
