<?php

/**
 * Make mysql connection object
 * @param string $timezone_offset
 * @return mysqli object
 */
function conn_mysql($timezone_offset = null, $mysql_params = []) {
	global $MYSQL_CLUSTERED, $MYSQL_USER, $MYSQL_PASS, $MYSQL_DB, $MYSQL_HOSTS, $MYSQL_PORT;

	$params['MYSQL_CLUSTERED'] = @$mysql_params['MYSQL_CLUSTERED'] ?: $MYSQL_CLUSTERED;
	$params['MYSQL_USER'] = @$mysql_params['MYSQL_USER'] ?: $MYSQL_USER;
	$params['MYSQL_PASS'] = @$mysql_params['MYSQL_PASS'] ?: $MYSQL_PASS;
	$params['MYSQL_DB'] = @$mysql_params['MYSQL_DB'] ?: $MYSQL_DB;
	$params['MYSQL_HOSTS'] = @$mysql_params['MYSQL_HOSTS'] ?: $MYSQL_HOSTS;
	$params['MYSQL_PORT'] = @$mysql_params['MYSQL_PORT'] ?: $MYSQL_PORT;

	if ( $params['MYSQL_CLUSTERED'] ) {
		$hosts = array_merge([], $params['MYSQL_HOSTS']);

		while (sizeof($hosts) > 0) {
			$idx = mt_rand(0, sizeof($hosts)-1);
			$host = $hosts[$idx];

			$username= session_GET('username');
			if ( !$username )
				$username = queryparam_fetch('username');

			$hash = crc32(sha1("--$username--"));
			$idx = $hash % sizeof($hosts);
			$idx = max(0, $idx);
			$host = $hosts[$idx];

			// For DEV: override hash based host
			$idx = mt_rand(0, sizeof($hosts)-1);
			$host = $hosts[$idx];

			// 			elog("host picked: $host, for [$username], [$hash]");
			$hkey = "INACTIVE_DBHOST_$host";
			if ( function_exists('apc_exists') && apc_exists($hkey) ) {
				$hosts = array_values(array_diff($hosts, array($host)));
				elog("dbhost: $host is inactive, trying with new db hosts: " . pretty_json($hosts));
				continue;
			}

			// 		$host = 'myapp';
			$link = @new mysqli($host, $params['MYSQL_USER'], $params['MYSQL_PASS'], $params['MYSQL_DB'], $params['MYSQL_PORT']);

			if ( $link->connect_errno ) {
				if ( function_exists('apc_exists') && !apc_exists($hkey) ) {
					$HOST_RETRY_COOLTIME = 5; // seconds
					$cooltime = $HOST_RETRY_COOLTIME;

					apc_store($hkey, $cooltime, $cooltime);

					$hosts = array_values(array_diff($hosts, array($host)));
					elog("dbhost: $host is inactive, trying with new db hosts: " . pretty_json($hosts));
					continue;
				}
			}
			break; // everything is okay
		}

		#elog("host_info: " . $link->host_info);
	} else {
		$host = $params['MYSQL_HOSTS'][0]; // always pick first host
		$link = @new mysqli($host, $params['MYSQL_USER'], $params['MYSQL_PASS'], $params['MYSQL_DB'], $params['MYSQL_PORT']);
	}

	if ($link && $link->connect_errno) {
		$emsg = "failed to connect to mysql: (" . $link->connect_errno . ") " . $link->connect_error;
		elog($emsg);

		http_response_code(500);
		exit;
	}

	#$link->set_charset("utf8"); // server will serve as utf8
	#$link->autocommit(false); // query always with TxnBlock
	#$link->query('SET foreign_key_checks = 0;');
	if ( $timezone_offset )
		tune_timezone($link, $timezone_offset);

	return $link;
}

global $global_redis; // As Redis->pconnect seems to have problems
/**
 * Make redis connection object
 */
function conn_redis() {
	global $REDIS_HOSTS, $REDIS_PORTS;

	if (false) {
		// nrk/Predis, deprecated at 2013.07.25 by performance issue
		$redis_config = array(
				'host' => 'ew_tb_was_3p',
				'read_write_timeout' => 60,
				'connection_persistent' => true,
		);

		require_once 'Predis/Autoloader.php';
		Predis\Autoloader::register();

		$redis = new Predis\Client($redis_config);
	} else {
		global $global_redis;

		$host = $REDIS_HOSTS[0];
		$port = $REDIS_PORTS[0];

		try {
			if ( $global_redis ) {
				#       $global_redis->ping();
				return $global_redis;
			}
		} catch (RedisException $e) {
			$global_redis = null;
		}

		if ( !$global_redis ) {
			$redis = new Redis();
			$redis->connect($host, $port);
			#$redis->pconnect($host, $port);
			#$redis->setOption(Redis::OPT_READ_TIMEOUT, "60.0");
			$global_redis = $redis;
		}
	}

	return $redis;
}

