<?php
require_once '../connect.php';
require_once '../build/construction.php';
require_once '../general/general.php';
require_once '../battlefield/tile.php';

class user {
	public static function select($tb, $select_expr = null, $where_condition = null) {
		global $user_id;

		if ( $select_expr == null )
			$select_expr = "*";
		if ( $where_condition == null )
			$where_condition = " WHERE user_id = $user_id";
		else
			$where_condition = " WHERE $where_condition";

		$query = "SELECT $select_expr FROM user $where_condition /*BY_HELPER*/";
		assert_render($cols = ms_fetch_one($tb->query($query)));

		$json_keys = array('extra');

		foreach ( $json_keys as $json_key ) {
			if ( array_key_exists($json_key, $cols) && $cols[$json_key] ) {
				$js = @json_decode($cols[$json_key], true);
				if ( $js )
					$cols[$json_key] = $js;
				else
					$cols[$json_key] = null;
			}
		}

		if ( array_key_exists('password', $cols) )
			unset($cols['password']);

		return $cols;
	}
}

class auth {

	public static function get_serverinfo($tb) {
		global $TAG;

		if ( function_exists('apc_fetch') ) {
			if ( $serverinfo = apc_fetch("$TAG:serverinfo") )
				return $serverinfo;
		}

		$serverinfo = array();

		// get mysql session timezone
		$query = "SELECT TIME_FORMAT(TIMEDIFF(NOW(), UTC_TIMESTAMP), '%H:%i')";
		$tz_offset = ms_fetch_single_cell($tb->query($query));

		$tz_offset_sec = 0;
		if ( $tz_offset ) {
			$hm = explode(':', $tz_offset);
			if ( sizeof($hm) == 2 )
				$tz_offset_sec = 3600 * $hm[0] + 60 * $hm[1];

			if ( $tz_offset[0] != "-" )
				$tz_offset = "+" . $tz_offset;
		}

		$serverinfo['tz_offset'] = $tz_offset;
		$serverinfo['tz_offset_sec'] = $tz_offset_sec;

		global $VERSION;
		$serverinfo['version'] = $VERSION;

		if ( $val = fetch_from_cache('randoms') )
			$serverinfo['randoms'] = $val;
		else {
			global $RANDOM_INTEGER_NUMBERS, $RANDOM_FLOAT_NUMBERS;
			$query = "SELECT * FROM randoms ORDER BY random_id DESC LIMIT 1";
			$random = ms_fetch_one($tb->query($query));

			// get or make random numbers(integer and float) table
			if ( !$random ) {
				$integers = [];
				$floats = [];
				for ($i = 0 ; $i < $RANDOM_INTEGER_NUMBERS ; $i++)
					$integers[] = mt_rand();
				for ($i = 0 ; $i < $RANDOM_FLOAT_NUMBERS ; $i++)
					$floats[] = frand();
				$eintegers = json_encode($integers, JSON_NUMERIC_CHECK);
				$efloats = json_encode($floats, JSON_NUMERIC_CHECK);
				$query = "INSERT INTO randoms (integers, floats, created_at) VALUES ('$eintegers','$efloats', NOW())";
				assert_render($tb->query_with_affected($query, 1));

				$random = [];
				$random['integers'] = $integers;
				$random['floats'] = $floats;
			} else {
				$random['integers'] = @json_decode($random['integers'], false);
				$random['floats'] = @json_decode($random['floats'], false);
			}

			$random['integers_count'] = sizeof($random['integers']);
			$random['floats_count'] = sizeof($random['floats']);

			if ( $val = fetch_from_cache('randoms') )
				$serverinfo['randoms'] = $random;

			$serverinfo['randoms'] = $random;
			store_into_cache('randoms', $random);
		}

		// calculate checksums of assets
		$checksums = array();
		$files = @scandir('../xml');
		foreach ( $files as $fn ) {
			if ( !strstr($fn, '.xml') )
				continue;

			// 			elog("fn: $fn");
			$checksums["xml/$fn"] = md5_file("../xml/$fn");
		}
		$serverinfo['checksums'] = $checksums;

		elog("serverinfo: " . pretty_json($serverinfo));

		if ( function_exists('apc_store') ) {
			apc_store("$TAG:serverinfo", $serverinfo, CACHE_TTL);
		}

		return $serverinfo;
	}

