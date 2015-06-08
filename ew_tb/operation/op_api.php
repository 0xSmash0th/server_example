<?php
require_once '../connect.php';

require_once '../PHPExcel/Classes/PHPExcel.php';

/*
 * Session string decoder
*/
class Session {
	public static function unserialize($session_data) {
		$method = ini_get("session.serialize_handler");
		switch ($method) {
			case "php":
				return self::unserialize_php($session_data);
				break;
			case "php_binary":
				return self::unserialize_phpbinary($session_data);
				break;
			default:
				throw new Exception("Unsupported session.serialize_handler: " . $method . ". Supported: php, php_binary");
		}
	}

	public static function unserialize_php($session_data) {
		$return_data = array();
		$offset = 0;
		while ($offset < strlen($session_data)) {
			if (!strstr(substr($session_data, $offset), "|")) {
				throw new Exception("invalid data, remaining: " . substr($session_data, $offset));
			}
			$pos = strpos($session_data, "|", $offset);
			$num = $pos - $offset;
			$varname = substr($session_data, $offset, $num);
			$offset += $num + 1;
			$data = unserialize(substr($session_data, $offset));
			$return_data[$varname] = $data;
			$offset += strlen(serialize($data));
		}
		return $return_data;
	}

	public static function unserialize_phpbinary($session_data) {
		$return_data = array();
		$offset = 0;
		while ($offset < strlen($session_data)) {
			$num = ord($session_data[$offset]);
			$offset += 1;
			$varname = substr($session_data, $offset, $num);
			$offset += $num;
			$data = unserialize(substr($session_data, $offset));
			$return_data[$varname] = $data;
			$offset += strlen(serialize($data));
		}
		return $return_data;
	}
}

class operator {

	public static function login($tb) {
		global $opuser_id;

		$username = queryparam_fetch('username');
		$password = queryparam_fetch('password');

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

		$query = "SELECT * FROM opuser WHERE username = '$eusername' AND password = '$epassword';";
		assert_render($opuser = ms_fetch_one($tb->query($query)), "opuser select: $username"); // login failure

		unset($opuser['password']);

		//////////////////////////////////////////////////////
		// here, authentication was granted to opuser
		//////////////////////////////////////////////////////

		assert_render($opuser['username'] != 'eventbot', "not allowed to login");

		$opuser_id = $opuser['opuser_id'];

		assert_render($tb->end_txn(), "opuser failure: $username");

		$query = "UPDATE opuser SET login_at = NOW() WHERE opuser_id = $opuser_id";
		assert_render($tb->query($query));

		operator::action_log($tb, 'login', ['opuser'=>$opuser]);

		assert_render($tb->end_txn(), "opuser failure: $username");

		// 		// put into redis
		// 		$context = [];
		// 		$context['login_at_utc'] = time();
		// 		$context['opuser_id'] = $opuser_id;
		// 		$context['general_id'] = $general['general_id'];
		// 		$context['dev_type'] = isset($opuser['dev_type']) ? $opuser['dev_type'] : 1;
		// 		$context['dev_uuid'] = isset($opuser['dev_uuid']) ? $opuser['dev_uuid'] : 'dummy_dev_uuid';
		// 		store_into_redis("opuser_id=$opuser_id", $context);
		// 		store_into_redis("opuser_id=$opuser_id:effects", $general['effects']);

		// put into session
		$_SESSION['opuser_id'] = $opuser['opuser_id'];
			
		$_SESSION['opuser'] = $opuser;
		$_SESSION['username'] = $username;
		$_SESSION['acl'] = $opuser['acl'];
			
		$map['opuser'] = $opuser;

		if (dev)
			elog("============== logged in [$username] ==============");

		render_ok('success', $map);
	}

	public static function logout() {
		$username = session_GET('username');

		unset($_SESSION['opuser_id']);

		if (dev)
			elog("============== logged out [$username] ==============");

		session_destroy();

		render_ok('logged out');
	}

	public static function register($tb) {
		global $opuser_id;

		assert_render(operator::acl_check(['master']), "insuffieicnt access token " . session_GET('acl'));

		$username = queryparam_fetch('username');
		$password = queryparam_fetch('password');

		if ( !($username && $password) )
			render_error(sprintf('not enough params [%d,%d]', isset($username), isset($password)), array('fcode' => 30100));

		// check input validation
		assert_render(sizeof($username) > 0, "sizeof(username) > 0");
		assert_render(sizeof($password) > 0, "sizeof(password) > 0");

		$query = sprintf("SELECT * FROM opuser WHERE username = '%s'", $tb->escape($username));

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
		// CREATE NEW opuser
		////////////////////////////////////////////////////////////////

		$query = sprintf("INSERT INTO opuser (username, password, created_at, acl) VALUES ('%s', '%s', NOW(), 'monitor');",
				$tb->escape($username), $tb->escape($epwd));
		$rs = $tb->query($query);
		assert_render($rs, "opuser was not inserted: $username");

		$opuser_id = $tb->mc()->insert_id;

		$query = "SELECT * FROM opuser WHERE opuser_id = $opuser_id";
		$opuser = ms_fetch_one($tb->query($query));
		////////////////////////////////////////////////////////////////
		////////////////////////////////////////////////////////////////
		operator::action_log($tb, 'user_add', ['opuser'=>$opuser]);

		assert_render($tb->end_txn(), "creating opuser");
		render_ok('registered'); // DO NOT login automatically on register
	}