function tune_timezone($mysqlconn, $timezone = null) {
	// 	$rs = $mysqlconn->query("SELECT IF(@@session.time_zone = 'SYSTEM', @@system_time_zone, @@session.time_zone) AS session_timezone;");
	// 	$tzstr = ms_fetch_single_cell($rs);
}

/**
 * A very basic php-session handler using Predis
 * @deprecated php-redis will be used instead of Predis by performance issue at 2013.0725
 * @author http://phpmaster.com/saving-php-sessions-in-redis/
 *
 */
class RedisSessionHandler implements \SessionHandlerInterface
{
	public $ttl = 1800; // 30 minutes default
	protected $db;
	protected $prefix;

	public function __construct(Predis\Client $db = null, $prefix = 'PHPSESSID:') {
		if (is_null($db))
			$db = conn_redis();

		$this->db = $db;
		$this->prefix = $prefix;
	}

	public function open($savePath, $sessionName) {
		// No action necessary because connection is injected
		// in constructor and arguments are not applicable.
	}

	public function close() {
		$this->db = null;
		unset($this->db);
	}

	public function read($id) {
		$id = $this->prefix . $id;
		$sessData = $this->db->get($id);
		$this->db->expire($id, $this->ttl);
		return $sessData;
	}

	public function write($id, $data) {
		$id = $this->prefix . $id;
		$this->db->set($id, $data);
		$this->db->expire($id, $this->ttl);
	}

	public function destroy($id) {
		$this->db->del($this->prefix . $id);
	}

	public function gc($maxLifetime) {
		// no action necessary because using EXPIRE
	}
}

function common_param_GET($map, $key, $default_value) {
	if ( isset($map[$key]) )
		return $map[$key];
	return $default_value;
}

/**
 * Safely get value with default from $_GET
 * @param unknown $key
 * @param string $default_value
 */
function queryparam_GET($key, $default_value = null) {
	return common_param_GET($_GET, $key, $default_value);
}

/**
 * Safely get value with default from $_GET/$_POST/php:input
 * @param unknown $key
 * @param string $default_value
 */
function queryparam_fetch($key, $default_value = null) {
	// check GET first
	$val = common_param_GET($_GET, $key, $default_value);
	if ($val != $default_value) {
		if ( is_string($val) )
			$val = urldecode($val);
		return $val;
	}

	// next, try POST (with form encode)
	$val = common_param_GET($_POST, $key, $default_value);
	if ($val != $default_value)
		return $val;

	// next, try to understand rawinput as a json string

	// check pre-parsed object
	if ( !isset($GLOBALS['phpinput_parsed']) ) {
		$GLOBALS['phpinput'] = file_get_contents("php://input");
		if ( $GLOBALS['phpinput'] ) {
			$GLOBALS['phpinput_parsed'] = json_decode($GLOBALS['phpinput'], true);
			if ( $GLOBALS['phpinput_parsed'] ) {
				elog("param is available as: " . $GLOBALS['phpinput']);
			}
		}
	}

	// check key in parsed object
	if ( isset($GLOBALS['phpinput_parsed']) ) {
		if ( isset($GLOBALS['phpinput_parsed'][$key]) ) {
			$val = $GLOBALS['phpinput_parsed'][$key];
			if ($val != $default_value)
				return $val;
		}
	}

	return $default_value;
}

function queryparam_fetch_int($key, $default_value = null) {
	$res = queryparam_fetch($key);
	if ( strlen($res) > 0 && !is_null(int_check($res)) ) return $res;
	return $default_value;
}

function queryparam_fetch_float($key, $default_value = null) {
	$res = queryparam_fetch($key);
	if ( strlen($res) > 0 && !is_null(float_check($res)) ) return $res;
	return $default_value;
}

function queryparam_fetch_number($key, $default_value = null) {
	$res = queryparam_fetch($key);
	if ( strlen($res) > 0 && !is_null(number_check($res)) ) return $res;
	return $default_value;
}

/**
 * Safely get value with default from $_SESSION
 * @param unknown $key
 * @param string $default_value
 */
