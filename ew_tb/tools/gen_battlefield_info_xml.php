<?php

require_once '../connect.php';

$tag = "tile";
$colstr = "tile_id	tile_name	tile_x	tile_y	tile_attr	desc_tile_attr	tile_type	desc_tile_type	tile_init_occupy	desc_tile_init_occupy	occupy_effect_type	occupy_effect_value	pvp_gold_reward_group_id	pvp_exp_reward_group_id	pvp_honor_reward_group_id	pvp_item_reward_group_id	pve_gold_reward_group_id	pve_exp_reward_group_id	pve_honor_reward_group_id	pve_item_reward_group_id	cost_activity_pvp	cost_activity_pve	recomm_level	pve_grade	npc_group_id_allies	npc_group_id_empire";
$headers = explode("\t", $colstr);

$dbinfo = queryparam_fetch('dbinfo');
if ( $dbinfo ) {
	//	elog("dbinfo: $dbinfo");

	$dbinfo = trim($dbinfo, $charlist = " \n\r\0\x0B");
	$input_rows = explode("\r\n", $dbinfo);

	elog("got input_rows: " . count($input_rows));
	
	$rows = array();
	foreach ($input_rows as $input_row) {
		$cols = explode("\t", $input_row);

		$count_cols = count($cols);
		$count_headers = count($headers);
		if ( $count_cols != $count_headers )
			render_error("count_cols,$count_cols != count_headers,$count_headers");

		$val = array();
		for ( $i = 0 ; $i < count($cols) ; $i++ ) {
			$val[$headers[$i]] = $cols[$i];
		}

		$tile_id = $val['tile_id'];

		if ( !isset($rows[$tile_id]) )
			$rows[$tile_id] = array();

		$rows[$tile_id] = $val;
	}

	// no merging is required
	// 	foreach( $tiles as $group_id => $group ) {
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
	// 		$tiles[$group_id] = $group;
	// 	}
	// 	elog(pretty_json($tiles));
	// 	echo pretty_json($tiles);

	// transform to xml
	header("Content-Type:text/xml");
	$x = '<?xml version="1.0" encoding="utf-8"?>';

	$x = '';
	foreach ( $rows as $rid => $row ) {
		$attrs = array();
		foreach ( $row as $k => $v ) {
			if ( !is_array($v) ) $attrs[] = "$k='$v'";
		}
		$attrlist = implode(' ', $attrs);
		$x .= "<$tag $attrlist>";
			
		$x .= "</$tag>\n";
	}

	$x = "<$tag"."_info>\n\n$x\n</$tag"."_info>\n";
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
