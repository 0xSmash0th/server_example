<?php

require_once '../connect.php';

$tag = 'quest';
$colstr = "quest_id	category	weight	req_quest_id	icon_name	desc	quest_type	complete_qty	complete_str	reward_type	reward_id	reward_qty";
$headers = explode("\t", $colstr);
$ignores = [];
// $headers = [];
// $headers[] = 'quest_id';
// $ignores[] = $headers[] = 'category';
// $ignores[] = $headers[] = 'target_type';
// $ignores[] = $headers[] = 'param_type';
// $ignores[] = $headers[] = 'description';

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

		$rid = $val["$tag" . "_id"];

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

	$x = "<$tag"."_info>\n<$tag"."s>\n$x</$tag"."s>\n</$tag"."_info>\n";
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