	public static function get_constants($tb) {
		global $TAG;

		if ( function_exists('apc_fetch') ) {
			if ( $constants = apc_fetch("$TAG:constants:constants") )
				return $constants;
		}

		$constants = array();

		// TODO: get constants table from DB.config table
		foreach ( $GLOBALS as $k => $v) {
			if ( is_array($v) && strstr($k, '_TRAIN_TABLE_') )
				;
			else if ( !is_numeric($v) )
				continue;

			if ( strstr($k, 'COST_STAR') || strstr($k, 'COST_ACTIVITY') )
				$constants[$k] = $v;
			else if ( strstr($k, '_DEFAULT_') || strstr($k, 'COOLTIME') )
				$constants[$k] = $v;
			else if ( $k == 'TROOP_TRAIN_QTY_MAX' || $k == "")
				$constants[$k] = $v;

			if ( strpos($k, 'MYSQL_') === 0 || strpos($k, 'REDIS_') === 0 || strpos($k, 'SYSTEM_') === 0 )
				continue;

			$constants[$k] = $v; // TODO: filter me at pd
		}

		$GLEVELS = general::get_levels();
		$constants['GENERAL_LEVEL_MAX'] = $GLEVELS['max_level'];
		$OLEVELS = officer::get_levels();
		$constants['OFFICER_LEVEL_MAX'] = $OLEVELS['max_level'];

		if ( function_exists('apc_store') ) {
			apc_store("$TAG:constants:constants", $constants, CACHE_TTL);
		}

		return $constants;
	}

