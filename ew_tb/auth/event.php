<?php
require_once '../connect.php';

class event {

	public static function clear($tb) {
		global $TAG, $ALLIES, $EMPIRE, $user_id, $general_id, $dev_type, $dev_uuid, $ops, $status, $country;

		if ( dev && false ) {
			$query = "DELETE FROM event;";
			assert_render($rs = $tb->query($query));
		}
	}

	public static function get($tb) {
		global $TAG, $ALLIES, $EMPIRE, $user_id, $general_id, $dev_type, $dev_uuid, $ops, $status, $country;
		global $event_id;

		$query = sprintf("SELECT * FROM event");
		if ( $event_id )
			$query .= " WHERE event_id = $event_id";

		assert_render($rs = $tb->query($query));

		if ( $event_id && $rs->num_rows == 0 )
			render_error("invalid:event_id: $event_id");

		if ( !$tb->end_txn() )
			render_error();

		$map['events'] = ms_fetch_all($rs);

		render_ok('success', $map);
	}

	public static function push_post($tb, $context) {
		global $TAG;

		$user_id = $context['user_id'];
		$src_id = $context['src_id'];
		$send_at = $context['send_at'];
		$dev_type = $context['dev_type'];
		$dev_uuid = $context['dev_uuid'];
		$body = $context['body'];

		if ( stripos($send_at, 'now') !== false ) 
			$send_at_utc = time();
		else
			$send_at_utc = DateTime::createFromFormat('Y-m-d H:i:s', $send_at)->getTimestamp();

		$terms = [];
		$terms['user_id'] = $user_id;
		$terms['src_id'] = ms_quote($src_id);
		$terms['queued_at'] = 'NOW()';
		$terms['send_at'] = ms_quote($send_at);
		$terms['dev_type'] = $dev_type;
		$terms['dev_uuid'] = ms_quote($dev_uuid);
		$terms['body'] = ms_quote($tb->escape(pretty_json($body)));

		$keys = $vals = [];
		join_terms($terms, $keys, $vals);

		$query = "INSERT INTO pushes ($keys) VALUES ($vals)";
		if  ( $tb->query_with_affected($query, 1) ) {
			$pid = $tb->mc()->insert_id;

			// notify to redis
			$src_type = explode(':', $src_id)[0];
			$notify_key = "$TAG:events:$src_type";
			$pid_pool_key = "$TAG:events:$src_type:pid_pool";

			$redis = conn_redis();

			$redis->multi();

			$redis->zAdd($pid_pool_key, $send_at_utc, $pid);

			// signal the waiter
			$redis->lPush($notify_key, time());

			$redis->exec();

			elog("posted: {pid:$pid, user_id:$user_id, src_type:$src_type, send_at_utc: $send_at_utc} to pushes");
		}
	}

	public static function push_cancel($tb, $src_id) {
		global $TAG;

		$query = "SELECT pid FROM pushes WHERE src_id = '$src_id' AND sent = FALSE AND send_at > NOW()";
		$push = ms_fetch_one($tb->query($query));

		if ( !empty($push['pid']) ) {
			$pid = $push['pid'];
			$query = "DELETE FROM pushes WHERE pid = $pid";
			if ( $tb->query($query) ) {
				$affected = $tb->mc()->affected_rows;
				elog("cancelled $affected pushes from queue by src_id: $src_id");
			}

			$src_type = explode(':', $src_id)[0];
			$pid_pool_key = "$TAG:events:$src_type:pid_pool";

			$redis = conn_redis();
			$redis->zRem($pid_pool_key, $pid);
		}
	}

	private static function send_via_apn($deviceToken, $collapseKey, $messageText) {

	}

	private static function send_via_gcm($deviceToken, $collapseKey, $messageText)
	{
		// 		global $SYSTEM_GCM_APPKEY;
		$SYSTEM_GCM_APPKEY = 'GET_THIS_FROM: https://code.google.com/apis/console/b/0/';

		$headers = array('Authorization:key=' . $SYSTEM_GCM_APPKEY);
		$data = array(
				'registration_id' => $deviceToken,
				'collapse_key' => $collapseKey,
				'data.message' => $messageText);

		$ch = curl_init();

		curl_setopt($ch, CURLOPT_URL, "https://android.googleapis.com/gcm/send");
		if ($headers)
			curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $data);

		$response = curl_exec($ch);
		$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		if (curl_errno($ch)) {
			//request failed
			return false;//probably you want to return false
		}
		if ($httpCode != 200) {
			//request failed
			return false;//probably you want to return false
		}
		curl_close($ch);