function session_GET($key, $default_value = null) {
	if ( !isset($_SESSION) )
		return $default_value;
	return common_param_GET($_SESSION, $key, $default_value);
}

/**
 * Check session for login, if not, render error
 * @return unknown
 */
function login_check($render_error_on_fail = true, $ukey = 'user_id') {

	if ( dev && $ukey == 'user_id') {

		$user_id = queryparam_fetch_int('user_id'); // for test, implicit login
		if ( !session_GET('user_id') && $user_id ) {
			$tb = new TxnBlock();

			$query = "SELECT * FROM user WHERE user_id = $user_id";
			if ( !($rs = $tb->query($query)) )
				render_error();
			$user = ms_fetch_one($rs);

			$general = general::select($tb, null, "user_id = $user_id");

			if ( !$tb->end_txn() )
				render_error();

			$_SESSION['user_id'] = $user['user_id'];
			$_SESSION['general_id'] = $general['general_id'];
			$_SESSION['user'] = $user;
			$_SESSION['general'] = $general;
			$_SESSION['country'] = $general['country'];
		}
	}

	global $SYSTEM_EVENT_SOURCE_IPS;

	$remote_addr = @$_SERVER['REMOTE_ADDR'] ?: null;

	if ( !($user_id = session_GET($ukey)) ) {
		if ( empty($user_id) && in_array($remote_addr, $SYSTEM_EVENT_SOURCE_IPS) ) {
			elog("REMOTE_ADDR: [$remote_addr], bypassed authentication");

			$acl = queryparam_fetch('acl');
			if ( !empty($acl) ) {
				elog("granting [$acl] tokens to acl...");

				$tokens = explode(',', $acl);
				$old_tokens = empty($_SESSION['acl']) ? [] : explode(',', $_SESSION['acl']);
				$new_tokens = array_unique(array_merge($tokens, $old_tokens));
				$_SESSION['acl'] = implode(',', $new_tokens) ;
			}

			elog("complete acl: " . $_SESSION['acl']);

			return null;
		}

		if ( $render_error_on_fail )
			render_error("login first: $remote_addr", FCODE(30109));
	}

	// 	if ( dev ) elog("REQUEST: " . $_SERVER['REQUEST_URI']);

	return $user_id;
}

/**
 * convert(encode) $obj to pretty json string
 * @param mixed $obj
 * @param int $options additional options for json_encode
 * @return string
 */
function pretty_json($obj, $options = 0) {
	return @json_encode($obj, JSON_PRETTY_PRINT|JSON_NUMERIC_CHECK|$options);
}

/**
 * Render a page for 'ok' or 'error' with message and map
 * @param string $code
 * @param string $message
 * @param string $map
 */
function render_page($code = "error", $message = '', $map = null, $exit_on_return = true) {
	$result['code'] = $code;
	$result['message'] = $message;
	if ( $map ) {
		foreach ($map as $k => $v) {
			$result[$k] = $v;
		}
	}

	if ( dev || $code == 'error' ) {
		$backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
		$filtered_bt = array();
		foreach ($backtrace as $trace ) {
			if ( !strstr($trace['function'], 'render_page') )
				array_push($filtered_bt, $trace);
		}

		$result['backtrace'] = $filtered_bt;
		if ( $code == 'error' ) {
			elog("render_error: " . $result['message']);
			elog(" +-> backtrace: " . pretty_json($filtered_bt));
		}
	}

	$js = pretty_json($result);
	echo $js;
	if ( $exit_on_return )
		exit;
	elog("non_exit_on_return emitted bytes: " . strlen($js));
}

/**
 * render error with optional $message and $map
 * @param string $message
 * @param string $map
 */
function render_error($message = 'error by unknown reason', $map = null) {
	render_page("error", $message, $map);
}

/**
 * render ok with optional $message and $map
 * @param string $message
 * @param string $map
 */
function render_ok($message = 'success', $map = null) {
	render_page("ok", $message, $map);
}

/**
 * render assertion error with optional $message only if $expr is_null or false(of bool)
 * @param mixed|bool $expr
 * @param string $message
 * @param array $map
 */
function assert_render($expr, $message = '', $map = null) {
	if ( is_null($expr) || (is_bool($expr) && !$expr) ) {
		render_error("assertion:violation:[$message]: $expr", $map);
	}
}

function elog($message) {
	global $TAG;
	$gid = session_GET('general_id', '');
	error_log(sprintf("[%s:gid:%2d] ", $TAG, $gid) . $message);
}