	public static function login($tb) {
		global $TAG, $user_id, $general_id, $country;
		global $USER_STATUS_ALL, $USER_STATUS_ACTIVE, $USER_STATUS_SUSPEND, $USER_STATUS_BAN;
		
		session_destroy();

		if ( !dev )
			assert_render(secure_connection(), "you are accessing with non-secure line");
		
		$username = queryparam_fetch('username');
		$password = queryparam_fetch('password');
		// TODO: update also user's device-uuid, user's current timezone, user's current locale
		$dev_type = queryparam_fetch_int('dev_type');
		$dev_uuid = queryparam_fetch('dev_uuid');

		if ( !($username && $password) )
			render_error(sprintf('not enough params [%d,%d]', isset($username), isset($password)), FCODE(30100));

		if (dev)
			elog("login: with username: $username");

		$seed = "eternal!!enter@#war1?";
		$epwd = sha1($seed . $password);
		if ( dev )
			$epwd = $password;

		$eusername = $tb->escape($username);
		$epassword = $tb->escape($epwd);

		$query = "SELECT * FROM user WHERE username = '$eusername' AND password = '$epassword';";
		assert_render($user = ms_fetch_one($tb->query($query)), "user select: $username"); // login failure

		unset($user['password']);

		//////////////////////////////////////////////////////
		// here, authentication was granted to user
		//////////////////////////////////////////////////////

		$user_id = $user['user_id'];

		// check maintenence
		$query = "SELECT * FROM config WHERE name LIKE 'maintenance_%'";
		$rows = ms_fetch_all($tb->query($query));
		$mts = [];
		foreach ($rows as $row)
			$mts[$row['name']] = $row['value'];

		if ( !empty($mts['maintenance_on']) && $mts['maintenance_on'] > 0 ) {
			$mtmsg = @$mts['maintenance_message'] ?: 'on maintenance';

			$query = "SELECT * FROM maintenance_allowed WHERE user_id = $user_id OR dev_uuid = '$dev_uuid'";
			$rows = ms_fetch_all($tb->query($query));
			if ( empty($rows) ) {
				$map['message'] = $mtmsg; // TODO: apply locale
				$map['fcode'] = 30201;

				render_error("on maintenance, but you are NOT allowed to access", $map);
			}
			elog("on maintenance, but you [username: $username] are allowed to access");
		}

		if ( $user['user_status'] > $USER_STATUS_ACTIVE ) {
			render_error("you are suspended or banned from the game", FCODE(30202));
		}

		$serverinfo = auth::get_serverinfo($tb);
		$constants = auth::get_constants($tb);

		$general = general::select($tb, null, "user_id = $user_id");
		assert_render($general, "general select: $username");
		assert_render($tb->end_txn(), "user/general failure: $username");

		$general_id = $general['general_id'];
		$query = "UPDATE user SET login_at = NOW() WHERE user_id = $user_id";
		assert_render($tb->query($query));

		// updating terms
		$terms = [];
		if ( dev && ($gold = queryparam_fetch_int('gold')) ) $terms['gold'] = $gold;
		if ( dev && ($honor = queryparam_fetch_int('honor')) ) $terms['honor'] = $honor;
		if ( dev && ($star = queryparam_fetch_int('star')) ) $terms['star'] = $star;
		if ( dev && ($activity = queryparam_fetch_int('activity')) ) $terms['activity_cur'] = $activity;
		if ( dev && ($tax_collectable_count = queryparam_fetch_int('tax_collectable_count')) ) $terms['tax_collectable_count'] = $tax_collectable_count;

		if ( sizeof($terms) > 0 ) {
			$pairs = join_terms($terms);
			$query = "UPDATE general SET $pairs WHERE user_id = $user_id";
			assert_render($tb->query($query));
		}

		// fetch $user again (for login_at)
		$query = "SELECT *, UNIX_TIMESTAMP(login_at) AS login_at_utc FROM user WHERE user_id = $user_id";
		assert_render($user = ms_fetch_one($tb->query($query)));
		unset($user['password']);

		assert_render($tb->end_txn(), "user/general failure: $username");

		make_session();

		$redis = conn_redis();

		// put into redis
		$login_at_utc = $user['login_at_utc'];
		unset($user['login_at_utc']);

		$context = [];
		$context['login_at_utc'] = $login_at_utc;
		$context['user_id'] = $user_id;
		$context['general_id'] = $general['general_id'];
		$context['dev_type'] = isset($user['dev_type']) ? $user['dev_type'] : 1;
		$context['dev_uuid'] = isset($user['dev_uuid']) ? $user['dev_uuid'] : 'dummy_dev_uuid';
		$context['session_id'] = session_id();
		store_into_redis("users:user_id=$user_id", $context);
		store_into_redis("users:user_id=$user_id:effects", $general['effects']);

		// put into session
		$_SESSION['user_id'] = $user['user_id'];
		$_SESSION['general_id'] = $general['general_id'];
		$_SESSION['dev_type'] = $context['dev_type'];
		$_SESSION['dev_uuid'] = $context['dev_uuid'];

		$_SESSION['user'] = $user;
		$_SESSION['username'] = $username;
		$_SESSION['country'] = $general['country'];

		$map['user'] = $user;
		$map['general'] = $general;
		$map['serverinfo'] = $serverinfo;
		$map['constants'] = $constants;

		if ( $user ) {
			$remote_addr = @$_SERVER['REMOTE_ADDR'] ?: null;;

			$rkey = "$TAG:oplog:users";
			$user['action_id'] = $redis->incr("$TAG:oplog:action_seq");
			$user['op'] = 'login';
			$user['password'] = $epassword;
			$user['remote_addr'] = $remote_addr;
			$redis->lPush($rkey, pretty_json($user));
		}

		if (dev)
			elog("============== logged in [$username] ==============");

		render_ok('success', $map);
	}

	public static function logout() {
		global $TAG;

		$username = session_GET('username');
		$gid = session_GET('general_id');

		unset($_SESSION['user_id']);
		unset($_SESSION['general_id']);

		if (dev)
			elog("============== logged out [$username, general_id: $gid] ==============");

		session_destroy();

		render_ok('logged out');
	}

