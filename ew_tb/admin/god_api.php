<?php
require_once '../connect.php';

class god {

	public static function resetdb($tb) {
		global $TAG, $ALLIES, $EMPIRE, $user_id, $general_id, $dev_type, $dev_uuid, $ops, $status, $country;

		$redis = conn_redis();
		
		$keys = $redis->keys("$TAG:*");
		if ( is_array($keys) ) {
			foreach ($keys as $key) {
				elog("deleting redis key: $key ...");
				$redis->delete($key);
			}
		}
		
		$query = "SHOW TABLES";
		$rows = ms_fetch_all($tb->query($query));
		
		elog(pretty_json($rows));
		$querys = [];
		
		$querys[] = "SET foreign_key_checks = 0";
		
		foreach($rows as $row) {
			foreach($row as $k => $v ) {
				elog("deleting table: $v ...");
				$querys[] = "DELETE FROM `$v`";
			}
		}
		
		$querys[] = "SET foreign_key_checks = 1";
			
		assert_render($tb->multi_query($querys));
	
		assert_render($tb->end_txn());
	
		render_ok();
	}	
	
	public static function run($tb) {
		global $TAG, $ALLIES, $EMPIRE, $user_id, $general_id, $dev_type, $dev_uuid, $ops, $status, $country;

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
	
	global $SYSTEM_OPERATOR_ALLOWED_IPS;
	if ( $remote_addr = @$_SERVER['REMOTE_ADDR'] ) {
		$SYSTEM_OPERATOR_ALLOWED_IPS = empty($SYSTEM_OPERATOR_ALLOWED_IPS) ? [] : $SYSTEM_OPERATOR_ALLOWED_IPS;
		assert_render(in_array($remote_addr, $SYSTEM_OPERATOR_ALLOWED_IPS), "you are not allowed to access: $remote_addr");
	}
	
	$ops = explode(',', $op = queryparam_fetch('op', 'get'));

	$user_id = login_check();
	$general_id = session_GET('general_id');

	if ( sizeof(array_intersect_key(['get', 'run', 'resetdb'], $ops)) ) {
		for ( $retried = 1 ; $retried <= $TXN_RETRY_MAX ; $retried++ ) {
			try {
				$tb = new TxnBlock();

			//	if ( in_array("clear", $ops) ) god::clear($tb);

				if ( dev ) {
					if ( in_array("run", $ops) ) god::run($tb);
					else if ( in_array("resetdb", $ops) ) god::resetdb($tb);					
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