/**
 * Mysql transactional query object
 * Intended for RAII pattern
 * @author hjyun
 * @example
 *	{
 *		$tb = new TxnBlock();
 *		$tb->query('...');
 *		$tb->query('...');
 *	}	// Here, TxnBlock automatically commit by Class destructor or rollback if any error was found
 *		// Of course you can end transaction manully($tb->end_txn()) or by '$tb = null';
 *
 */
class TxnBlock {
	private $recommit_on_deadlock = true;
	private $debug = true;
	private $explain = false; // run EXPLAIN-query before SELECT-query
	private $mysql_client; // mysqli object
	private $started = false; // Transaction was triggered by a query
	private $failed = false; // At least one query has failed. No further query will be run
	private $querys = array(); // Executed queries. For history
	private $querys_info = array(); // detailed info about queries

	private function reset_internal() {
		$this->started = false;
		$this->failed = false;
		$this->querys = array();
		$this->querys_info = array();
		$this->querys_info['exec_times'] = array();
		$this->querys_info['explain_results'] = array();
	}

	function dump_query_stat() {
		$i = 0;
		foreach ( $this->querys as $query ) {
			elog(sprintf("query[%02d][%9.6f]: %s", $i, $this->querys_info['exec_times'][$i], $query));

			if ( $this->explain && $this->querys_info['explain_results'][$i] ) {
				$explain_result = $this->querys_info['explain_results'][$i];
				$js_explain_result = @json_encode($explain_result, JSON_PRETTY_PRINT|JSON_NUMERIC_CHECK);

				if ( $js_explain_result && strpos($js_explain_result, '"type": "ALL"') > 0 )
					$js_explain_result .= " type:ALL QUERY: $query";
				elog(" +- EXPLAIN: " . $js_explain_result);
			}

			$i++;
		}
	}

	function __construct($mysql_client = null) {
		$this->mysql_client = $mysql_client;
		if ( !$this->mysql_client )
			$this->mysql_client = conn_mysql();

		$this->reset_internal();
	}
	function __destruct() {
		try {
			if ( sizeof($this->querys) == 0 )
				return ;

			if ( $this->mysql_client != null ) {
				// 			$this->end_txn();
				if ( $this->started ) {
					elog("some queries were successfully executed but no end_txn() was called, rollbacks");
					// 					elog(implode("\n", debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS)));

					$this->dump_query_stat();

					$bgn = microtime(true);

					$this->mysql_client->rollback();

					$end = microtime(true);
					$this->querys_info['exec_times_txn']['commit'] = 0.0;
					$this->querys_info['exec_times_txn']['rollback'] = $end - $bgn;

					elog(sprintf("TXN [commit, rollback] => [%f, %f]", $this->querys_info['exec_times_txn']['commit'], $this->querys_info['exec_times_txn']['rollback']));
				}

				$this->mysql_client->close();
			}
		} catch (Exception $e ) {
			// suppress all exceptions
			elog('mysql:exception: ' . $e->getMessage());
		}
	}

	/**
	 * Get mysql client
	 * @return Ambigous <string, mysqli>
	 */
	function mc() {
		return $this->mysql_client;
	}
	/**
	 * End transaction with commit or rollback
	 * @return boolean
	 */
	function end_txn() {
		if ( sizeof($this->querys) == 0 )
			return true;

		$success = false;
		if ( $this->started ) {

			if ( $this->failed || $this->debug ) {
				$this->dump_query_stat();
			}

			$this->querys_info['exec_times_txn']['commit'] = 0.0;
			$this->querys_info['exec_times_txn']['rollback'] = 0.0;

			if ( $this->failed ) {
				$bgn = microtime(true);
				if ( !$this->mysql_client->rollback() )
					elog('rollback failed: ' . $this->mysql_client->error);
				$end = microtime(true);

				$this->querys_info['exec_times_txn']['rollback'] = $end - $bgn;
			}
			else {
				$bgn = microtime(true);

				$retried = 0;
				while (1) {
					if ( !$this->mysql_client->commit() ) {
						elog('commit failed: ' . $this->mysql_client->error);
						if ( $this->recommit_on_deadlock && strstr($this->mysql_client->error, 'Deadlock found') ) {
							$retried++;
							elog("recommits on deadlock with retried: $retried");
							throw new Exception('Deadlock found');
							//sleep(1);
							continue;
						}
					}
					else
						$success = true;
					break;
				}

				$end = microtime(true);

				if ( $retried  > 0 )
					elog("commit was eventually succeeded with {retried: $retried, success: $success}");

				$this->querys_info['exec_times_txn']['commit'] = $end - $bgn;
			}

			if ( $this->failed || $this->debug )
				elog(sprintf("TXN [commit, rollback] => [%f, %f]", $this->querys_info['exec_times_txn']['commit'], $this->querys_info['exec_times_txn']['rollback']));
		}
		$this->reset_internal();

		return $success;
	}