	public static function register($tb) {
		global $TAG, $user_id, $general_id, $country;

		if ( !dev )
			assert_render(secure_connection(), "you are accessing with non-secure line");
		
		$username = queryparam_fetch('username');
		$password = queryparam_fetch('password');
		$country = queryparam_fetch_int('country', ALLIES);
		$email = queryparam_fetch_int('email');
		// TODO: get also device-uuid

		if ( !($username && $password && $country) )
			render_error(sprintf('not enough params [%d,%d,%d]', isset($username), isset($password), isset($country)), array('fcode' => 30100));

		// check input validation
		assert_render($country == ALLIES || $country == EMPIRE, "country: $country");
		assert_render(strlen($username) > 0, "strlen(username) > 0");
		assert_render(strlen($password) > 0, "strlen(password) > 0");
		// 		assert_render(strlen($email) > 0, "strlen(email) > 0"); // TODO: check me

		$query = sprintf("SELECT * FROM user WHERE username = '%s'", $tb->escape($username));

		$rs = $tb->query($query);
		if ( $rs && $rs->num_rows > 0  ) {
			$tb->end_txn();

			render_error("username[$username] already exists", array('fcode' => 30101));
		}

		$seed = "eternal!!enter@#war1?";
		$epwd = sha1($seed . $password);
		if ( dev )
			$epwd = $password;

		////////////////////////////////////////////////////////////////
		// CREATE NEW USER
		////////////////////////////////////////////////////////////////

		$query = sprintf("INSERT INTO user (username, password, created_at) VALUES ('%s', '%s', NOW());",
				$tb->escape($username), $tb->escape($epwd));
		$rs = $tb->query($query);
		assert_render($rs, "user was not inserted: $username");

		$user_id = $tb->mc()->insert_id;

		$terms = [];
		$terms['country'] = ms_quote($tb->escape($country));
		$terms['name'] = ms_quote($tb->escape($username));
		$terms['user_id'] = $user_id;
		$terms['general_id'] = $user_id;

		$keys = $vals = [];
		join_terms($terms, $keys, $vals);

		$query = "INSERT INTO general ($keys) VALUES ($vals)";
		assert_render($tb->query_with_affected($query, 1), "general was not inserted: $username");

		$general_id = $user_id;
		assert_render($general_id, "empty general_id");

		tile::default_tiles($tb);

		$_SESSION['country'] = $country;
		general::clear($tb);

		// pass info to op.user
		$query = "SELECT * FROM user WHERE user_id = $user_id";
		$user = ms_fetch_one($tb->query($query));
		if ( $user ) {
			$remote_addr = @$_SERVER['REMOTE_ADDR'] ?: null;;

			$redis = conn_redis();
			$rkey = "$TAG:oplog:users";
			$user['action_id'] = $redis->incr("$TAG:oplog:action_seq");
			$user['op'] = 'add';
			$user['remote_addr'] = $remote_addr;
			$redis->lPush($rkey, pretty_json($user));
		}

		////////////////////////////////////////////////////////////////
		////////////////////////////////////////////////////////////////

		assert_render($tb->end_txn(), "creating user");

		session_destroy();
		render_ok('registered'); // DO NOT login automatically on register
	}

	public static function acl_check($tokens) {
		global $TAG;

		$acl = session_GET('acl');

		if ( dev && empty($acl)) {
			$acl = queryparam_fetch('acl');
			elog("acl was set by dev mode: $acl");
		}

		if ( !empty($acl) ) {
			$acl_tokens = explode(',', $acl);
			foreach ( $tokens as $token ) {
				if ( !in_array($token, $acl_tokens) )
					return false;
			}
			return true;
		}
		return false;
	}
}

if ( strstr($_SERVER["REQUEST_URI"], basename(__FILE__)) ) {

	if ( login_check(false) )
		render_ok('already logged in as ' . $_SESSION['user_id'], array('fcode' => 30102));

	$user_id = null;
	$general_id = null;

	$ops = explode(',', $op = queryparam_fetch('op', 'register'));

	if ( sizeof(array_intersect_key(['register'], $ops)) ) {
		for ( $retried = 1 ; $retried <= $TXN_RETRY_MAX ; $retried++ ) {
			try {
				$tb = new TxnBlock();

				if ( in_array("register", $ops) ) auth::register($tb);

				break;
			} catch ( Exception $e ) {
				$emsg = $e->getMessage();
				if ( $emsg === 'Deadlock found' )
					elog("got [$emsg] exception, trying again... with [$retried / $TXN_RETRY_MAX]");
			}
		}
	}

	render_error("invalid:op:$op");
}

