<?php

require_once '../connect.php';

$colstr = "level	req_exp	activity_max	gold_capacity	honor_capacity	officer_hired_max	item_storage_slot_cap	building_count_max";
$headers = explode("\t", $colstr);

$dbinfo = queryparam_fetch('dbinfo');
if ( $dbinfo ) {
	//	elog("dbinfo: $dbinfo");

	$dbinfo = trim($dbinfo);
	$rows = explode("\r\n", $dbinfo);

	elog("got rows: " . count($rows));

	$glevels = array();
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

		$glevel_id = $val['level'];

		if ( !isset($glevels[$glevel_id]) )
			$glevels[$glevel_id] = array();

		$glevels[$glevel_id] = $val;
	}

	// no merging is required
	// 	foreach( $glevels as $group_id => $group ) {
	// 		// merge slots for each npc
	// 		elog("processing group_id: $group_id");
	// 		foreach ( $group as $npc_id => $npc ) {
	// 			elog("processing npc_id: $npc_id");
	// 			$slots = array();
	// 			foreach ($npc as $k => $v) {
	// 				// 				elog("key: $k");
	// 				if ( strstr($k, '_unit_') ) {
	// 					$slot_idx = $k[1];
	// 					if ( strstr($k, 'unit_id') ) $slots[$slot_idx]['unit_id'] = $v;
	// 					if ( strstr($k, 'unit_qty') ) $slots[$slot_idx]['unit_qty'] = $v;
	// 				}
	// 			}
	// 			foreach ($npc as $k => $v) {
	// 				if ( strstr($k, '_unit_') ) unset($npc[$k]);
	// 			}

	// 			elog("slots: " . pretty_json($slots));
	// 			$npc['slots'] = $slots;
	// 			$group[$npc_id] = $npc;
	// 		}
	// 		$glevels[$group_id] = $group;
	// 	}
	// 	elog(pretty_json($glevels));
	// 	echo pretty_json($glevels);

	// transform to xml
	header("Content-Type:text/xml");
	$x = '<?xml version="1.0" encoding="utf-8"?>';

	$x = '';
	foreach ( $glevels as $glevel_id => $glevel ) {
		$attrs = array();
		foreach ( $glevel as $k => $v ) {
			if ( !is_array($v) ) $attrs[] = "$k='$v'";
		}
		$attrlist = implode(' ', $attrs);
		$x .= "<level $attrlist>";
			
		$x .= "</level>\n";
	}

	$x = "<general_info><levels>$x</levels></general_info>";
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

	<p>HEADERS:<br/><?=$colstr?></p>
	<form name='form_dbinfo' action="" method=post>
		<textarea name='dbinfo' rows=20 cols=100>copy table from EXCEL and paste here</textarea>
		<br /> <input type=submit>
	</form>

</body>
</html>