	/**
	 * Run query inside transaction, would fail always if any error was found before.
	 *
	 * @param string $query
	 * @param mysqlresultset $result_set
	 * @return boolean|mixed execution flag for non-SELECT query|mysql's result set
	 */
	function query($query, &$result_set = null) {
		if ( $this->failed || $this->mysql_client == null )
			return false;

		if ( !$this->started ) {
			$rs = $this->mysql_client->query('BEGIN;');

			if ( !$rs ) {
				$this->failed = true;
				elog("query failed: BEGIN: " . $this->mysql_client->error);
				return false;
			}
		}

		$explain_result = null;
		if ( $this->explain && stripos($query, 'SELECT') === 0 && stristr($query, 'FROM') ) {
			$rs = $this->mysql_client->query("EXPLAIN EXTENDED " . $query);
			$explain_result = ms_fetch_all($rs);
		}

		$mt_bgn = microtime(true);
		$rs = $this->mysql_client->query($query);
		$mt_end = microtime(true);

		if ( $rs ) {
			$this->started = true;
			$this->querys[] = $query;
			$this->querys_info['exec_times'][] = $mt_end - $mt_bgn;
			$this->querys_info['explain_results'][] = $explain_result;
		} else {
			$this->failed = true;

			elog("query failed: $query: " . $this->mysql_client->error);
		}
		if ( $result_set )
			$result_set = $rs;
		return $rs;
	}

	/**
	 * Run $query and additionally check affected_rows by that query. Returns $expected_affected_rows if matches
	 * @param string $query
	 * @param string $expected_affected_rows
	 * @return NULL|integer
	 */
	function query_with_affected($query, $expected_affected_rows = null) {
		$rs = $this->query($query);
		if ( $rs != null ) {
			if ( $expected_affected_rows != null && $expected_affected_rows != $this->mysql_client->affected_rows) {
				elog("executed query: $query");
				elog("expected affected rows: $expected_affected_rows, but " . $this->mysql_client->affected_rows);
				return null;
			}
			return $expected_affected_rows;
		}
		return null;
	}

	/**
	 * Run $query which expects multiple resultset
	 * @param array $querys array of string
	 * @return boolean|array:
	 */
	function multi_query($querys) {
		if ( is_array($querys) && sizeof($querys) == 0 )
			return true;

		if ( $this->failed || $this->mysql_client == null )
			return false;

		if ( !$this->started ) {
			$rs = $this->mysql_client->query('BEGIN;');
			if ( !$rs ) {
				$this->failed = true;
				elog("query failed: BEGIN: " . $this->mysql_client->error);
				return false;
			}
		}

		$explain_result = null;
		if ( $this->explain && 0 ) {
			$rs = $this->mysql_client->query("EXPLAIN EXTENDED " . $query);
			elog(json_encode(ms_fetch_all($rs), JSON_PRETTY_PRINT|JSON_NUMERIC_CHECK));
		}

		$querystr = '';
		foreach ($querys as $query) {
			$querystr .= trim($query, ';') . ';';
		}

		$multirs = null;

		$mt_bgn = microtime(true);
		$rs = $this->mysql_client->multi_query($querystr);
		$mt_end = microtime(true);

		if ( $rs ) {
			$multirs = array();

			do {
				/* store first result set */
				if ($result = $this->mysql_client->store_result())
					array_push($multirs, $result);
			} while ($this->mysql_client->more_results() && $this->mysql_client->next_result());

			$this->started = true;
			$this->querys[] = $querystr;
			$this->querys_info['exec_times'][] = $mt_end - $mt_bgn;
			$this->querys_info['explain_results'][] = $explain_result;
		} else {
			$this->failed = true;

			elog("queries failed: $querystr: " . $this->mysql_client->error);
		}

		if ( !$this->failed )
			return $multirs;
		return $rs;
	}

	/**
	 * Return escaped string manipulated by mysql client connection
	 * @param unknown $value
	 */
	function escape($value) {
		return $this->mysql_client->escape_string($value);
	}

	/**
	 * Return latest successful mysql's insert_id
	 */
	function insert_id() {
		return $this->mysql_client->insert_id;
	}
}