		return $response;
	}
	public static function send_pushes() {
		global $TAG;

		$types = ['troop', 'general', 'officer', 'item'];
		$src_type = queryparam_fetch('src_type');
		assert_render(in_array($src_type, $types), "invalid:src_type:$src_type");

		$my_id = strval(microtime(true));
		$bot_key = "$TAG:push_bot_alive:$src_type";

		$push_conn_ios = null;
		$push_conn_and = null;
		$redis = null;

		$PUSH_BOT_HEARTBEAT_PERIOD = 10;
		$PUSH_BOT_PROCESS_COUNT = 10;
		$PUSH_BOT_BRPOP_TIMEOUT = 5;

		while (1) {
			$redis = $redis ? $redis : conn_redis();

			$bot_id = $redis->get($bot_key);
			if ( $bot_id && $bot_id != $my_id ) {
				$emsg = "another push bot($bot_id) for src_type: $src_type seems to be alive (my_id: $my_id)";
				if ( $bot_id == 'die' )
					$redis->del($bot_key);
				elog($emsg);
				render_error($emsg);
			}

			while (1) {
				// 				set_time_limit(60); // reset execution timer of php script // TODO: unlock me on release

				$bot_id = $redis->get($bot_key);
				if ( $bot_id && $bot_id != $my_id ) {
					$emsg = "another push bot($bot_id) for src_type: $src_type seems to be alive (my_id: $my_id)";
					if ( $bot_id == 'die' )
						$redis->del($bot_key);
					elog($emsg);
					render_error($emsg);
				}
				$redis->setex($bot_key, $PUSH_BOT_HEARTBEAT_PERIOD, $my_id); // renew heartbeat
					
				// wait notify
				$notify_key = "$TAG:events:$src_type";
				$pid_pool_key = "$TAG:events:$src_type:pid_pool";

				// wait signal
				if ( !$redis->brPop($notify_key, $PUSH_BOT_BRPOP_TIMEOUT) )
					; // timed out

				$pid_utcs = $redis->zRangeByScore($pid_pool_key, "-inf", '+inf', array('withscores' => TRUE, 'limit' => array(0, $PUSH_BOT_PROCESS_COUNT)));
				if ( sizeof($pid_utcs) == 0 ) {
					sleep(1);
					continue;
				}

				$head_key = array_keys($pid_utcs)[0];

				elog(pretty_json($pid_utcs));
				$now = time();
				if ( $now < $pid_utcs[$head_key] ) {
					elog("now($now) < pid_utcs[head_key].utc(".$pid_utcs[$head_key]."), should wait more ...($src_type)");
					continue;
				}

				// and also discard obsolute pids from pid_pool
				$redis->zRemRangeByScore($pid_pool_key, '-inf', "$now)");

				$tb = new TxnBlock();

				$query = "SELECT * FROM pushes WHERE sent = FALSE AND send_at <= NOW() ORDER BY send_at LIMIT $PUSH_BOT_PROCESS_COUNT";
				$pushes = ms_fetch_all($tb->query($query));
				if ( sizeof($pushes) == 0 ) {
					// 					elog("sizeof(pushes) == 0");
					$tb->end_txn();
					unset($tb);
					continue;
				}

				$sent_pids = [];
				foreach ($pushes as $push) {
					$pid = $push['pid'];

					assert_render($push['dev_type'] == 1); // TODO: support also ios (dev_type==2)

					elog("processing pid: $pid ... ");

					if ( $redis->zScore($pid_pool_key, $pid) ) {
						// send push
						elog("sending push for: $pid");

						$redis->zRem($pid_pool_key, $pid);
					}

					$sent_pids[] = $pid;
				}

				$joined_pids = implode(',', $sent_pids);
				$query = "UPDATE pushes SET sent = TRUE WHERE pid IN ($joined_pids)";
				assert_render($tb->query($query));
				assert_render($tb->end_txn());

				elog("processed some pushes: " . sizeof($pushes));
			}
		}
	}
}

if ( strstr($_SERVER["REQUEST_URI"], basename(__FILE__)) ) {

	$ops = explode(',', $op = queryparam_fetch('op', 'get'));

	if ( sizeof(array_intersect_key(['send_pushes'], $ops)) ) {
		for ( $retried = 1 ; $retried <= $TXN_RETRY_MAX ; $retried++ ) {
			try {
				// note that we dont have TxnBlock here

				if ( in_array("send_pushes", $ops) ) event::send_pushes();

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