	public static function acl_check($tokens) {
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

	public static function action_log($tb, $type, $detail = null) {
		global $TAG, $opuser_id;

		$terms = [];
		$terms['opuser_id'] = $opuser_id;
		if ( operator::acl_check(['event']) )
			$terms['opuser_id'] = "(SELECT opuser_id FROM opuser WHERE username = 'eventbot')";
		$terms['type'] = ms_quote($type);
		$terms['action_at'] = 'NOW()';
		if ( !empty($detail) )
			$terms['detail'] = ms_quote($tb->escape(pretty_json($detail)));
		if ( isset($detail['user_id']) )
			$terms['user_id'] = $detail['user_id']; // set also target id

		$keys = $vals = [];
		join_terms($terms, $keys, $vals);
		$query = "INSERT INTO actions_op ($keys) VALUES ($vals)";
		assert_render($tb->query($query), "query: $query");
	}

	public static function opuser_list($tb) {
		global $TAG, $opuser_id;

		$query = "SELECT * FROM opuser";
		$rows = ms_fetch_all($tb->query($query));

		$map['opuser_list'] = $rows;

		operator::action_log($tb, 'opuser_list');

		assert_render($tb->end_txn());

		render_ok('success', $map);
	}

	public static function opuser_mod($tb) {
		global $TAG, $opuser_id;

		assert_render(operator::acl_check(['master']), "insuffieicnt access token " . session_GET('acl'));

		$opuser_id = queryparam_fetch_int('opuser_id');
		$new_acl = queryparam_fetch('new_acl');
		assert_render($new_acl == 'operator' || $new_acl == 'monitor', "invalid:new_acl:$new_acl");

		$query = "SELECT * FROM opuser WHERE opuser_id = $opuser_id";
		$opuser = ms_fetch_one($tb->query($query));
		assert_render($opuser, "invalid:opuser_id:$opuser_id");

		if ( strpos($opuser['acl'], 'master') !== false )
			render_error("cannot modify from acl:master opuser to acl:$new_acl");

		$new_acl = $tb->escape($new_acl);
		$query = "UPDATE opuser SET acl = '$new_acl' WHERE opuser_id = $opuser_id";
		assert_render($tb->query($query));

		operator::action_log($tb, 'opuser_mod', ['opuser'=>$opuser, 'new_acl'=>$new_acl]);

		assert_render($tb->end_txn());

		render_ok('success');
	}

	public static function opuser_del($tb) {
		global $TAG, $opuser_id;

		assert_render(operator::acl_check(['master']), "insuffieicnt access token " . session_GET('acl'));

		$opuser_id = queryparam_fetch_int('opuser_id');

		$query = "SELECT * FROM opuser WHERE opuser_id = $opuser_id";
		$opuser = ms_fetch_one($tb->query($query));
		assert_render($opuser, "invalid:opuser_id:$opuser_id");

		if ( strpos($opuser['acl'], 'master') !== false )
			render_error("cannot delete acl:master opuser");

		$query = "DELETE FROM opuser WHERE opuser_id = $opuser_id";
		assert_render($tb->query($query));

		operator::action_log($tb, 'opuser_del', ['opuser'=>$opuser]);

		assert_render($tb->end_txn());

		render_ok('success');
	}

	public static function opaction_list($tb) {
		global $TAG, $opuser_id;

		$bgn_at = queryparam_fetch('bgn_at'); $bgn_at = !empty($bgn_at) ? $bgn_at : '1970-01-01';
		$end_at = queryparam_fetch('end_at'); $end_at = !empty($end_at) ? $end_at : '2038-01-01';

		$dt_bgn_at = DateTime::createFromFormat('Y-m-d', $bgn_at);
		$dt_end_at = DateTime::createFromFormat('Y-m-d', $end_at);
		assert_render($dt_bgn_at != FALSE, "invalid:bgn_at:$bgn_at");
		assert_render($dt_end_at != FALSE, "invalid:end_at:$end_at");

		$username = queryparam_fetch('username');

		$wheres = [];
		$wheres[] = "action_at >= '$bgn_at'";
		$wheres[] = "action_at < '$end_at'";
		if ( strlen($username) > 0 ) {
			$es = $tb->escape($username);
			$wheres = ["opuser.username = '$es'"];
		}

		$where = implode(' AND ', $wheres);

		$query = "SELECT opuser.username, actions_op.* FROM actions_op NATURAL JOIN opuser WHERE $where ORDER BY action_id DESC";
		assert_render($rs = $tb->query($query));

		if ( queryparam_fetch_int('download') > 0 ) {
			try {
				$sname = "opaction_list";
				$now = new DateTime();
				$date = $now->format("Ymd_His");
				$filename = "$sname"."_$date.xlsx";
				header("Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet");
				header("Content-Disposition: attachment;filename=\"$filename\"");
				header("Cache-Control: max-age=0");

				set_time_limit(60*10); // 10 min
				$px = new PHPExcel();
				$sheet = $px->setActiveSheetIndex(0);
				$sheet->setTitle($sname);
				$sheet->getPageSetup()->setRowsToRepeatAtTopByStartAndEnd();
				$sheet->freezePane('A2');

				$r = 1; $c = 0;
				foreach ($rs->fetch_fields() as $field)
					$sheet->setCellValueByColumnAndRow($c++, $r, $field->name);

				for ($r = 2, $c = 0 ; $row = $rs->fetch_array(MYSQLI_ASSOC) ; $r++, $c = 0) {
					foreach ($row as $k => $v)
						$sheet->setCellValueByColumnAndRow($c++, $r, $v);
				}

				operator::action_log($tb, $sname, ['download'=>true]);
				assert_render($tb->end_txn());

				$w = PHPExcel_IOFactory::createWriter($px, 'Excel2007');
				$w->save('php://output');

			} catch (Exception $e) {
				$msg = $e->getMessage();
				elog("PHPExcel exception: $msg on " . __METHOD__);
			}
			exit;
		}

		global $SYSTEM_FETCH_ALL_MAX;
		$rows = ms_fetch_all($rs, MYSQLI_ASSOC, $SYSTEM_FETCH_ALL_MAX);
		assert_render($rows, "search returned too many rows(than $SYSTEM_FETCH_ALL_MAX), try downloading");

		$map['opaction_list'] = $rows;

		operator::action_log($tb, 'opaction_list');
		assert_render($tb->end_txn());

		render_ok('success', $map);
	}

	private static function process_oplog_multiple($tb, $types = []) {
		global $TAG, $opuser_id;
		global $SYSTEM_OPLOG_BULK_PROCESS_QTY;

		if ( sizeof($types) == 0 )
			$types = ['item', 'officer', 'troop', 'event', 'combat', 'tile', 'construction', 'general', 'mail', 'quest', 'shop'];

		$redis = conn_redis();

		foreach ($types as $type) {
			$rkey = "$TAG:oplog:$type";
			$rpkey = "$rkey:processing";

			elog("processing oplog for type: $type ...");
			while ( true ) {
				// processing remaining elements
				if ( $redis->lLen($rpkey) > 0 ) {
					$querys = [];
					$oplogs = $redis->lGetRange($rpkey, 0, -1);
					if ( is_array($oplogs) ) {
						for ( $i = sizeof($oplogs)-1 ; $i >= 0 ; $i-- ) {
							$raw_oplog = $oplogs[$i];

							elog("will process oplog[$type]: " . $raw_oplog);

							$oplog = @json_decode($raw_oplog, true);
							$op = @$oplog['type'] ?: null;
							if ( $op ) {
								if ( in_array($op, ['rebuild_hotspots']) && empty($oplog['user_id']) )
									continue;
									
								$terms = [];
								$terms['action_id'] = $oplog['action_id'];
								$terms['user_id'] = $oplog['user_id'];
								$terms['type'] = ms_quote($tb->escape($op));
								$terms['action_at'] = "FROM_UNIXTIME(".$oplog['action_at_utc'].")";
									
								foreach( ['user_id', 'general_id', 'type'] as $unset ) {
									if ( isset($oplog[$unset]) )
										unset($oplog[$unset]);
								}
								if ( $op == 'buy_cash' )
									$terms['price'] = empty($oplog['price']) ? null : trim($oplog['price'], "$ ");
									
								$terms['detail'] = ms_quote($tb->escape(pretty_json($oplog)));
									
								$keys = $vals = [];
								$pairs = join_terms($terms, $keys, $vals);
								$query = "INSERT INTO actions_". $type ." ($keys) VALUE ($vals)";
								$querys[] = $query;
							}
						}
					} else {
						elog("lGetRange[$rpkey] returned non-array, skips");
					}
					assert_render($tb->multi_query($querys));
					$redis->del($rpkey);
				}

				// move to processing list
				for ($i = 0; $i < $SYSTEM_OPLOG_BULK_PROCESS_QTY
				&& ($raw = $redis->rpoplpush($rkey, $rpkey)) ; $i++ ) {
				}
				elog("[$type] populated to processing list: $i");
				if ($i == 0 )
					break;
			}
		}
	}

	private static function process_oplog_users($tb) {
		global $TAG, $opuser_id;
		global $SYSTEM_OPLOG_BULK_PROCESS_QTY;

		$redis = conn_redis();

		$type = "users";
		$rkey = "$TAG:oplog:$type";
		$rpkey = "$rkey:processing";

		elog("processing oplog for type: $type ...");
		while ( true ) {
			// processing remaining elements
			if ( $redis->lLen($rpkey) > 0 ) {
				$querys = [];
				$oplogs = $redis->lGetRange($rpkey, 0, -1);
				if ( is_array($oplogs) ) {
					for ( $i = sizeof($oplogs)-1 ; $i >= 0 ; $i-- ) {
						$raw_oplog = $oplogs[$i];

						elog("will process op logs: " . $raw_oplog);

						$oplog = @json_decode($raw_oplog, true);
						$op = @$oplog['op'] ?: null;
						$remote_addr = @$oplog['remote_addr'];
						unset($oplog['remote_addr']);
						if ( $op == 'add' || $op == 'login' ) {
							$terms = [];
							foreach($oplog as $k => $v) {
								if ( !$v || is_numeric($v) )
									$terms[$k] = $v;
								else
									$terms[$k] = ms_quote($tb->escape($v));
							}
							unset($terms['op']);
							unset($terms['action_id']);

							$keys = $vals = [];
							$pairs = join_terms($terms, $keys, $vals);
							$query = "INSERT INTO user ($keys) VALUE ($vals) ON DUPLICATE KEY UPDATE $pairs";
							$querys[] = $query;

							// action_user
							$detail = $oplog;
							$detail['remote_addr'] = $remote_addr;

							$terms = [];
							$terms['action_id'] = $oplog['action_id'];
							$terms['user_id'] = $oplog['user_id'];
							$terms['type'] = ms_quote($tb->escape($op));
							$terms['action_at'] = ms_quote($tb->escape($op == 'login' ? $oplog['login_at'] : $oplog['created_at']));
							$terms['detail'] = ms_quote($tb->escape(pretty_json($detail)));

							$keys = $vals = [];
							join_terms($terms, $keys, $vals);
							$query = "INSERT INTO actions_user ($keys) VALUE ($vals)";
							$querys[] = $query;
						} else if ( $op == 'payment_sum_add' ) {
							$user_id = @$oplog['user_id'];
							$mod = @$oplog['mod'];
							$price = empty($oplog['price']) ? null : trim($oplog['price'], "$ ");
							if ( $user_id > 0 && $price > 0 ) {
								$query = "UPDATE user SET payment_sum = payment_sum + $price WHERE user_id = $user_id";
								$querys[] = $query;

								// action_user
								$detail = $oplog;
								$detail['remote_addr'] = $remote_addr;
								$terms = [];
								$terms['action_id'] = $oplog['action_id'];
								$terms['user_id'] = $oplog['user_id'];
								$terms['type'] = ms_quote($tb->escape($op));
								$terms['action_at'] = "FROM_UNIXTIME(".$oplog['action_at_utc'].")";
								$terms['detail'] = ms_quote($tb->escape(pretty_json($detail)));

								$keys = $vals = [];
								join_terms($terms, $keys, $vals);
								$query = "INSERT INTO actions_user ($keys) VALUE ($vals)";
								$querys[] = $query;
							}
						}
					}
				} else {
					elog("lGetRange[$rpkey] returned non-array, skips");
				}
				assert_render($tb->multi_query($querys));
				$redis->del($rpkey);
			}

			// move to processing list
			for ($i = 0; $i < $SYSTEM_OPLOG_BULK_PROCESS_QTY
			&& ($raw = $redis->rpoplpush($rkey, $rpkey)) ; $i++ ) {
			}
			elog("[$type] populated to processing list: $i");
			if ($i == 0 )
				break;
		}
	}

	private static function process_oplog_chats($tb) {
		global $TAG, $opuser_id;
		global $SYSTEM_OPLOG_BULK_PROCESS_QTY;

		operator::process_oplog_users($tb);

		$redis = conn_redis();

		$type = "chats";
		$rkey = "$TAG:oplog:$type";
		$rpkey = "$rkey:processing";

		elog("processing oplog for type: $type ...");
		while ( true ) {
			// processing remaining elements
			if ( $redis->lLen($rpkey) > 0 ) {
				$querys = [];
				$oplogs = $redis->lGetRange($rpkey, 0, -1);
				if ( is_array($oplogs) ) {
					for ( $i = sizeof($oplogs)-1 ; $i >= 0 ; $i-- ) {
						$raw_oplog = $oplogs[$i];
							
						elog("will process op logs: " . $raw_oplog);

						$oplog = @json_decode($raw_oplog, true);
						$op = @$oplog['op'] ?: null;
						if ( $op == 'chat_force' || $op == 'chat_legion' ) {
							$terms = [];
							foreach($oplog as $k => $v) {
								if ( !$v || is_numeric($v) )
									$terms[$k] = $v;
								else
									$terms[$k] = ms_quote($tb->escape($v));
							}
							$terms['created_at'] = "FROM_UNIXTIME(".$terms['created_at_utc'].")";
							unset($terms['op']);
							unset($terms['action_id']);
							unset($terms['username']);
							unset($terms['created_at_utc']);

							$keys = $vals = [];
							$pairs = join_terms($terms, $keys, $vals);
							$query = "INSERT INTO $op ($keys) VALUE ($vals) ON DUPLICATE KEY UPDATE $pairs";
							$querys[] = $query;
						}
					}
				} else {
					elog("lGetRange[$rpkey] returned non-array, skips");
				}
				assert_render($tb->multi_query($querys));
				$redis->del($rpkey);
			}

			// move to processing list
			for ($i = 0; $i < $SYSTEM_OPLOG_BULK_PROCESS_QTY
			&& ($raw = $redis->rpoplpush($rkey, $rpkey)) ; $i++ ) {
			}
			elog("[$type] populated to processing list: $i");
			if ($i == 0 )
				break;
		}
	}

	private static function init_user($tb) {
		global $TAG, $opuser_id;

		elog("running initialization on: user");

		$ltb = new TxnBlock();
		$query = "SELECT * FROM user";
		$rows = ms_fetch_all($ltb->query($query));
		assert_render($ltb->end_txn());

		$querys = [];
		foreach ($rows as &$row) {
			$terms = [];
			foreach($row as $k => $v) {
				if ( !$v || is_numeric($v) )
					$terms[$k] = $v;
				else
					$terms[$k] = ms_quote($tb->escape($v));
			}
			$keys = $vals = [];
			$pairs = join_terms($terms, $keys, $vals);
			$query = "INSERT INTO user ($keys) VALUE ($vals) ON DUPLICATE KEY UPDATE $pairs";
			$querys[] = $query;

			unset($row['password']);
		}
		assert_render($tb->multi_query($querys));

		return $rows;
	}

	private static function init_chat($tb) {
		global $TAG, $opuser_id;

		elog("running initialization on: chat");

		$query = "SELECT COUNT(*) FROM user";
		$count = ms_fetch_single_cell($tb->query($query));
		if ( empty($count) )
			operator::init_user($tb);

		$ltb = new TxnBlock();
		$query = "SELECT * FROM chat_force";
		$frows = ms_fetch_all($ltb->query($query));

		$query = "SELECT * FROM chat_legion";
		$lrows = ms_fetch_all($ltb->query($query));
		assert_render($ltb->end_txn());

		$querys = [];
		elog('inserting chat_force ...');
		foreach ($frows as &$row) {
			$terms = [];
			foreach($row as $k => $v) {
				if ( !$v || is_numeric($v) )
					$terms[$k] = $v;
				else
					$terms[$k] = ms_quote($tb->escape($v));
			}
			$keys = $vals = [];
			$pairs = join_terms($terms, $keys, $vals);
			$query = "INSERT INTO chat_force ($keys) VALUE ($vals) ON DUPLICATE KEY UPDATE $pairs";
			$querys[] = $query;
		}

		elog('inserting chat_legion ...');
		foreach ($lrows as &$row) {
			$terms = [];
			foreach($row as $k => $v) {
				if ( !$v || is_numeric($v) )
					$terms[$k] = $v;
				else
					$terms[$k] = ms_quote($tb->escape($v));
			}
			$keys = $vals = [];
			join_terms($terms, $keys, $vals);
			$pairs = join_terms($terms, $keys, $vals);
			$query = "INSERT INTO chat_legion ($keys) VALUE ($vals) ON DUPLICATE KEY UPDATE $pairs";
			$querys[] = $query;
		}
		if ( sizeof($querys) > 0 )
			assert_render($tb->multi_query($querys));

		return ['chat_force'=>$frows, 'chat_legion'=>$lrows];
	}

	public static function chat_list($tb) {
		global $TAG, $opuser_id;

		$recv_force = queryparam_fetch_int('recv_force', 3);
		$legion_id = queryparam_fetch_int('legion_id');
		$username = queryparam_fetch('username');
		$body = queryparam_fetch('body');

		assert_render(1 <= $recv_force && $recv_force <= 3, "invalid:recv_force:$recv_force");

		$bgn_at = queryparam_fetch('bgn_at');
		$bgn_at = !empty($bgn_at) ? $bgn_at : '1970-01-01';
		$end_at = queryparam_fetch('end_at');
		$end_at = !empty($end_at) ? $end_at : '2038-01-01';

		$dt_bgn_at = DateTime::createFromFormat('Y-m-d', $bgn_at);
		$dt_end_at = DateTime::createFromFormat('Y-m-d', $end_at);
		assert_render($dt_bgn_at != FALSE, "invalid:bgn_at:$bgn_at");
		assert_render($dt_end_at != FALSE, "invalid:end_at:$end_at");

		$detail = [];
		$detail['recv_force'] = $recv_force;
		$detail['legion_id'] = $legion_id;

		operator::process_oplog_chats($tb);

		if ( $recv_force > 0 ) {
			$wheres = [];
			$wheres[] = "recv_force = $recv_force";
			$wheres[] = "c.created_at >= '$bgn_at'";
			$wheres[] = "c.created_at < '$end_at'";
			if ( $username && strlen($username) > 0 )
				$wheres[] = "username = " . ms_quote($tb->escape($username));
			if ( $body && strlen($body) )
				$wheres[] = "body LIKE '%" . $tb->escape($body) . "%'";

			$where = implode(' AND ', $wheres);

			$query = "SELECT COUNT(*) FROM chat_force";
			$count = ms_fetch_single_cell($tb->query($query));
			if ( !($count > 0) )
				$chats = operator::init_chat($tb);

			$query = "SELECT c.*, u.user_id, u.username, u.user_status FROM chat_force AS c JOIN user AS u ON c.general_id = u.user_id "
					."WHERE $where ORDER BY created_at DESC";
			assert_render($rs = $tb->query($query));
		}
		else if ( $legion_id > 0 ) {
			// TODO: implement me

			$query = "SELECT COUNT(*) FROM chat_legion";
			$count = ms_fetch_single_cell($tb->query($query));
			if ( !($count > 0) )
				$chats = operator::init_chat($tb);
		}

		if ( queryparam_fetch_int('download') > 0 ) {
			try {
				$sname = "chat_list";
				$now = new DateTime();
				$date = $now->format("Ymd_His");
				$filename = "$sname"."_$date.xlsx";
				header("Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet");
				header("Content-Disposition: attachment;filename=\"$filename\"");
				header("Cache-Control: max-age=0");

				set_time_limit(60*10); // 10 min
				$px = new PHPExcel();
				$sheet = $px->setActiveSheetIndex(0);
				$sheet->setTitle($sname);
				$sheet->getPageSetup()->setRowsToRepeatAtTopByStartAndEnd();
				$sheet->freezePane('A2');

				$r = 1; $c = 0;
				foreach ($rs->fetch_fields() as $field)
					$sheet->setCellValueByColumnAndRow($c++, $r, $field->name);

				for ($r = 2, $c = 0 ; $row = $rs->fetch_array(MYSQLI_ASSOC) ; $r++, $c = 0) {
					foreach ($row as $k => $v)
						$sheet->setCellValueByColumnAndRow($c++, $r, $v);
				}

				operator::action_log($tb, $sname, ['download'=>true]);
				assert_render($tb->end_txn());

				$w = PHPExcel_IOFactory::createWriter($px, 'Excel2007');
				$w->save('php://output');

			} catch (Exception $e) {
				$msg = $e->getMessage();
				elog("PHPExcel exception: $msg on " . __METHOD__);
			}
			exit;
		}

		global $SYSTEM_FETCH_ALL_MAX;
		$rows = ms_fetch_all($rs, MYSQLI_ASSOC, $SYSTEM_FETCH_ALL_MAX);
		assert_render($rows, "search returned too many rows(than $SYSTEM_FETCH_ALL_MAX), try downloading");

		$map['chat_list'] = $rows;

		operator::action_log($tb, 'chat_list', $detail);
		assert_render($tb->end_txn());

		render_ok('success', $map);
	}

	public static function chat_badword_list($tb, $do_action_log = true) {
		global $TAG, $opuser_id;

		// get list from live
		$ltb = new TxnBlock();
		$query = "SELECT * FROM chat_badword";
		$rows = ms_fetch_all($ltb->query($query));
		assert_render($ltb->end_txn());

		// build $opuser_ids => username map
		$opuser_map = [];
		foreach ($rows as $row) {
			if ( !empty($row['opuser_id']) && empty($opuser_map[$row['opuser_id']]) )
				$opuser_map[$row['opuser_id']] = null;
		}

		$opuser_ids = array_keys($opuser_map);
		$ids = implode(',', $opuser_ids);

		$query = "SELECT opuser_id, username FROM opuser";
		$opusers = ms_fetch_all($tb->query($query));

		foreach($opusers as $opuser)
			$opuser_map[$opuser['opuser_id']] = $opuser['username'];

		foreach ($rows as &$row) {
			if ( !empty($row['opuser_id']) )
				$row['username'] = 	$opuser_map[$row['opuser_id']];
			else
				$row['username'] = null;
		}

		$map['chat_badword_list'] = $rows;

		// 		$redis = conn_redis();
		// 		$rkey = "$TAG:chat:badwords";
		// 		$bwlen = $redis->scard($rkey);
		// 		if ( $bwlen != sizeof($rows) ) {
		// 			elog("element count on redis:$rkey differs from db");
		// 			foreach ($rows as $row)
		// 				$redis->sAdd($rkey, $row['badword']);
		// 		}

		if ( $do_action_log )
			operator::action_log($tb, 'chat_badword_list');

		assert_render($tb->end_txn());

		render_ok('success', $map);
	}

	public static function chat_badword_add($tb) {
		global $TAG, $opuser_id;

		$badword = queryparam_fetch('badword', '');

		$badword = trim($badword);

		assert_render(strlen($badword) > 0);

		$es = $tb->escape($badword);

		// get list from live
		$ltb = new TxnBlock();
		$query = "SELECT * FROM chat_badword WHERE badword = '$es'";
		$row = ms_fetch_one($ltb->query($query));
		assert_render(!$row, "invalid:badword:already exists:$badword");

		$query = "INSERT INTO chat_badword (opuser_id, badword) VALUES ($opuser_id, '$es')";
		assert_render($ltb->query($query));
		assert_render($ltb->end_txn());

		// invalidate badword on redis
		delete_at_redis('constants:badwords');

		$detail = ['badword'=>$badword];

		operator::action_log($tb, 'chat_badword_add', $detail);

		operator::chat_badword_list($tb, false);

		assert_render($tb->end_txn());

		render_ok('success');
	}

	public static function chat_badword_del($tb) {
		global $TAG, $opuser_id;

		$bw_id = queryparam_fetch_int('bw_id');

		// get list from live
		$ltb = new TxnBlock();
		$query = "SELECT * FROM chat_badword WHERE bw_id = $bw_id";
		$row = ms_fetch_one($ltb->query($query));
		assert_render($row, "invalid:bw_id:$bw_id");

		$query = "DELETE FROM chat_badword WHERE bw_id = $bw_id";
		assert_render($ltb->query($query));
		assert_render($ltb->end_txn());

		delete_at_redis('constants:badwords');

		$detail = $row;

		operator::action_log($tb, 'chat_badword_del', $detail);

		operator::chat_badword_list($tb, false);

		assert_render($tb->end_txn());

		render_ok('success');
	}

	public static function user_list($tb) {
		global $TAG, $opuser_id;
		global $MARKET_TYPE_MAP;
		global $USER_LINE_ALL, $USER_LINE_OFF, $USER_LINE_ON;
		global $USER_STATUS_ALL, $USER_STATUS_ACTIVE, $USER_STATUS_SUSPEND, $USER_STATUS_BAN;
		global $USER_ACCESS_ALL, $USER_ACCESS_JOIN, $USER_ACCESS_LOGIN;

		$market_type = queryparam_fetch_int('market_type');
		assert_render(in_array($market_type, array_keys($MARKET_TYPE_MAP)), "invalid:market_type:$market_type");

		$line_type = queryparam_fetch_int('line_type');
		assert_render(in_array($line_type, [$USER_LINE_ALL, $USER_LINE_ON, $USER_LINE_OFF]), "invalid:line_type:$line_type");

		$status_type = queryparam_fetch_int('status_type');
		assert_render(in_array($status_type, [$USER_STATUS_ALL, $USER_STATUS_ACTIVE, $USER_STATUS_SUSPEND, $USER_STATUS_BAN]), "invalid:status_type:$status_type");

		$access_type = queryparam_fetch_int('access_type');
		assert_render(in_array($access_type, [$USER_ACCESS_ALL, $USER_ACCESS_JOIN, $USER_ACCESS_LOGIN]), "invalid:access_type:$access_type");

		$username = queryparam_fetch('username');
		$payment_min = max(0, queryparam_fetch_int('payment_min', 0));
		$payment_max = queryparam_fetch_int('payment_max', PHP_INT_MAX);

		$date_min = '1970-01-01';
		$date_max = '2038-01-01';
		$bgn_at = queryparam_fetch('bgn_at'); $bgn_at = !empty($bgn_at) ? $bgn_at : '1970-01-01';
		$end_at = queryparam_fetch('end_at'); $end_at = !empty($end_at) ? $end_at : '2038-01-01';

		$dt_bgn_at = DateTime::createFromFormat('Y-m-d', $bgn_at);
		$dt_end_at = DateTime::createFromFormat('Y-m-d', $end_at);
		assert_render($dt_bgn_at != FALSE, "invalid:bgn_at:$bgn_at");
		assert_render($dt_end_at != FALSE, "invalid:end_at:$end_at");

		operator::process_oplog_users($tb);
		operator::process_oplog_multiple($tb);

		$query = "SELECT COUNT(*) FROM user";
		$count = ms_fetch_single_cell($tb->query($query));
		if ( empty($count) )
			$rows = operator::init_user($tb);

		$online_count = 0;
		$concurrent_users = operator::probe_concurrent_users($tb);
		foreach ( $concurrent_users as $market_type_name => $user_ids ) {
			$online_count += sizeof($user_ids);
		}

		$map = [];
		// make user_summary
		$query = "SELECT ";
		$query .= "(SELECT COUNT(*) FROM user) AS `all`, ";
		$query .= "$online_count AS `online`, ";
		$query .= "(SELECT COUNT(*)-$online_count FROM user) AS `offline`, ";
		foreach ( $MARKET_TYPE_MAP as $type => $name ) {
			if ( !is_numeric($type) ) continue;
			$query .= "(SELECT COUNT(*) FROM user WHERE market_type = $type) AS `$name`, ";
		}
		$query .= "(SELECT COUNT(*) FROM user WHERE market_type >= 0 AND user_status = $USER_STATUS_ACTIVE) AS `active`, ";
		$query .= "(SELECT COUNT(*) FROM user WHERE market_type >= 0 AND user_status = $USER_STATUS_SUSPEND) AS `suspend`, ";
		$query .= "(SELECT COUNT(*) FROM user WHERE market_type >= 0 AND user_status = $USER_STATUS_BAN) AS `ban` ";
		$user_summary = ms_fetch_one($tb->query($query));

		$map['user_summary'] = $user_summary;

		// make search results
		$wheres = [];
		$wheres[] = "market_type = $market_type";
		if ( $status_type == $USER_STATUS_ALL )
			$wheres[] = "user_status >= 0";
		else
			$wheres[] = "user_status = $status_type";
		$wheres[] = "payment_sum >= $payment_min";
		$wheres[] = "payment_sum <= $payment_max";
		if ( $access_type == $USER_ACCESS_ALL ) {
			$wheres[] = "created_at >= '$date_min'";
			$wheres[] = "created_at < '$date_max'";
			$wheres[] = "login_at >= '$date_min'";
			$wheres[] = "login_at < '$date_max'";
		} else if ( $access_type == $USER_ACCESS_JOIN ) {
			$wheres[] = "created_at >= '$bgn_at'";
			$wheres[] = "created_at < '$end_at'";
			$wheres[] = "login_at >= '$date_min'";
			$wheres[] = "login_at < '$date_max'";
		} else if ( $access_type == $USER_ACCESS_LOGIN ) {
			$wheres[] = "created_at >= '$date_min'";
			$wheres[] = "created_at < '$date_max'";
			$wheres[] = "login_at >= '$bgn_at'";
			$wheres[] = "login_at < '$end_at'";
		}

		if ( $line_type == $USER_LINE_OFF || $line_type == $USER_LINE_ON ) {
			$online_user_ids = [];
			foreach ( $concurrent_users as $market_type_name => $user_ids ) {
				$online_user_ids = array_merge($online_user_ids, $user_ids);
			}
			$ids = implode(',', $online_user_ids);

			if ( $line_type == $USER_LINE_ON )
				$wheres[] = sizeof($online_user_ids) > 0 ? "user_id IN ($ids)" : "1 = 2";
			else if ( sizeof($online_user_ids) > 0 )
				$wheres[] = "user_id NOT IN ($ids)";
		}

		if ( strlen($username) > 0 ) {
			$es = $tb->escape($username);
			$wheres = ["username = '$es'"];
		}

		$where = implode(' AND ', $wheres);
		$query = "SELECT user_id, username, market_type, user_status, payment_sum, created_at, login_at FROM user WHERE $where";
		assert_render($rs = $tb->query($query));

		if ( queryparam_fetch_int('download') > 0 ) {
			try {

				$sname = "user_list";
				$now = new DateTime();
				$date = $now->format("Ymd_His");
				$filename = "$sname"."_$date.xlsx";
				// 				$filename = "$sname".".xlsx";
				header("Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet");
				header("Content-Disposition: attachment;filename=\"$filename\"");
				header("Cache-Control: max-age=0");

				set_time_limit(60*10); // 10 min
				$px = new PHPExcel();
				$sheet = $px->setActiveSheetIndex(0);
				$sheet->setTitle('user_summary');
				$sheet->getPageSetup()->setRowsToRepeatAtTopByStartAndEnd();
				$sheet->freezePane('A2');
				$r = 1; $c = 0;
				foreach ($user_summary as $k => $v ) {
					$sheet->setCellValueByColumnAndRow($c, 1, $k);
					$sheet->setCellValueByColumnAndRow($c++, 2, $v);
				}

				$px->createSheet(1);
				$sheet = $px->setActiveSheetIndex(1);
				$sheet->setTitle('user_list');
				$sheet->getPageSetup()->setRowsToRepeatAtTopByStartAndEnd();
				$sheet->freezePane('A2');

				$fields = [];
				foreach ($rs->fetch_fields() as $field)
					$fields[] = $field->name;
				$fields[] = 'line';

				$r = 1; $c = 0;
				foreach ($fields as $field)
					$sheet->setCellValueByColumnAndRow($c++, $r, $field);

				for ($r = 2, $c = 0 ; $row = $rs->fetch_array(MYSQLI_ASSOC) ; $r++, $c = 0) {
					foreach ($row as $k => $v)
						$sheet->setCellValueByColumnAndRow($c++, $r, $v);

					$type = $MARKET_TYPE_MAP[$row['market_type']];
					if ( in_array($row['user_id'], $concurrent_users[$type]) )
						$sheet->setCellValueByColumnAndRow($c++, $r, $USER_LINE_ON);
					else
						$sheet->setCellValueByColumnAndRow($c++, $r, $USER_LINE_OFF);
				}

				operator::action_log($tb, 'user_list', ['download'=>true]);
				assert_render($tb->end_txn());

				$w = PHPExcel_IOFactory::createWriter($px, 'Excel2007');
				$w->save('php://output');

			} catch (Exception $e) {
				$msg = $e->getMessage();
				elog("PHPExcel exception: $msg on " . __METHOD__);
			}

			exit;
		}

		global $SYSTEM_FETCH_ALL_MAX;
		$rows = ms_fetch_all($rs, MYSQLI_ASSOC, $SYSTEM_FETCH_ALL_MAX);
		assert_render($rows, "search returned too many rows(than $SYSTEM_FETCH_ALL_MAX), try downloading");

		foreach ($rows as &$row) {
			$row['line'] = $USER_LINE_OFF;
			$type = $MARKET_TYPE_MAP[$row['market_type']];
			if ( in_array($row['user_id'], $concurrent_users[$type]) ) {
				$row['line'] = $USER_LINE_ON;
			}
		}

		$map['user_list'] = $rows;

		operator::action_log($tb, 'user_list');
		assert_render($tb->end_txn());

		render_ok('success', $map);
	}

	public static function user_penalty_list($tb) {
		global $TAG, $opuser_id;

		$user_id = queryparam_fetch_int('user_id');

		$query = "SELECT * FROM actions_penalty WHERE user_id = $user_id ORDER BY action_id DESC";
		$rows = ms_fetch_all($tb->query($query));

		$map['user_penalty_list'] = $rows;

		operator::action_log($tb, 'user_penalty_list');

		assert_render($tb->end_txn());

		render_ok('success', $map);
	}

	public static function user_penalty_mod($tb) {
		global $TAG, $opuser_id;

		$user_id = queryparam_fetch_int('user_id');
		$new_status = queryparam_fetch_int('new_status');
		$reason = queryparam_fetch_int('reason', 0);
		$status_date_from = queryparam_fetch('status_date_from');
		$status_date_to = queryparam_fetch('status_date_to');

		$query = "SELECT * FROM user WHERE user_id = $user_id";
		assert_render($user = ms_fetch_one($tb->query($query)));

		// 		assert_render($user['user_status'] != $new_status, "old and new status are equal: $new_status");

		// update live server's user table
		$ltb = new TxnBlock();
		$query = "UPDATE user SET user_status = $new_status WHERE user_id = $user_id";
		assert_render($ltb->query($query));
		assert_render($ltb->end_txn());

		$query = "UPDATE user SET user_status = $new_status WHERE user_id = $user_id";
		assert_render($tb->query($query));

		$terms = [];
		$terms['user_id'] = $user_id;
		$terms['old_status'] = $user['user_status'];
		$terms['new_status'] = $new_status;
		$terms['reason'] = $reason;
		$terms['action_at'] = "NOW()";

		$keys = $vals = [];
		join_terms($terms, $keys, $vals);

		$query = "INSERT INTO actions_penalty ($keys) VALUES ($vals)";
		assert_render($tb->query($query));

		$detail = $terms;
		$detail['status_date_from'] = $status_date_from;
		$detail['status_date_to'] = $status_date_to;
		operator::action_log($tb, 'user_penalty_mod', $detail);

		assert_render($tb->end_txn());

		render_ok('success');
	}

	public static function maintenance_set($tb) {
		global $TAG, $opuser_id;

		$active = queryparam_fetch_int('active');

		assert_render($active, "invalid:active");

		$active = $active > 0 ? 1 : 0;

		$detail = [];
		$detail['active'] = $active;

		$ltb = new TxnBlock(); // aceesss to live
		$query = "REPLACE INTO config (name, value) VALUES ('maintenance_on', '$active')";
		assert_render($ltb->query($query));
		assert_render($ltb->end_txn());

		operator::action_log($tb, 'maintenance_set', $detail);

		assert_render($tb->end_txn());

		render_ok('success');
	}

	public static function maintenance_message_set($tb) {
		global $TAG, $opuser_id;

		$mtmsg = queryparam_fetch('mtmsg', '');
		$mtmsg = trim($mtmsg);
		assert_render($mtmsg && strlen($mtmsg) > 0);

		$ltb = new TxnBlock(); // aceesss to live
		$ejs = $ltb->escape($mtmsg);
		$query = "REPLACE INTO config (name, value) VALUES ('maintenance_message', '$ejs')";
		assert_render($ltb->query($query));
		assert_render($ltb->end_txn());

		$detail['new_mtmsg'] = $mtmsg;
		operator::action_log($tb, 'maintenance_message_set', $detail);

		assert_render($tb->end_txn());

		render_ok('success');
	}

	public static function maintenance_allowed_list($tb, $do_action_log = true) {
		global $TAG, $opuser_id;

		$map = [];

		$map['maintenance_on'] = 0;
		$map['maintenance_message'] = '';

		$ltb = new TxnBlock(); // aceesss to live

		$query = "SELECT m.mt_id, m.user_id, u.username FROM maintenance_allowed AS m JOIN user AS u ON m.user_id = u.user_id WHERE m.user_id IS NOT NULL";
		$rows = ms_fetch_all($ltb->query($query));
		$map['maintenance_allowed_list_user_id'] = $rows;

		$query = "SELECT mt_id, dev_uuid FROM maintenance_allowed WHERE dev_uuid IS NOT NULL";
		$rows = ms_fetch_all($ltb->query($query));
		$map['maintenance_allowed_list_dev_uuid'] = $rows;

		// also get previous mt status/message
		$query = "SELECT * FROM config WHERE name LIKE 'maintenance_%'";
		$mts = ms_fetch_all($ltb->query($query));

		foreach ($mts as $mt) {
			if ( $mt['name'] == 'maintenance_on' )
				$map['maintenance_on'] = @$mt['value'] > 0 ? 1 : 0;
			else if ( $mt['name'] == 'maintenance_message' )
				$map['maintenance_message'] = @$mt['value'];
		}

		assert_render($ltb->end_txn());

		if ( $do_action_log )
			operator::action_log($tb, 'maintenance_allowed_list');

		assert_render($tb->end_txn());

		render_ok('success', $map);
	}

	public static function maintenance_allowed_add($tb) {
		global $TAG, $opuser_id;

		$username = queryparam_fetch('username');
		$dev_uuid = queryparam_fetch('dev_uuid');

		$ltb = new TxnBlock(); // aceesss to live

		$user_id = null;
		if ( $username ) {
			$es = $tb->escape($username);
			$query = "SELECT * FROM user WHERE username = '$es'";
			assert_render($user = ms_fetch_one($ltb->query($query)), "invalid:username:not found:$username");
			$user_id = $user['user_id'];
		}
		if ( $dev_uuid ) {
			assert_render(strlen($dev_uuid) > 0, "invalid:dev_uuid");
		}

		assert_render($user_id || $dev_uuid, "invalid username or dev_uuid");

		if ( $user_id ) {
			$query = "SELECT * FROM maintenance_allowed WHERE user_id = $user_id";
			$row = ms_fetch_one($ltb->query($query));
			assert_render(!$row, "user already in allowed list");

			$query = "INSERT INTO maintenance_allowed (user_id) VALUES ($user_id)";
			assert_render($ltb->query($query));

			$detail = ['user_id'=>$user_id, 'username'=>$username];
		}
		else {
			$es = $tb->escape($dev_uuid);
			$query = "SELECT * FROM maintenance_allowed WHERE dev_uuid = '$es'";
			$row = ms_fetch_one($ltb->query($query));
			assert_render(!$row, "dev_uuid already in allowed list");

			$query = "INSERT INTO maintenance_allowed (dev_uuid) VALUES ('$es')";
			assert_render($ltb->query($query));

			$detail = ['dev_uuid'=>$dev_uuid];
		}

		assert_render($ltb->end_txn());

		operator::action_log($tb, 'maintenance_allowed_add', $detail);

		operator::maintenance_allowed_list($tb, false);

		assert_render($tb->end_txn());

		render_ok('success');
	}

	public static function maintenance_allowed_del($tb) {
		global $TAG, $opuser_id;

		$mt_id = queryparam_fetch_int('mt_id');

		$ltb = new TxnBlock(); // aceesss to live

		$query = "SELECT * FROM maintenance_allowed WHERE mt_id = $mt_id";
		$row = ms_fetch_one($ltb->query($query));
		assert_render($row, "invalid:mt_id:$mt_id");

		$query = "DELETE FROM maintenance_allowed WHERE mt_id = $mt_id";
		assert_render($ltb->query($query));

		$detail = $row;

		assert_render($ltb->end_txn());

		operator::action_log($tb, 'maintenance_allowed_del', $detail);

		operator::maintenance_allowed_list($tb, false);

		assert_render($tb->end_txn());

		render_ok('success');
	}
	public static function mailq_list($tb, $do_action_log = true) {
		global $TAG, $opuser_id;
		global $MAIL_RECV_TYPE_USER, $MAIL_RECV_TYPE_LEGION, $MAIL_RECV_TYPE_PUBLIC;

		// get list from live
		$all = [];

		$ltb = new TxnBlock();
		$query = "SELECT * FROM mail_repo_user";
		$rows = ms_fetch_all($ltb->query($query));
		foreach ($rows as &$row) {
			$row['recv_type'] = $MAIL_RECV_TYPE_USER;
			$row['mailq_id'] = $row['repo_id'];
			$all[] = $row;
		}

		$query = "SELECT * FROM mail_repo_legion";
		$rows = ms_fetch_all($ltb->query($query));
		foreach ($rows as &$row) {
			$row['recv_type'] = $MAIL_RECV_TYPE_LEGION;
			$row['mailq_id'] = $row['repo_id'];
			$all[] = $row;
		}

		$query = "SELECT * FROM mail_repo_public";
		$rows = ms_fetch_all($ltb->query($query));
		foreach ($rows as &$row) {
			$row['recv_type'] = $MAIL_RECV_TYPE_PUBLIC;
			$row['mailq_id'] = $row['repo_id'];
			if ( $row['recv_force'] == 2 )
				$row['recv_force'] = NEUTRAL;
			else if ( $row['recv_force'] == 3 )
				$row['recv_force'] = EMPIRE;

			$all[] = $row;
		}

		assert_render($ltb->end_txn());

		$map['mailq_list'] = $all;

		if ( $do_action_log )
			operator::action_log($tb, 'mailq_list');

		assert_render($tb->end_txn());

		render_ok('success', $map);
	}

	public static function mailq_add($tb) {
		global $TAG, $opuser_id;
		global $MAIL_RECV_TYPE_USER, $MAIL_RECV_TYPE_LEGION, $MAIL_RECV_TYPE_PUBLIC;
		global $FORCE_MAP, $MARKET_TYPE_MAP;

		$recv_type = queryparam_fetch_int('recv_type');
		$title = trim(queryparam_fetch('title', ''));
		$body = trim(queryparam_fetch('body', ''));
		$pull_after_at = trim(queryparam_fetch('send_at', ''));
		$gifts = queryparam_fetch('gifts');

		assert_render(strlen($title) > 0, "invalid:title:length==0", FCODE(26202));
		assert_render(strlen($body) > 0, "invalid:body:length==0", FCODE(26203));

		global $MAIL_TITLE_LIMIT, $MAIL_BODY_LIMIT;
		assert_render(strlen($title) <= $MAIL_TITLE_LIMIT, "invalid:title:exceed limit:$MAIL_TITLE_LIMIT:" . strlen($title), FCODE(26205));
		assert_render(strlen($body) <= $MAIL_BODY_LIMIT, "invalid:body:exceed limit:$MAIL_BODY_LIMIT", FCODE(26206));

		// take gifts
		if ( !is_array($gifts) )
			$gifts = @json_decode(queryparam_fetch('gifts'), true) ?: null;
		elog("attaching gifts: " . pretty_json($gifts));

		if ( !empty($gifts['items']) ) {
			$item_id_seq = 1;
			foreach ($gifts['items'] as &$gift_item) {
				// put implicit item_id as we want lazy item.row insertion when we acquire
				$gift_item['item_id'] = $item_id_seq;
				$item_id_seq++;
			}
		}

		$ltb = new TxnBlock(); // access to live server

		$terms = [];
		if ( $recv_type == $MAIL_RECV_TYPE_USER ) {
			$recv_name = queryparam_fetch('recv_name');
			assert_render(strlen($recv_name) > 0, "invalid:recv_name:$recv_name", FCODE(26204));

			$es = $ltb->escape($recv_name);
			$query = "SELECT user_id FROM user WHERE username = '$es'";
			$user = ms_fetch_one($ltb->query($query));
			assert_render($user, "invalid:username:$recv_name");

			$terms['user_id'] = $user['user_id'];

			$target_db = "mail_repo_user";

		} else if ( $recv_type == $MAIL_RECV_TYPE_LEGION ) {
			$recv_name = queryparam_fetch('recv_name');
			assert_render(strlen($recv_name) > 0, "invalid:recv_name:$recv_name", FCODE(26204));

			$recv_legion_id = mt_rand(10, 99);

			// TODO: check recv_legion_id is available
			$terms['legion_id'] = $recv_legion_id;
			$target_db = "mail_repo_legion";

		} else if ( $recv_type == $MAIL_RECV_TYPE_PUBLIC ) {
			$recv_force = queryparam_fetch_int('recv_force');
			assert_render(1 <= $recv_force && $recv_force <= 3, "invalid:recv_force:$recv_force", FCODE(26204));

			$market_type = queryparam_fetch_int('market_type', 0);
			assert_render(array_key_exists($market_type, $MARKET_TYPE_MAP), "invalid:market_type:$market_type");

			$terms['market_type'] = $market_type;
			if ( $recv_force == NEUTRAL )
				$terms['recv_force'] = 2;
			else if ( $recv_force == EMPIRE )
				$terms['recv_force'] = 3;
			$target_db = "mail_repo_public";

		} else
			render_error("invalid:recv_type:$recv_type");

		$terms['pull_after_at'] = $pull_after_at ? ms_quote($ltb->escape($pull_after_at)) : 'NOW()';
		$terms['title'] = ms_quote($ltb->escape($title));
		$terms['body'] = ms_quote($ltb->escape($body));
		$terms['gifts'] = $gifts ? ms_quote($ltb->escape(pretty_json($gifts))) : null;

		$keys = $vals = [];
		join_terms($terms, $keys, $vals);

		$query = "INSERT INTO $target_db ($keys) VALUES ($vals)";
		assert_render($ltb->query($query));
		$repo_id = $ltb->mc()->insert_id;
		assert_render($ltb->end_txn());

		$detail = $terms;
		$detail['repo_id'] = $repo_id;

		operator::action_log($tb, 'mailq_add', $detail);

		operator::mailq_list($tb, false);

		assert_render($tb->end_txn());

		render_ok('success');
	}

	public static function mailq_del($tb) {
		global $TAG, $opuser_id;
		global $MAIL_RECV_TYPE_USER, $MAIL_RECV_TYPE_LEGION, $MAIL_RECV_TYPE_PUBLIC;
		global $FORCE_MAP, $MARKET_TYPE_MAP;

		$recv_type = queryparam_fetch_int('recv_type');
		$repo_id = queryparam_fetch_int('repo_id');

		if ( $recv_type == $MAIL_RECV_TYPE_USER )
			$target_db = "mail_repo_user";
		else if ( $recv_type == $MAIL_RECV_TYPE_LEGION )
			$target_db = "mail_repo_legion";
		else if ( $recv_type == $MAIL_RECV_TYPE_PUBLIC )
			$target_db = "mail_repo_public";
		else
			render_error("invalid:recv_type:$recv_type");

		$ltb = new TxnBlock(); // access to live server

		$query = "SELECT * FROM $target_db WHERE repo_id = $repo_id";
		$row = ms_fetch_one($ltb->query($query));
		assert_render($row, "invalid:repo_id:not found:$repo_id");

		$query = "DELETE FROM $target_db WHERE repo_id = $repo_id";
		assert_render($ltb->query($query));

		assert_render($ltb->end_txn());

		$detail = $row;
		$detail['recv_type'] = $recv_type;

		operator::action_log($tb, 'mailq_del', $detail);

		operator::mailq_list($tb, false);

		assert_render($tb->end_txn());

		render_ok('success');
	}

	public static function coupon_list($tb, $do_action_log = true) {
		global $TAG, $opuser_id;
		global $COUPON_SEARCH_MAP;

		$search_type = queryparam_fetch_int('search_type', 0);
		$search_value = queryparam_fetch('search_value', '');

		// get list from live

		$ltb = new TxnBlock();

		$where = '';
		if ( $search_type == 1 )
			$where = "WHERE redeem_user_id IS NOT NULL";
		else if ( $search_type == 2 )
			$where = "WHERE redeem_user_id IS NULL";
		else if ( $search_type == 3 )
			$where = "WHERE username = " . ms_quote($ltb->escape($search_value));
		else if ( $search_type == 4 )
			$where = "WHERE code = " . ms_quote($ltb->escape($search_value));
		else
			$where = '';

		$query = "SELECT c.*, u.username FROM coupon_repo AS c LEFT OUTER JOIN user AS u ON c.redeem_user_id = u.user_id $where";

		assert_render($rs = $ltb->query($query));

		if ( queryparam_fetch_int('download') > 0 ) {
			try {
				$sname = "coupon_list";
				$now = new DateTime();
				$date = $now->format("Ymd_His");
				$filename = "$sname"."_$date.xlsx";
				header("Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet");
				header("Content-Disposition: attachment;filename=\"$filename\"");
				header("Cache-Control: max-age=0");

				set_time_limit(60*10); // 10 min
				$px = new PHPExcel();
				$sheet = $px->setActiveSheetIndex(0);
				$sheet->setTitle($sname);
				$sheet->getPageSetup()->setRowsToRepeatAtTopByStartAndEnd();
				$sheet->freezePane('A2');

				$r = 1; $c = 0;
				foreach ($rs->fetch_fields() as $field)
					$sheet->setCellValueByColumnAndRow($c++, $r, $field->name);

				for ($r = 2, $c = 0 ; $row = $rs->fetch_array(MYSQLI_ASSOC) ; $r++, $c = 0) {
					foreach ($row as $k => $v)
						$sheet->setCellValueByColumnAndRow($c++, $r, $v);
				}

				assert_render($ltb->end_txn());

				operator::action_log($tb, $sname, ['download'=>true]);
				assert_render($tb->end_txn());

				$w = PHPExcel_IOFactory::createWriter($px, 'Excel2007');
				$w->save('php://output');

			} catch (Exception $e) {
				$msg = $e->getMessage();
				elog("PHPExcel exception: $msg on " . __METHOD__);
			}
			exit;
		}

		global $SYSTEM_FETCH_ALL_MAX;
		$rows = ms_fetch_all($rs, MYSQLI_ASSOC, $SYSTEM_FETCH_ALL_MAX);
		assert_render($rows, "search returned too many rows(than $SYSTEM_FETCH_ALL_MAX), try downloading");

		assert_render($ltb->end_txn());

		$map['coupon_list'] = $rows;

		if ( $do_action_log )
			operator::action_log($tb, 'coupon_list');

		assert_render($tb->end_txn());

		render_ok('success', $map);
	}

	public static function coupon_add($tb) {
		global $TAG, $opuser_id;
		global $FORCE_MAP, $MARKET_TYPE_MAP;

		$coupon_qty = queryparam_fetch_int('coupon_qty', 1);
		$title = trim(queryparam_fetch('title', ''));
		$body = trim(queryparam_fetch('body', ''));
		$pull_after_at = trim(queryparam_fetch('send_at', ''));
		$gifts = queryparam_fetch('gifts');

		assert_render(strlen($title) > 0, "invalid:title:length==0", FCODE(26202));
		assert_render(strlen($body) > 0, "invalid:body:length==0", FCODE(26203));

		global $MAIL_TITLE_LIMIT, $MAIL_BODY_LIMIT;
		assert_render(strlen($title) <= $MAIL_TITLE_LIMIT, "invalid:title:exceed limit:$MAIL_TITLE_LIMIT:" . strlen($title), FCODE(26205));
		assert_render(strlen($body) <= $MAIL_BODY_LIMIT, "invalid:body:exceed limit:$MAIL_BODY_LIMIT", FCODE(26206));

		// take gifts
		if ( !is_array($gifts) )
			$gifts = @json_decode(queryparam_fetch('gifts'), true) ?: null;
		elog("attaching gifts: " . pretty_json($gifts));

		if ( !empty($gifts['items']) ) {
			$item_id_seq = 1;
			foreach ($gifts['items'] as &$gift_item) {
				// put implicit item_id as we want lazy item.row insertion when we acquire
				$gift_item['item_id'] = $item_id_seq;
				$item_id_seq++;
			}
		}

		$detail = [];
		$detail['coupon_list'] = [];

		$ltb = new TxnBlock(); // access to live server

		for ($i = 0 ; $i < $coupon_qty ; $i++ ) {
			$terms = [];
			$terms['pull_after_at'] = $pull_after_at ? ms_quote($ltb->escape($pull_after_at)) : 'NOW()';
			$terms['title'] = ms_quote($ltb->escape($title));
			$terms['body'] = ms_quote($ltb->escape($body));
			$terms['gifts'] = $gifts ? ms_quote($ltb->escape(pretty_json($gifts))) : null;

			while(1) {
				// generate 16-digit numbered code
				$code = strval(mt_rand(10000000, 99999999)) . strval(mt_rand(10000000, 99999999));
				$elements = str_split($code, 4);
				$code = implode('-', $elements);

				$terms['code'] = ms_quote($code);
				$keys = $vals = [];
				join_terms($terms, $keys, $vals);

				$query = "INSERT IGNORE INTO coupon_repo ($keys) VALUES ($vals)";
				assert_render($ltb->query($query));
				if ( $ltb->mc()->affected_rows > 0 ) {
					$d = [];
					$d['pull_after_at'] = $pull_after_at ? $pull_after_at : null;
					$d['title'] = $title;
					$d['body'] = $body;
					$d['gifts'] = $gifts;
					$d['code'] = $code;
					$d['coupon_id'] = $ltb->insert_id();

					$detail['coupon_list'][] = $d;
					break;
				}
				// code collided, try again
			}
		}

		assert_render($ltb->end_txn());

		operator::action_log($tb, 'coupon_add', $detail);

		operator::coupon_list($tb, false);

		assert_render($tb->end_txn());

		render_ok('success');
	}

	public static function coupon_del($tb) {
		global $TAG, $opuser_id;
		global $FORCE_MAP, $MARKET_TYPE_MAP;

		$coupon_id = queryparam_fetch_int('coupon_id');

		$ltb = new TxnBlock(); // access to live server

		$query = "SELECT * FROM coupon_repo WHERE coupon_id = $coupon_id";
		$row = ms_fetch_one($ltb->query($query));
		assert_render($row, "invalid:coupon_id:not found:$coupon_id");

		$query = "DELETE FROM coupon_repo WHERE coupon_id = $coupon_id";
		assert_render($ltb->query($query));

		assert_render($ltb->end_txn());

		$detail = $row;

		operator::action_log($tb, 'coupon_del', $detail);

		operator::coupon_list($tb, false);

		assert_render($tb->end_txn());

		render_ok('success');
	}

	public static function notice_list($tb, $do_action_log = true) {
		global $TAG, $opuser_id;
		global $NOTICE_TYPE_SYSTEM, $NOTICE_TYPE_EVENT;

		// get list from live
		$all = [];

		$ltb = new TxnBlock();

		$query = "SELECT * FROM notice_repo ORDER BY available_before_at";
		$rows = ms_fetch_all($ltb->query($query));
		foreach ($rows as &$row) {
			if ( $row['recv_force'] == 2 )
				$row['recv_force'] = NEUTRAL;
			else if ( $row['recv_force'] == 3 )
				$row['recv_force'] = EMPIRE;

			// calculate status
			$after = DateTime::createFromFormat('Y-m-d H:i:s', $row['available_after_at'])->getTimestamp();
			$before = DateTime::createFromFormat('Y-m-d H:i:s', $row['available_before_at'])->getTimestamp();
			$now = time();

			$status = 2; // publishing
			if ( $before < $now )
				$status = 3; // done
			else if ( $after > $now )
				$status = 1; // pending

			$row['notice_status'] = $status;

			$all[] = $row;
		}

		assert_render($ltb->end_txn());

		$map['notice_list'] = $all;

		if ( $do_action_log )
			operator::action_log($tb, 'notice_list');

		assert_render($tb->end_txn());

		render_ok('success', $map);
	}

	private static function notice_renew_willbe_refreshed_at($ltb) {
		$query = "SELECT UNIX_TIMESTAMP(NOW()) AS now_utc, "
				."(SELECT UNIX_TIMESTAMP(MIN(available_before_at)) FROM notice_repo WHERE market_type >= 0 AND "
						." recv_force >= 0 AND available_after_at >= FROM_UNIXTIME(0) AND available_before_at >= NOW()) AS min_before_utc, "
								."(SELECT UNIX_TIMESTAMP(MIN(available_after_at)) FROM notice_repo WHERE market_type >= 0 AND "
										."recv_force >= 0 AND available_after_at >= NOW()) AS min_after_utc";

		$notice = ms_fetch_one($ltb->query($query));

		if ( $notice ) {
			elog("notice_utc: " . pretty_json($notice));

			$min_before_utc = $notice['min_before_utc'] ?: PHP_INT_MAX;
			$min_after_utc = $notice['min_after_utc'] ?: PHP_INT_MAX;

			$at = min($min_after_utc, $min_before_utc);
			if ( $at < PHP_INT_MAX ) {
				$query = "REPLACE INTO config (name, value) VALUES ('notice_willbe_refreshed_at', FROM_UNIXTIME($at))";
				assert_render($ltb->query($query));
			}
		}
	}

	private static function notice_update_notices($ltb, $force = false) {
		// update notices (market_type[1,5] X recv_force[allies,empire])

		$notice_willbe_refreshed_at = null;
		if (!$force) {
			$query = "SELECT value FROM config WHERE name = 'notice_willbe_refreshed_at'";
			$notice_willbe_refreshed_at = ms_fetch_one($ltb->query($query));
			if ( !empty($notice_willbe_refreshed_at['value']) ) {
				$ts = DateTime::createFromFormat('Y-m-d H:i:s', $notice_willbe_refreshed_at['value'])->getTimestamp();
				if ( $ts <= time() )
					$notice_willbe_refreshed_at = null;
			}
		}

		if ( !$notice_willbe_refreshed_at ) {
			$query = "SELECT * FROM notice_repo WHERE market_type >= 0 AND recv_force >= 0 AND "
					."available_after_at >= FROM_UNIXTIME(0) AND available_before_at >= NOW()"
							." UNION ALL "
									."SELECT * FROM notice_repo WHERE market_type >= 0 AND recv_force >= 0 AND "
											."available_after_at >= NOW()";
			$rows = ms_fetch_all($ltb->query($query));
			elog("available notices: " . sizeof($rows));

			$notices = [];

			foreach ( $rows as $notice ) {
				global $MARKET_TYPE_MAP;
				foreach ($MARKET_TYPE_MAP as $key => $val) {
					if ( !is_numeric($key) ) continue;

					if ( !isset($notices[$val]['allies']) )
						$notices[$val]['allies'] = [];
					if ( !isset($notices[$val]['empire']) )
						$notices[$val]['empire'] = [];

					if ( $notice['market_type'] == 0 || $notice['market_type'] == $key ) {
						if ( $notice['recv_force'] <= 2 )
							$notices[$val]['allies'][$notice['notice_id']] = $notice['body'];
						if ( $notice['recv_force'] >= 2 )
							$notices[$val]['empire'][$notice['notice_id']] = $notice['body'];
					}
				}
			}

			$ejs = $ltb->escape(pretty_json($notices));
			$query = "REPLACE INTO config (name, value) VALUES ('notices', '$ejs')";
			assert_render($ltb->query($query));

			$now = time();
			$query = "REPLACE INTO config (name, value) VALUES ('notice_refreshed_at_utc', '$now')";
			assert_render($ltb->query($query));

			operator::notice_renew_willbe_refreshed_at($ltb);
		}
	}

	public static function notice_add($tb) {
		global $TAG, $opuser_id;
		global $NOTICE_TYPE_SYSTEM, $NOTICE_TYPE_EVENT;
		global $FORCE_MAP, $MARKET_TYPE_MAP;
		global $TIMESTAMP_MIN, $TIMESTAMP_MAX;

		$notice_type = queryparam_fetch_int('notice_type');
		$market_type = queryparam_fetch_int('market_type', 0);
		$recv_force = queryparam_fetch_int('recv_force');

		$body = trim(queryparam_fetch('body', ''));
		$available_after_at = trim(queryparam_fetch('available_after_at'));
		$available_before_at = trim(queryparam_fetch('available_before_at'));

		assert_render(strlen($body) > 0, "invalid:body:length==0", FCODE(26203));

		// 		global $MAIL_TITLE_LIMIT, $MAIL_BODY_LIMIT;
		// 		assert_render(strlen($body) <= $MAIL_BODY_LIMIT, "invalid:body:exceed limit:$MAIL_BODY_LIMIT", FCODE(26206));

		$ltb = new TxnBlock(); // access to live server

		$terms = [];

		assert_render($notice_type == $NOTICE_TYPE_SYSTEM || $notice_type == $NOTICE_TYPE_EVENT, "invalid:notice_type:$notice_type");
		assert_render(1 <= $recv_force && $recv_force <= 3, "invalid:recv_force:$recv_force", FCODE(26204));

		assert_render(array_key_exists($market_type, $MARKET_TYPE_MAP), "invalid:market_type:$market_type");

		$terms['market_type'] = $market_type;
		$terms['recv_force'] = 1;
		if ( $recv_force == NEUTRAL )
			$terms['recv_force'] = 2;
		else if ( $recv_force == EMPIRE )
			$terms['recv_force'] = 3;

		$terms['available_after_at'] = $available_after_at ? ms_quote($ltb->escape($available_after_at)) : 'NOW()';
		$terms['available_before_at'] = $available_before_at ? ms_quote($ltb->escape($available_before_at)) : ms_quote($TIMESTAMP_MAX);
		$terms['body'] = ms_quote($ltb->escape($body));

		$keys = $vals = [];
		join_terms($terms, $keys, $vals);

		$query = "INSERT INTO notice_repo ($keys) VALUES ($vals)";
		assert_render($ltb->query($query));
		$notice_id = $ltb->mc()->insert_id;

		operator::notice_update_notices($ltb, true);
		operator::notice_renew_willbe_refreshed_at($ltb);

		assert_render($ltb->end_txn());

		$detail = $terms;
		$detail['notice_id'] = $notice_id;

		operator::action_log($tb, 'notice_add', $detail);

		operator::notice_list($tb, false);

		assert_render($tb->end_txn());

		render_ok('success');
	}

	public static function notice_del($tb) {
		global $TAG, $opuser_id;
		global $NOTICE_TYPE_SYSTEM, $NOTICE_TYPE_EVENT;
		global $FORCE_MAP, $MARKET_TYPE_MAP;
		global $TIMESTAMP_MIN, $TIMESTAMP_MAX;

		$notice_id = queryparam_fetch_int('notice_id');

		$ltb = new TxnBlock(); // access to live server

		$query = "SELECT * FROM notice_repo WHERE notice_id = $notice_id";
		$row = ms_fetch_one($ltb->query($query));
		assert_render($row, "invalid:notice_id:not found:$notice_id");

		$query = "DELETE FROM notice_repo WHERE notice_id = $notice_id";
		assert_render($ltb->query($query));

		operator::notice_update_notices($ltb, true);
		operator::notice_renew_willbe_refreshed_at($ltb);

		assert_render($ltb->end_txn());

		$detail = $row;

		operator::action_log($tb, 'notice_del', $detail);

		operator::notice_list($tb, false);

		assert_render($tb->end_txn());

		render_ok('success');
	}

	private static function probe_concurrent_users($tb) {
		global $TAG, $opuser_id;
		global $MARKET_TYPE_MAP;

		$redis = conn_redis();

		$concurrent_users = [];
		foreach ($MARKET_TYPE_MAP as $key => $val) {
			if ( is_numeric($key) )
				$concurrent_users[$val] = [];
		}

		$skeys = $redis->keys("$TAG:PHPREDIS_SESSION:*");
		if ( is_array($skeys) ) {
			// TODO: exploit redis->pipeline for performance

			$skeys_all = 0;
			$skeys_available = 0;

			foreach	($skeys as $skey) {
				$sessionstr = $redis->get($skey);
				if ( $sessionstr && strlen($sessionstr) > 0 ) {
					try {
						$session = Session::unserialize_php($sessionstr);
						elog("probing session: " . pretty_json($session));
						if ( isset($session['user_id']) && isset($session['general_id']) && isset($session['user']['market_type']) ) {
							$market_type = $session['user']['market_type'];
							$market_type_str = @$MARKET_TYPE_MAP[$market_type] ?: null;
							if ( !is_null($market_type_str) ) {
								if ( !in_array($session['user_id'], $concurrent_users[$market_type_str]) ) {
									$concurrent_users[$market_type_str][] = $session['user_id'];
									$skeys_available++;
								}
							}
						}
					} catch (Exception $e) {
					}
				}
				$skeys_all++;
			}
			elog("skeys_all: $skeys_all, skeys_available: $skeys_available");
			elog("concurrent_users: " . pretty_json($concurrent_users));

			$detail = [];
			$detail['skeys_all'] = $skeys_all;
			$detail['skeys_available'] = $skeys_available;
			operator::action_log($tb, 'probe_concurrent_users', $detail);

		} else {
			elog("redis->keys() didnt return array, skips");
		}

		return $concurrent_users;
	}

	public static function user_probe_concurrent_users($tb) {
		global $TAG, $opuser_id;
		global $MARKET_TYPE_MAP, $MARKET_TYPE_RMAP;

		$concurrent_users = operator::probe_concurrent_users($tb);

		$concurrent_users_qty = [];
		$querys = [];
		foreach ($concurrent_users as $market_type => $user_ids) {
			$type = $MARKET_TYPE_RMAP[$market_type];
			$qty = sizeof($user_ids);
			$concurrent_users_qty[$type] = $qty;
			if ( $qty > 0 )
				$querys[] = "INSERT INTO probe_concurrent_users (market_type, qty, probe_at) VALUES ($type, $qty, NOW())";
		}
		assert_render($tb->multi_query($querys));

		operator::action_log($tb, 'user_probe_concurrent_users', $concurrent_users_qty);

		assert_render($tb->end_txn());

		render_ok();
	}

	public static function probe_set_events($tb) {
		global $TAG, $opuser_id;
		global $SYSTEM_EXECUTION_TIME_LIMIT, $SYSTEM_EVENT_TARGET_URLBASE;
		global $BATTLEFIELD_HOTSPOT_TIME_QUANTUM;

		// set user_probe_concurrent_users (every 5 minutes)
		$url = "$SYSTEM_EVENT_TARGET_URLBASE/operation/op_api.php?acl=event&op=user_probe_concurrent_users";
		$eventname = $TAG . "_probe_concurrent_users";
		$querys = [];
		// 		$querys[] = "DROP EVENT IF EXISTS $eventname";
		$querys[] = "CREATE DEFINER=`ew_op`@`%` EVENT IF NOT EXISTS `$eventname` "
		."ON SCHEDULE EVERY 5 MINUTE ON COMPLETION PRESERVE ENABLE DO "
				."SELECT sys_exec('(curl -m $SYSTEM_EXECUTION_TIME_LIMIT \"$url\" 2>&1) | logger')";
		assert_render($tb->multi_query($querys));

		// set battlefield_rebuild_hotspots (every $BATTLEFIELD_HOTSPOT_TIME_QUANTUM minutes)
		$url = "$SYSTEM_EVENT_TARGET_URLBASE/battlefield/tile.php?op=rebuild_hotspots&acl=event&stop_on_success=1";
		$eventname = $TAG . "_battlefield_rebuild_hotspots";
		$querys = [];
		// 		$querys[] = "DROP EVENT IF EXISTS $eventname";
		$querys[] = "CREATE DEFINER=`ew_op`@`%` EVENT IF NOT EXISTS `$eventname` "
		."ON SCHEDULE EVERY $BATTLEFIELD_HOTSPOT_TIME_QUANTUM MINUTE ON COMPLETION PRESERVE ENABLE DO "
		."SELECT sys_exec('(curl -m $SYSTEM_EXECUTION_TIME_LIMIT \"$url\" 2>&1) | logger')";
		assert_render($tb->multi_query($querys));
	}

	public static function statistics_service_build($tb) {
		global $TAG, $opuser_id;
		global $STATS_SERVICE_JOINED, $STATS_SERVICE_CONCURRENT, $STATS_SERVICE_ACTIVE, $STATS_SERVICE_PARTED, $STATS_SERVICE_RMAP;

		// concurrent_users
		elog("building statistics for concurrent_users ...");

		// for each market_type
		$querys = [];

		global $MARKET_TYPE_MAP;
		foreach ($MARKET_TYPE_MAP as $market_type => $desc) {
			if ( !is_numeric($market_type) ) continue;

			elog("building [market_type:$market_type] ...");

			$datetime_bgn = '';
			$datetime_end = '';
			// find datetime_bgn, datetime_end
			$query = "SELECT MAX(stat_at) AS datetime_bgn, DATE_FORMAT(TIMESTAMPADD(HOUR, 0, NOW()), '%Y-%m-%d %H:00:00') AS datetime_end "
					."FROM stats_service WHERE stat_type = $STATS_SERVICE_CONCURRENT AND market_type = $market_type";
			$row = ms_fetch_one($tb->query($query));
			$datetime_bgn = $row['datetime_bgn'];
			$datetime_end = $row['datetime_end'];
			if ( empty($datetime_bgn) ) {
				// first-time build
				$query = "SELECT DATE_FORMAT(MIN(probe_at), '%Y-%m-%d %H:00:00') AS datetime_bgn FROM probe_concurrent_users WHERE market_type = $market_type";
				$row = ms_fetch_one($tb->query($query));
				$datetime_bgn = $row['datetime_bgn'];
			}

			if ( empty($datetime_bgn) )
				elog("we dont have probed rows for concurrent_users on [market_type:$market_type]");
			else {
				$cur = $dt_bgn = DateTime::createFromFormat('Y-m-d H:00:00', $datetime_bgn);
				$dt_end = DateTime::createFromFormat('Y-m-d H:00:00', $datetime_end);

				for ( $cur = $dt_bgn ; $cur < $dt_end ; $cur->add(new DateInterval('PT1H')) ) {
					elog("building [market_type:$market_type] at " . $cur->format(DateTime::ATOM));
					$cur_str = $cur->format('Y-m-d H:i:s');

					$query = "INSERT IGNORE INTO stats_service (stat_type, market_type, stat_at, value) ";
					$query .= "(SELECT $STATS_SERVICE_CONCURRENT, $market_type, '$cur_str', ";
					$query .= "(SELECT AVG(qty) FROM probe_concurrent_users WHERE ";
					$query .= "market_type = $market_type AND probe_at >= '$cur_str' AND probe_at < TIMESTAMPADD(HOUR, 1, '$cur_str')) ) ";

					$querys[] = $query;
				}
			}
		}

		assert_render($tb->multi_query($querys));
		$querys = [];

		// active_users, joined_users, parted_users
		$build_types = $STATS_SERVICE_RMAP;
		unset($build_types['concurrent_users']);
		$op_types = ['active_users'=>'login', 'joined_users'=>'add', 'parted_users'=>'del'];
		foreach ($build_types as $build_type => $stat_type) {
			elog("building statistics for $build_type ...");

			$op_type = $op_types[$build_type];

			// for each market_type
			global $MARKET_TYPE_MAP;
			foreach ($MARKET_TYPE_MAP as $market_type => $desc) {
				if ( !is_numeric($market_type) ) continue;
					
				elog("building [market_type:$market_type] ...");
					
				$datetime_bgn = '';
				$datetime_end = '';
				// find datetime_bgn, datetime_end
				$query = "SELECT MAX(stat_at) AS datetime_bgn, DATE_FORMAT(TIMESTAMPADD(HOUR, 0, NOW()), '%Y-%m-%d %H:00:00') AS datetime_end "
						."FROM stats_service WHERE stat_type = $stat_type AND market_type = $market_type";
					
				$row = ms_fetch_one($tb->query($query));
				$datetime_bgn = $row['datetime_bgn'];
				$datetime_end = $row['datetime_end'];
				if ( empty($datetime_bgn) ) {
					// first-time build
					$query = "SELECT DATE_FORMAT(MIN(action_at), '%Y-%m-%d %H:00:00') AS datetime_bgn ";
					$query .= "FROM actions_user AS a JOIN user AS u ON a.user_id = u.user_id ";
					$query .= "WHERE u.market_type = $market_type AND type = '$op_type'";

					$row = ms_fetch_one($tb->query($query));
					$datetime_bgn = $row['datetime_bgn'];
				}
					
				if ( empty($datetime_bgn) )
					elog("we dont have probed rows for $build_type on [market_type:$market_type]");
				else {
					$cur = $dt_bgn = DateTime::createFromFormat('Y-m-d H:00:00', $datetime_bgn);
					$dt_end = DateTime::createFromFormat('Y-m-d H:00:00', $datetime_end);

					for ( $cur = $dt_bgn ; $cur < $dt_end ; $cur->add(new DateInterval('PT1H')) ) {
						elog("building [market_type:$market_type] at " . $cur->format(DateTime::ATOM));
						$cur_str = $cur->format('Y-m-d H:i:s');
							
						$query = "INSERT IGNORE INTO stats_service (stat_type, market_type, stat_at, value) ";
						$query .= "(SELECT $stat_type, $market_type, '$cur_str', ";
						$query .= "(SELECT COUNT(DISTINCT a.user_id) ";
						$query .= "FROM actions_user AS a JOIN user AS u ON a.user_id = u.user_id ";
						$query .= "WHERE market_type = $market_type AND type = '$op_type' AND action_at >= '$cur_str' AND action_at < TIMESTAMPADD(HOUR, 1, '$cur_str')) ) ";
							
						$querys[] = $query;
					}
				}
			}
			assert_render($tb->multi_query($querys));
			$querys = [];
		}

		operator::action_log($tb, 'statistics_service_build');
	}

	public static function statistics_service_list($tb, $do_action_log = true) {
		global $TAG, $opuser_id;
		global $STATS_SERVICE_JOINED, $STATS_SERVICE_CONCURRENT, $STATS_SERVICE_ACTIVE, $STATS_SERVICE_PARTED, $STATS_SERVICE_RMAP;
		global $MARKET_TYPE_MAP;

		operator::probe_set_events($tb);

		$service_type = queryparam_fetch_int('service_type');
		assert_render(in_array($service_type, array_values($STATS_SERVICE_RMAP)), "invalid:service_type:$service_type");

		$market_type = queryparam_fetch_int('market_type');
		assert_render(in_array($market_type, array_keys($MARKET_TYPE_MAP)), "invalid:market_type:$market_type");

		$result_type = queryparam_fetch_int('result_type');
		assert_render(in_array($result_type, [1, 2]), "invalid:result_type:$result_type");

		$bgn_at = queryparam_fetch('bgn_at');
		$bgn_at = !empty($bgn_at) ? $bgn_at : '1970-01-01';
		$end_at = queryparam_fetch('end_at');
		$end_at = !empty($end_at) ? $end_at : '2038-01-01';

		$dt_bgn_at = DateTime::createFromFormat('Y-m-d', $bgn_at);
		$dt_end_at = DateTime::createFromFormat('Y-m-d', $end_at);
		assert_render($dt_bgn_at != FALSE, "invalid:bgn_at:$bgn_at");
		assert_render($dt_end_at != FALSE, "invalid:end_at:$end_at");

		if ( $result_type == 1 ) {
			$bgn_at = $dt_bgn_at->format('Y-m-d 00:00:00');
			$end_at = $dt_end_at->format('Y-m-d 00:00:00');
		} else {
			$bgn_at = $dt_bgn_at->format('Y-m-01 00:00:00');
			$end_at = $dt_end_at->format('Y-m-01 00:00:00');
		}

		operator::statistics_service_build($tb);

		if ( $result_type == 1 ) {
			$query = "SELECT * FROM stats_service WHERE stat_type = $service_type AND market_type = $market_type ";
			$query .= "AND stat_at >= '$bgn_at' AND stat_at < '$end_at' ";
			$query .= "ORDER BY stat_at";
		} else {
			$op_type = in_array($service_type, [$STATS_SERVICE_CONCURRENT, $STATS_SERVICE_ACTIVE]) ? 'AVG' : 'SUM';

			$query = "SELECT DATE_FORMAT(stat_at, '%Y-%m') AS stat_id, market_type, $op_type(value) AS $op_type ";
			$query .= "FROM stats_service WHERE stat_type = $service_type AND market_type = $market_type ";
			$query .= "AND stat_at >= '$bgn_at' AND stat_at < '$end_at' GROUP BY DATE_FORMAT(stat_at, '%Y%m') ";
			$query .= "";
		}

		assert_render($rs = $tb->query($query));

		if ( queryparam_fetch_int('download') > 0 ) {
			try {
				$sname = "statistics_service_list";
				$now = new DateTime();
				$date = $now->format("Ymd_His");
				$filename = "$sname"."_$date.xlsx";
				header("Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet");
				header("Content-Disposition: attachment;filename=\"$filename\"");
				header("Cache-Control: max-age=0");

				set_time_limit(60*10); // 10 min
				$px = new PHPExcel();
				$sheet = $px->setActiveSheetIndex(0);
				$sheet->setTitle($sname);
				$sheet->getPageSetup()->setRowsToRepeatAtTopByStartAndEnd();
				$sheet->freezePane('A2');

				$r = 1; $c = 0;
				foreach ($rs->fetch_fields() as $field)
					$sheet->setCellValueByColumnAndRow($c++, $r, $field->name);

				for ($r = 2, $c = 0 ; $row = $rs->fetch_array(MYSQLI_ASSOC) ; $r++, $c = 0) {
					foreach ($row as $k => $v)
						$sheet->setCellValueByColumnAndRow($c++, $r, $v);
				}

				operator::action_log($tb, $sname, ['download'=>true]);
				assert_render($tb->end_txn());

				$w = PHPExcel_IOFactory::createWriter($px, 'Excel2007');
				$w->save('php://output');

			} catch (Exception $e) {
				$msg = $e->getMessage();
				elog("PHPExcel exception: $msg on " . __METHOD__);
			}
			exit;
		}

		global $SYSTEM_FETCH_ALL_MAX;
		$rows = ms_fetch_all($rs, MYSQLI_ASSOC, $SYSTEM_FETCH_ALL_MAX);
		assert_render($rows, "search returned too many rows(than $SYSTEM_FETCH_ALL_MAX), try downloading");

		$map['stats_service'] = $rows;

		if ( $do_action_log )
			operator::action_log($tb, 'statistics_service_list');

		assert_render($tb->end_txn());

		render_ok('success', $map);
	}

	public static function statistics_sale_build($tb) {
		global $TAG, $opuser_id;
		global $MARKET_TYPE_MAP;
		global $STATS_SALE_REVENUE, $STATS_SALE_COUNT, $STATS_SALE_BUYERS, $STATS_SALE_ARPU, $STATS_SALE_RMAP;

		$querys = [];

		$build_types = $STATS_SALE_RMAP;
		foreach ($build_types as $build_type => $stat_type) {
			elog("building statistics for $build_type ...");

			// for each market_type
			global $MARKET_TYPE_MAP;
			foreach ($MARKET_TYPE_MAP as $market_type => $desc) {
				if ( !is_numeric($market_type) ) continue;
					
				elog("building [market_type:$market_type] ...");
					
				$datetime_bgn = '';
				$datetime_end = '';
				// find datetime_bgn, datetime_end
				$query = "SELECT MAX(stat_at) AS datetime_bgn, ";
				$query .= "DATE_FORMAT(TIMESTAMPADD(HOUR, 0, NOW()), '%Y-%m-%d %H:00:00') AS datetime_end ";
				$query .= "FROM stats_sale WHERE stat_type = $stat_type AND market_type = $market_type";
				$row = ms_fetch_one($tb->query($query));

				$datetime_bgn = $row['datetime_bgn'];
				$datetime_end = $row['datetime_end'];
				if ( empty($datetime_bgn) ) {
					// first-time build
					$query = "SELECT DATE_FORMAT(MIN(action_at), '%Y-%m-%d %H:00:00') AS datetime_bgn ";
					$query .= "FROM actions_shop AS a JOIN user AS u ON a.user_id = u.user_id ";
					$query .= "WHERE u.market_type = $market_type AND type = 'buy_cash'";

					$row = ms_fetch_one($tb->query($query));
					$datetime_bgn = $row['datetime_bgn'];
				}
					
				if ( empty($datetime_bgn) )
					elog("we dont have probed rows for $build_type on [market_type:$market_type]");
				else {
					$cur = $dt_bgn = DateTime::createFromFormat('Y-m-d H:00:00', $datetime_bgn);
					$dt_end = DateTime::createFromFormat('Y-m-d H:00:00', $datetime_end);

					for ( $cur = $dt_bgn ; $cur < $dt_end ; $cur->add(new DateInterval('PT1H')) ) {
						elog("building [market_type:$market_type] at " . $cur->format(DateTime::ATOM));
						$cur_str = $cur->format('Y-m-d H:i:s');
							
						$query = "INSERT IGNORE INTO stats_sale (stat_type, market_type, stat_at, value) ";
						$query .= "(SELECT $stat_type, $market_type, '$cur_str', ";

						if ( $build_type == 'revenue' ) $query .= "(SELECT SUM(price) ";
						else if ( $build_type == 'count' ) $query .= "(SELECT COUNT(*) ";
						else if ( $build_type == 'buyers' ) $query .= "(SELECT COUNT(DISTINCT a.user_id) ";
						else if ( $build_type == 'arpu' )
							$query .= "(SELECT IF(COUNT(DISTINCT a.user_id) > 0, SUM(price) / COUNT(DISTINCT a.user_id), 0) ";
							
						$query .= "FROM actions_shop AS a JOIN user AS u ON a.user_id = u.user_id ";
						$query .= "WHERE market_type = $market_type AND type = 'buy_cash' AND ";
						$query .= "action_at >= '$cur_str' AND action_at < TIMESTAMPADD(HOUR, 1, '$cur_str')) ) ";
							
						$querys[] = $query;
					}
				}
			}
			assert_render($tb->multi_query($querys));
			$querys = [];
		}

		operator::action_log($tb, 'statistics_sale_build');
	}

	public static function statistics_sale_list($tb, $do_action_log = true) {
		global $TAG, $opuser_id;
		global $MARKET_TYPE_MAP;
		global $STATS_SALE_REVENUE, $STATS_SALE_COUNT, $STATS_SALE_BUYERS, $STATS_SALE_ARPU, $STATS_SALE_RMAP;

		operator::probe_set_events($tb);

		$sale_type = queryparam_fetch_int('sale_type');
		assert_render(in_array($sale_type, array_values($STATS_SALE_RMAP)), "invalid:sale_type:$sale_type");

		$market_type = queryparam_fetch_int('market_type');
		assert_render(in_array($market_type, array_keys($MARKET_TYPE_MAP)), "invalid:market_type:$market_type");

		$result_type = queryparam_fetch_int('result_type');
		assert_render(in_array($result_type, [1, 2]), "invalid:result_type:$result_type");

		$bgn_at = queryparam_fetch('bgn_at');
		$bgn_at = !empty($bgn_at) ? $bgn_at : '1970-01-01';
		$end_at = queryparam_fetch('end_at');
		$end_at = !empty($end_at) ? $end_at : '2038-01-01';

		$dt_bgn_at = DateTime::createFromFormat('Y-m-d', $bgn_at);
		$dt_end_at = DateTime::createFromFormat('Y-m-d', $end_at);
		assert_render($dt_bgn_at != FALSE, "invalid:bgn_at:$bgn_at");
		assert_render($dt_end_at != FALSE, "invalid:end_at:$end_at");

		if ( $result_type == 1 ) {
			$bgn_at = $dt_bgn_at->format('Y-m-d 00:00:00');
			$end_at = $dt_end_at->format('Y-m-d 00:00:00');
		} else {
			$bgn_at = $dt_bgn_at->format('Y-m-01 00:00:00');
			$end_at = $dt_end_at->format('Y-m-01 00:00:00');
		}

		operator::statistics_sale_build($tb);

		if ( $result_type == 1 ) {
			$query = "SELECT * FROM stats_sale WHERE stat_type = $sale_type AND market_type = $market_type ";
			$query .= "AND stat_at >= '$bgn_at' AND stat_at < '$end_at' ";
			$query .= "ORDER BY stat_at";
		} else {
			$op_type = $sale_type == $STATS_SALE_REVENUE || $sale_type == $STATS_SALE_COUNT ? 'SUM' : 'AVG';

			$query = "SELECT DATE_FORMAT(stat_at, '%Y-%m') AS stat_id, market_type, $op_type(value) AS $op_type ";
			$query .= "FROM stats_sale WHERE stat_type = $sale_type AND market_type = $market_type ";
			$query .= "AND stat_at >= '$bgn_at' AND stat_at < '$end_at' GROUP BY DATE_FORMAT(stat_at, '%Y%m') ";
			$query .= "";
		}

		assert_render($rs = $tb->query($query));

		if ( queryparam_fetch_int('download') > 0 ) {
			try {
				$sname = "statistics_sale_list";
				$now = new DateTime();
				$date = $now->format("Ymd_His");
				$filename = "$sname"."_$date.xlsx";
				header("Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet");
				header("Content-Disposition: attachment;filename=\"$filename\"");
				header("Cache-Control: max-age=0");

				set_time_limit(60*10); // 10 min
				$px = new PHPExcel();
				$sheet = $px->setActiveSheetIndex(0);
				$sheet->setTitle($sname);
				$sheet->getPageSetup()->setRowsToRepeatAtTopByStartAndEnd();
				$sheet->freezePane('A2');

				$r = 1; $c = 0;
				foreach ($rs->fetch_fields() as $field)
					$sheet->setCellValueByColumnAndRow($c++, $r, $field->name);

				for ($r = 2, $c = 0 ; $row = $rs->fetch_array(MYSQLI_ASSOC) ; $r++, $c = 0) {
					foreach ($row as $k => $v)
						$sheet->setCellValueByColumnAndRow($c++, $r, $v);
				}

				operator::action_log($tb, $sname, ['download'=>true]);
				assert_render($tb->end_txn());

				$w = PHPExcel_IOFactory::createWriter($px, 'Excel2007');
				$w->save('php://output');

			} catch (Exception $e) {
				$msg = $e->getMessage();
				elog("PHPExcel exception: $msg on " . __METHOD__);
			}
			exit;
		}

		global $SYSTEM_FETCH_ALL_MAX;
		$rows = ms_fetch_all($rs, MYSQLI_ASSOC, $SYSTEM_FETCH_ALL_MAX);
		assert_render($rows, "search returned too many rows(than $SYSTEM_FETCH_ALL_MAX), try downloading");

		$map['stats_sale'] = $rows;

		if ( $do_action_log )
			operator::action_log($tb, 'statistics_sale_list');

		assert_render($tb->end_txn());

		render_ok('success', $map);
	}


	public static function statistics_game_list($tb, $do_action_log = true) {
		global $TAG, $opuser_id;

		$all = [];


		$map['result_list'] = $all;

		if ( $do_action_log )
			operator::action_log($tb, 'statistics_game_list');

		assert_render($tb->end_txn());

		render_ok('success', $map);
	}

	public static function statistics_battlefield_list($tb, $do_action_log = true) {
		global $TAG, $opuser_id;

		$all = [];


		$map['result_list'] = $all;

		if ( $do_action_log )
			operator::action_log($tb, 'statistics_battlefield_list');

		assert_render($tb->end_txn());

		render_ok('success', $map);
	}



	private function curltest() {
		$terms = [];
		$terms['recv_type'] = $MAIL_RECV_TYPE_PUBLIC;
		$terms['title'] = $title;
		$terms['body'] = $body;
		$terms['pull_after_at'] = $send_at;
		$terms['gifts'] = pretty_json($gifts);
		$terms['market_type'] = $recv_market_type;
		$terms['recv_force'] = $recv_force;
		$terms['acl'] = 'operator';

		require_once '../Curl.class.php';

		global $SYSTEM_EVENT_TARGET_URLBASE;
		$url = "http://$SYSTEM_EVENT_TARGET_URLBASE/general/mail.php?op=enqueue";

		$done = false;
		try {
			$curl = new Curl();
			$curl->post($url, $terms);
			if ( !$curl->error && $curl->http_status_code == 200 ) {
				$response = @json_decode($curl->response, true) ?: [];
				elog("req done: " . pretty_json($response));
				if ( !(isset($response['code']) && $response['code'] == 'ok') )
					render_error(@$response['message']);

				$done = true;
			} else {
				$response = @json_decode($curl->response, true) ?: [];
				elog("req fail: " . pretty_json($response));

				render_error(@$response['message']);
			}
		} catch (Exception $e) {
			$emsg = $e->getMessage();
			elog("got [$emsg] exception on curl");
		}
	}
	public static function run($tb) {
		global $TAG, $ALLIES, $EMPIRE, $opuser_id, $general_id, $dev_type, $dev_uuid, $ops, $status, $country;

		$query = queryparam_fetch('query');
		$querys = queryparam_fetch('querys');

		if ( $querys ) {
			$q = explode(';', $querys);
			assert_render($tb->multi_query($q));
		}
		else
			assert_render($tb->query($query));

		assert_render($tb->end_txn());

		render_ok();
	}
}

if ( strstr($_SERVER["REQUEST_URI"], basename(__FILE__)) ) {
	global $MYSQL_USER_OP, $MYSQL_DB_OP;

	global $SYSTEM_OPERATOR_ALLOWED_IPS;
	if ( $remote_addr = @$_SERVER['REMOTE_ADDR'] ) {
		$SYSTEM_OPERATOR_ALLOWED_IPS = empty($SYSTEM_OPERATOR_ALLOWED_IPS) ? [] : $SYSTEM_OPERATOR_ALLOWED_IPS;
		assert_render(in_array($remote_addr, $SYSTEM_OPERATOR_ALLOWED_IPS), "you are not allowed to access: $remote_addr");
	}

	$op_mysql_client = conn_mysql(null, ['MYSQL_USER'=>$MYSQL_USER_OP, 'MYSQL_DB'=>$MYSQL_DB_OP]);

	$ops = explode(',', $op = queryparam_fetch('op', 'get'));

	$opuser_id = null;

	if ( in_array('login', $ops) ) {
		if ( ($opuser_id = login_check(false, 'opuser_id')) )
			render_ok('already logged in as ' . $_SESSION['opuser_id'], array('fcode' => 30102));
	}
	else
		$opuser_id = login_check(true, 'opuser_id');

	if ( sizeof(array_intersect_key(
			['get', 'run']
			+ ['login', 'logout', 'register']
			+ ['opuser_list', 'opuser_del', 'opuser_mod']
			+ ['opaction_list']
			+ ['user_list', 'user_probe_concurrent_users', 'user_penalty_list', 'user_penalty_mod']
			+ ['maintenance_set', 'maintenance_message_set', 'maintenance_allowed_list', 'maintenance_allowed_add', 'maintenance_allowed_del']
			+ ['chat_list', 'chat_badword_list', 'chat_badword_add', 'chat_badword_del']
			+ ['mailq_list', 'mailq_add', 'mailq_del']
			+ ['coupon_list', 'coupon_add', 'coupon_del']
			+ ['statistics_service_list', 'statistics_sale_list', 'statistics_game_list', 'statistics_battlefield_list']
			, $ops)) ) {
		for ( $retried = 1 ; $retried <= $TXN_RETRY_MAX ; $retried++ ) {
			try {
				$tb = new TxnBlock($op_mysql_client);

				//	if ( in_array("clear", $ops) ) operator::clear($tb);

				if ( dev ) {
					if ( in_array("run", $ops) ) operator::run($tb);
					else if ( in_array("login", $ops) ) operator::login($tb);
					else if ( in_array("logout", $ops) ) operator::logout($tb);
					else if ( in_array("register", $ops) ) operator::register($tb);
					else if ( in_array("opuser_list", $ops) ) operator::opuser_list($tb);
					else if ( in_array("opuser_mod", $ops) ) operator::opuser_mod($tb);
					else if ( in_array("opuser_del", $ops) ) operator::opuser_del($tb);
					else if ( in_array("opaction_list", $ops) ) operator::opaction_list($tb);

					else if ( in_array("user_list", $ops) ) operator::user_list($tb);
					else if ( in_array("user_probe_concurrent_users", $ops) ) operator::user_probe_concurrent_users($tb);
					else if ( in_array("user_penalty_list", $ops) ) operator::user_penalty_list($tb);
					else if ( in_array("user_penalty_mod", $ops) ) operator::user_penalty_mod($tb);

					else if ( in_array("maintenance_set", $ops) ) operator::maintenance_set($tb);
					else if ( in_array("maintenance_message_set", $ops) ) operator::maintenance_message_set($tb);
					else if ( in_array("maintenance_allowed_list", $ops) ) operator::maintenance_allowed_list($tb);
					else if ( in_array("maintenance_allowed_add", $ops) ) operator::maintenance_allowed_add($tb);
					else if ( in_array("maintenance_allowed_del", $ops) ) operator::maintenance_allowed_del($tb);

					else if ( in_array("chat_list", $ops) ) operator::chat_list($tb);
					else if ( in_array("chat_badword_list", $ops) ) operator::chat_badword_list($tb);
					else if ( in_array("chat_badword_add", $ops) ) operator::chat_badword_add($tb);
					else if ( in_array("chat_badword_del", $ops) ) operator::chat_badword_del($tb);

					else if ( in_array("mailq_list", $ops) ) operator::mailq_list($tb);
					else if ( in_array("mailq_add", $ops) ) operator::mailq_add($tb);
					else if ( in_array("mailq_del", $ops) ) operator::mailq_del($tb);

					else if ( in_array("coupon_list", $ops) ) operator::coupon_list($tb);
					else if ( in_array("coupon_add", $ops) ) operator::coupon_add($tb);
					else if ( in_array("coupon_del", $ops) ) operator::coupon_del($tb);

					else if ( in_array("notice_list", $ops) ) operator::notice_list($tb);
					else if ( in_array("notice_add", $ops) ) operator::notice_add($tb);
					else if ( in_array("notice_del", $ops) ) operator::notice_del($tb);

					else if ( in_array("statistics_service_list", $ops) ) operator::statistics_service_list($tb);
					else if ( in_array("statistics_sale_list", $ops) ) operator::statistics_sale_list($tb);
					else if ( in_array("statistics_game_list", $ops) ) operator::statistics_game_list($tb);
					else if ( in_array("statistics_battlefield_list", $ops) ) operator::statistics_battlefield_list($tb);
				}

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