/**
 * Fetch all rows as map, do not use this for large resultset
 * @param unknown $mysql_result
 * @param string $resulttype
 * @param integer $row_limit forcibly returns null if sizeof row exceeds row_limit. unlimited on null
 * @return array resultset
 */
function ms_fetch_all($mysql_result, $resulttype = MYSQLI_ASSOC, $row_limit = null)
{
	if ( !$mysql_result )
		return null;

	if (empty($row_limit) && method_exists('mysqli_result', 'fetch_all')) # Compatibility layer with PHP < 5.3
		$res = $mysql_result->fetch_all($resulttype);
	else {
		$count = 0;
		for ($res = array(); $count <= $row_limit && $tmp = $mysql_result->fetch_array($resulttype) ; $count++)
			$res[] = $tmp;

		if ( $count > $row_limit )
			return null;
	}

	return $res;
}

/**
 * Fetch the first row from resultset as array or map
 * @param unknown $mysql_result
 * @param string $resulttype
 * @return array|NULL
 */
function ms_fetch_one($mysql_result, $resulttype = MYSQLI_ASSOC)
{
	$res = ms_fetch_all($mysql_result, $resulttype);

	if ( $res && sizeof($res) == 1 )
		return $res[0];
	else
		return null;
}

/**
 * Fetch cell(0,0) from resultset
 * @param mysql_result $mysql_result
 * @return Object|NULL
 */
function ms_fetch_single_cell($mysql_result)
{
	$res = $mysql_result->fetch_array(MYSQLI_NUM);
	if ( $res && sizeof($res) == 1)
		return $res[0];
	return null;
}

function ms_quote($expr) {
	return "'" . $expr . "'";
}

/**
 * load and parse xml file, then return as DOM object
 * @param string $filename
 * @return NULL|SimpleXMLElement
 */
function loadxml_as_dom($filename) {
	$rpath = dirname(__FILE__) . "/$filename";
	if ( !file_exists($rpath) ) {
		elog("file not exists: [$rpath]");
		return null;
	}

	$dom = simplexml_load_file($rpath);
	if (!$dom) {
		elog("failed to simplexml_load_file: [$rpath]");
		return null;
	}

	return $dom;
}

/**
 * float random with mt_rand()
 * @return number
 */
function frand($min = 0, $max = 1) {
	return $min + mt_rand() / mt_getrandmax() * ($max - $min);
}

function gamelog2($category, $type, $detail = [], $sink = null) {
	global $TAG, $user_id, $general_id;

	if ( !$sink )
		$sink = conn_redis();

	$detail['category'] = $category;
	$detail['user_id'] = $user_id;
	$detail['general_id'] = $general_id;
	$detail['type'] = $type;
	$detail['action_at_utc'] = time();

	if ( is_a($sink, 'Redis') ) {
		$redis = $sink;

		$detail['action_at_utc'] = $redis->time()[0];
		$detail['action_id'] = $redis->incr("$TAG:oplog:action_seq");
		$ejs = pretty_json($detail);
		$rkey = "$TAG:oplog:$category";
		$r = $redis->lPush($rkey, $ejs);
	}
	else if ( is_a($sink, 'TxnBlock') ) {
		$tb = $sink;

		$ejs = pretty_json($detail);
		$ejs = $tb->escape($tb);
		$query = "INSERT INTO logs (created_at, type, body) VALUES (NOW(), 1, '$ejs')";
		if ( !$tb->query_with_affected($query, 1) )
			elog("FAILED to log: " . $query);
	}
}

function gamelog($METHOD, $detail = [], $sink = null) {
	$terms = explode('::', $METHOD);
	if ( sizeof($terms) == 2 )
		gamelog2($terms[0], $terms[1], $detail, $sink);
}

/**
 * fetch key from cache (APC and redis)
 * @param string $key
 * @return mixed fetched key
 */
function fetch_from_cache($key){
	global $SYSTEM_SHOW_CACHE_METRICS, $TAG;

	$key = "$TAG:$key";

	$timer_bgn = microtime(true);

	$val = null;
	if ( function_exists('apc_fetch') ) {
		$val = apc_fetch($key);
		if ( $val ) {
			$timer_end = microtime(true);

			if ($SYSTEM_SHOW_CACHE_METRICS)
				elog("time took fetching from APC-cache for key: $key: " . ($timer_end - $timer_bgn));
		}
	} else {
		$redis = conn_redis();
		$val = $redis->get($key);

		if ( $val ) {
			$timer_end = microtime(true);

			if ($SYSTEM_SHOW_CACHE_METRICS)
				elog("time took fetching from REDIS-cache for key: $key: " . ($timer_end - $timer_bgn));
			$val = @json_decode($val, true);
		}
	}

	return $val;
}

