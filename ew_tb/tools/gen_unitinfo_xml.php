<?php

require_once '../connect.php';

$tag = 'unit';
$colstr = "id	cl_prefab	cl_name_index	cl_sprname	cl_facename	obtain_gold	obtain_EXP	obtain_honor	force	type	line_of_fire	blocking	weapon	armor	morale	range_min	range_max	offense_min	offense_max	health	turnSpeed	hitArea	type_major	cost_command	req_level	req_building	cost_money	cost_honor	cost_time	efUnitType	efShotName	efShotTime	efShotPosX	efShotPosY	efHitNameHuman	efHitNameTank	efHitTime	efHitPosX	efHitPosY	efRepeat	efShaking	efShakingAmount	efCamShakingShot	efCamShakingShotAmount	efCamShakingHit	efCamShakingHitAmount	efShakingDelay	efCamShakingHitDelay	efDeadName	sprbattle	sprshot	sprhit	sprshotCount	soundShot	soundHitHuman	soundHitTank";
$headers = explode("\t", $colstr);
$ignores = [];
// $headers = [];
// $headers[] = 'effect_id';
// $ignores[] = $headers[] = 'category';
// $ignores[] = $headers[] = 'target_type';
// $ignores[] = $headers[] = 'param_type';
// $ignores[] = $headers[] = 'description';
// // $effect_types = ['building', 'skill', 'badge', 'speciality', 'occupy', 'item'];
// $effect_types = ['building', 'skill', 'badge', 'speciality', 'occupy'];
// foreach ($effect_types as $k) {
// 	$ignores[] = $headers[] = "$k" . "_name";
// 	$headers[] = "$k" . "_id";
// 	$headers[] = "$k" . "_operator";
// 	$headers[] = "$k" . "_valuetype";
// }
$colstr = implode("\t", $headers);

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
			if ( !in_array($headers[$i], $ignores) )
				$val[$headers[$i]] = trim($cols[$i]);
		}

// 		$rid = $val["$tag" . "_id"];
		$rid = $val["id"];

		if ( !isset($rows[$rid]) )
			$rows[$rid] = array();

		$rows[$rid] = $val;
	}

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

	$x = "<$tag"."info>\n\n$x\n</$tag"."info>\n";
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

	<p>
		HEADERS:<br />
		<?=$colstr?>
	</p>
	<form name='form_dbinfo' action="" method=post>
		<textarea name='dbinfo' rows=20 cols=100>copy table from EXCEL and paste here</textarea>
		<br /> <input type=submit>
	</form>

</body>
</html>