/**
 * store key=>val into cache (APC and redis)
 * @param unknown $key
 * @param unknown $val
 * @return unknown
 */
function store_into_cache($key, $val, $ttl = CACHE_TTL){
	global $SYSTEM_SHOW_CACHE_METRICS, $TAG;

	$key = "$TAG:$key";

	$timer_bgn = microtime(true);

	if ( function_exists('apc_store') ) {
		apc_store($key, $val, $ttl);
	} else {
		$redis = conn_redis();
		$redis->setex($key, $ttl, json_encode($val, JSON_NUMERIC_CHECK));
	}

	$timer_end = microtime(true);
	if ($SYSTEM_SHOW_CACHE_METRICS)
		elog("time took caching for key: $key: " . ($timer_end - $timer_bgn));

	return $val;
}

/**
 * remove(invalidate) key at cache (APC or redis)
 * @param unknown $key
 */
function invalidate_cache($key) {
	global $SYSTEM_SHOW_CACHE_METRICS, $TAG;

	$key = "$TAG:$key";

	if ( function_exists('apc_delete') ) {
		apc_delete($key);
	} else {
		$redis = conn_redis();
		$redis->del($key);
	}
}

function store_into_redis($key, $val, $ttl = CACHE_TTL) {
	global $SYSTEM_SHOW_CACHE_METRICS, $TAG;

	$key = "$TAG:$key";

	$redis = conn_redis();
	if ( $ttl > 0 )
		$redis->setex($key, $ttl, json_encode($val, JSON_NUMERIC_CHECK));
	else
		$redis->set($key, json_encode($val, JSON_NUMERIC_CHECK));
}

function fetch_from_redis($key) {
	global $SYSTEM_SHOW_CACHE_METRICS, $TAG;

	$key = "$TAG:$key";

	$redis = conn_redis();
	$val = $redis->get($key);

	if ( $val ) {
		$val = @json_decode($val, true);
	}
	return $val;
}

function delete_at_redis($key) {
	global $SYSTEM_SHOW_CACHE_METRICS, $TAG;

	$key = "$TAG:$key";

	$redis = conn_redis();
	$redis->del($key);
}

function sadd_into_redis($key, $member) {
	global $SYSTEM_SHOW_CACHE_METRICS, $TAG;

	$key = "$TAG:$key";

	$redis = conn_redis();
	return $redis->sAdd($key, $member);
}

function sismember_of_redis($key, $member) {
	global $SYSTEM_SHOW_CACHE_METRICS, $TAG;

	$key = "$TAG:$key";

	$redis = conn_redis();
	return $redis->sismember($key, $member);
}

/**
 * Make or merge a map for formatting-code(fcode)
 * @param int $fcode
 * @param map $map
 * @return map
 */
function FCODE($fcode, $map = null) {
	if ( $map ) {
		$map['fcode'] = $fcode;
		return $map;
	}
	else
		return array('fcode'=>$fcode);
}

/**
 * Choice a key in keys with weights
 * @param unknown $keys
 * @param unknown $weights
 * @param string $cpropbs
 * @return NULL|unknown
 */
function weighted_choice($keys, $weights, &$cpropbs = null) {
	if ( count($keys) != count($weights) )
		return null;

	// make propb table
	if ( !$cpropbs ) {
		$cprobs = [0.0]; // cumulative prob
		$psum = 0.0;

		for ( $i = 0 ; $i < count($keys) ; $i++ )
			$psum += $weights[$i];
		for ( $i = 1 ; $i <= count($keys) ; $i++ )
			$cprobs[] = $cprobs[$i-1] + floatval($weights[$i-1]) / floatval($psum);

		// 	elog("TABLE: psum: $psum, cprobs: " . json_encode($cprobs));
	}

	// determine key
	$rnd = frand();
	for ( $i = 1 ; $i < count($cprobs) ; $i++ ){
		if ( $rnd <= $cprobs[$i] )
			break;
	}
	return $keys[$i-1];
}

/**
 * Join terms mainly for UPDATE, INSERT query
 * @param unknown $terms
 * @param string $out_keys
 * @param string $out_vals
 * @return Ambigous <NULL, string>
 */
function join_terms($terms, &$out_keys = null, &$out_vals = null) {

	$ovals = $okeys = [];
	$term = [];
	foreach ($terms as $k => $v) {
		if ( is_null($v) )
			$v = "NULL";

		$term[] = "`$k` = $v";

		if ( !is_null($out_keys) )
			$okeys[] = "`$k`";
		if ( !is_null($out_vals) )
			$ovals[] = $v;
	}

	if ( !is_null($out_keys) )
		$out_keys = implode(', ', $okeys);
	if ( !is_null($out_vals) )
		$out_vals = implode(', ', $ovals);

	$pairs = null;
	if ( sizeof($term) > 0 )
		$pairs = implode(', ', $term);

	return $pairs;
}

/**
 * check $var is actually integer typed, and returns null if not
 * @param unknown $var
 * @return unknown|NULL
 */
function int_check($var) {
	if ( is_numeric($var) )
		return $var;
	return null;
}

/**
 * check $var is actually float typed, and returns null if not
 * @param unknown $var
 * @return unknown|NULL
 */
function float_check($var) {
	if ( is_numeric($var) )
		return $var;
	return null;
}

/**
 * check $var is number typed, and returns null if not
 * @param unknown $var
 * @return unknown|NULL
 */
function number_check($var) {
	if ( is_numeric($var) )
		return $var;
	return null;
}

/**
 * check current connection for script is secure(saying, SSL-HTTPS) or not
 * @return boolean true on secure, false on otherwise
 */
function secure_connection() {
	if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off') {
		return true;
	}

	return false;
}

/**
 * @author manstar
 * @param utf8-string $str
 * @return number
 */
function utf8_length($str) {
	$len = strlen($str);
	for ($i = $length = 0; $i < $len; $length++) {
		$high = ord($str{$i});
		if ($high < 0x80)//0<= code <128 범위의 문자(ASCII 문자)는 인덱스 1칸이동
			$i += 1;
		else if ($high < 0xE0)//128 <= code < 224 범위의 문자(확장 ASCII 문자)는 인덱스 2칸이동
			$i += 2;
		else if ($high < 0xF0)//224 <= code < 240 범위의 문자(유니코드 확장문자)는 인덱스 3칸이동
			$i += 3;
		else//그외 4칸이동 (미래에 나올문자)
			$i += 4;
	}
	return $length;
}

/**
 * @author manstar
 * @param unknown $str
 * @param unknown $chars
 * @param string $tail
 * @return string
 */
function utf8_strcut($str, $chars, $tail = '...') {
	if (utf8_length($str) <= $chars)//전체 길이를 불러올 수 있으면 tail을 제거한다.
		$tail = '';
	else
		$chars -= utf8_length($tail);//글자가 잘리게 생겼다면 tail 문자열의 길이만큼 본문을 빼준다.
	$len = strlen($str);
	for ($i = $adapted = 0; $i < $len; $adapted = $i) {
		$high = ord($str{$i});
		if ($high < 0x80)
			$i += 1;
		else if ($high < 0xE0)
			$i += 2;
		else if ($high < 0xF0)
			$i += 3;
		else
			$i += 4;
		if (--$chars < 0)
			break;
	}
	return trim(substr($str, 0, $adapted)) . $tail;
}

require_once 'constants.php';

function make_session() {
	require_once 'constants.php';

	global $TAG, $TXN_RETRY_MAX, $SYSTEM_EXECUTION_TIME_LIMIT, $USER_LINE_THRESHOLD_TO_OFFLINE;

	$save_handler = ini_get('session.save_handler');
	$save_path = ini_get('session.save_path');
	$gc_maxlifetime = ini_get('session.gc_maxlifetime');

	// 	elog("save_handler: $save_handler, save_path: $save_path, gc_maxlifetime: $gc_maxlifetime");

	ini_set('session.gc_maxlifetime', $USER_LINE_THRESHOLD_TO_OFFLINE);
	if ( !strpos($save_path, 'prefix=') ) {
		$save_path .= "&prefix=$TAG:PHPREDIS_SESSION:";
		ini_set('session.save_path', $save_path);
	}

	// 	session_id(uniqid("$TAG:", true));
	// 	session_name(uniqid("$TAG:", true));

	// session_set_save_handler(new RedisSessionHandler()); // Session Handler using redis
	for ( $retried = 1 ; $retried <= $TXN_RETRY_MAX ; $retried++ ) {
		try {
			session_start();
			set_time_limit($SYSTEM_EXECUTION_TIME_LIMIT);
			break;
		} catch(Exception $e) {
			$emsg = $e->getMessage();
			elog("got [$emsg] exception, trying again... with [$retried / $TXN_RETRY_MAX]");
		}
	}
}

make_session();

