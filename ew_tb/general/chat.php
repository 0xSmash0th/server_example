<?php
require_once '../connect.php';
require_once '../general/general.php';
require_once '../army/officer.php';
require_once '../auth/register.php';

function chat_subscribe_handler($redis, $chan, $msg) {
	global $SYSTEM_EXECUTION_TIME_LIMIT;
	$chat = @json_decode($msg, true) ?: null;
	if ($chat) {
		$chats = ['chats'=>[$chat]];
		$msg = pretty_json($chats);
		echo $msg;
		elog("chat_subscribe_handler sent: $msg");
		flush(); ob_flush();
	}
	set_time_limit($SYSTEM_EXECUTION_TIME_LIMIT); // extend limit if we've got message
}

class chat {

	public static function get_badwords($tb = null) {
		global $TAG;

		if ( $val = fetch_from_redis('constants:badwords') )
			return $val;

		$timer_bgn = microtime(true);

		elog("retreiving badword from chat_badword ...");

		$badwords = [];

		$internal_tb = !$tb;
		if ( $internal_tb )
			$tb = new TxnBlock();

		$query = "SELECT badword FROM chat_badword";
		$rows = ms_fetch_all($tb->query($query));

		if ( $internal_tb )
			assert_render($tb->end_txn());

		elog("rows: " . pretty_json($rows));

		foreach($rows as $row)
			$badwords[] = $row['badword'];

		$timer_end = microtime(true);

		global $SYSTEM_SHOW_CACHE_METRICS;
		if ($SYSTEM_SHOW_CACHE_METRICS)
			elog("time took badwords for chat::get_badwords(): " . ($timer_end - $timer_bgn));

		store_into_redis('constants:badwords', $badwords);

		elog("badwords: " . json_encode($badwords));

		return $badwords;
	}

	public static function select_all($tb, $select_expr = null, $where_condition = null, $post_where = null) {
		global $user_id, $general_id;
		global $CHAT_FETCH_LIMIT;

		$offset = queryparam_fetch_int('offset', 0);

		if ( $select_expr == null )
			$select_expr = "*";
		if ( $where_condition == null )
			$where_condition = "WHERE general_id = $general_id";
		else
			$where_condition = "WHERE general_id = $general_id AND ($where_condition)";

		$post_where = $post_where ?: "ORDER BY chat_id DESC LIMIT $CHAT_FETCH_LIMIT OFFSET $offset";

		$query = "SELECT $select_expr FROM chat_force $where_condition /*BY_HELPER*/";
		assert_render($rows = ms_fetch_all($tb->query($query)));

		$json_keys = [];

		for ( $i = 0 ; $i < count($rows) ; $i++ ) {
			$cols = $rows[$i];
			foreach ( $json_keys as $json_key ) {
				if ( array_key_exists($json_key, $cols) && $cols[$json_key] ) {
					$js = @json_decode($cols[$json_key], true);
					if ( $js )
						$cols[$json_key] = $js;
					else
						$cols[$json_key] = null;
				}
			}
			$rows[$i] = $cols;
		}

		return $rows;
	}

	public static function select($tb, $select_expr = null, $where_condition = null) {
		$rows = chat::select_all($tb, $select_expr, $where_condition);
		if ( $rows && count($rows) == 1 )
			return $rows[0];
		return null;
	}

	public static function recover($tb, $redis) {
		global $TAG, $ALLIES, $EMPIRE, $user_id, $general_id, $dev_type, $dev_uuid, $ops, $status, $country;
		global $CHAT_FETCH_LIMIT;

		elog("RECOVER starts: " . __METHOD__);
		// notice:system[:<locale>]
		// notice:event[:<locale>]


		// chat:allies,empire,neutral
		$offset = 0;
		$limit = $CHAT_FETCH_LIMIT;
		$FMAP = ['allies', 'empire', 'neutral'];

		foreach([ALLIES, EMPIRE, NEUTRAL] as $recv_force) {
			$force_name = $FMAP[$recv_force-1];
			$ckey = "$TAG:chat:" . $FMAP[$recv_force-1];

			elog("recovering [chat:$force_name] ...");

			$redis->del($ckey);

			$query = "SELECT chat_force.*, UNIX_TIMESTAMP(chat_force.created_at) AS created_at_utc, ";
			$query .= "general.name AS username FROM chat_force NATURAL JOIN general ";
			$query .= "WHERE recv_force = $recv_force ORDER BY chat_id DESC ";
			$query .= "LIMIT $limit OFFSET $offset";

			assert_render($rs = $tb->query($query));
			$chats = ms_fetch_all($rs) ?: [];

			foreach($chats as $chat) {
				$context = [];
				$context['username'] = $chat['username'];
				$context['general_id'] = $chat['general_id'];
				$context['send_force'] = $chat['send_force'];
				$context['recv_force'] = $recv_force;
				$context['body'] = $chat['body'];
				$context['created_at_utc'] = $chat['created_at_utc'];
				$jsc = pretty_json($context);

				$redis->rPush($ckey, $jsc); // note that NOT lPush BUT rPush
			}
		}

		elog("RECOVER finished: " . __METHOD__);
	}

	public static function chat_badword_list($tb) {
		global $TAG, $ALLIES, $EMPIRE, $user_id, $general_id, $dev_type, $dev_uuid, $ops, $status, $country;

		if ( dev && queryparam_fetch_int('insure_sample') > 0 ) {
			$words = ['bad', 'words'];
			foreach ($words as $word) {
				// as clustered mysql sees deadlock on 'INSERT IGNORE'
				$query = "SELECT * FROM chat_badword WHERE badword = '$word'";
				if ( !ms_fetch_one($tb->query($query)) ) {
					$query = "INSERT INTO chat_badword (opuser_id, badword, created_at) VALUES (NULL, '$word', NOW())";
					$tb->query($query);
				}
			}

			// invalidate badword on redis
			delete_at_redis('constants:badwords');

			$chat_badword_list = chat::get_badwords($tb);
		} else
			$chat_badword_list = chat::get_badwords();

		$map['chat_badword_list'] = $chat_badword_list;

		assert_render($tb->end_txn());

		render_ok('success', $map);
	}

	public static function chat_badword_filter($tb, $body, $replace_with = '*',  $bad_words = null) {
		// badwords filtering here

		if ( !($body && strlen($body) > 0) )
			return $body;

		if ( !$bad_words )
			$bad_words = chat::get_badwords();

		$replaces = [];
		foreach ($bad_words as $word)
			$replaces[] = str_repeat($replace_with, strlen($word));

		$body = str_ireplace($bad_words, $replaces, $body);

		return $body;
	}


	public static function clear($tb) {
		global $TAG, $ALLIES, $EMPIRE, $user_id, $general_id, $dev_type, $dev_uuid, $ops, $status, $country;

		if ( dev ) {
			$query = "DELETE FROM chat WHERE general_id = $general_id";
			assert_render($tb->query($query));
		}
	}

	public static function get($tb) {
		global $TAG, $ALLIES, $EMPIRE, $user_id, $general_id, $dev_type, $dev_uuid, $ops, $status, $country;
		global $CHAT_FETCH_LIMIT;

		$country = session_GET('country');
		$recv_force = queryparam_fetch_int('recv_force');
		if ( $recv_force != NEUTRAL ) {
			if ( !(dev && queryparam_fetch_int('ignore_force') > 0) )
				$recv_force = $country;
		}
		$stream = queryparam_fetch_int('stream', 0) > 0 ? 1 : 0;
		$offset = queryparam_fetch_int('offset', 0);
		$limit = queryparam_fetch_int('limit', 0);
		$limit = $limit > 0 ? $limit : $CHAT_FETCH_LIMIT;

		$chats = [];

		$redis = conn_redis();

		// take care ban_list here
		$cbkey = "$TAG:users:user_id=$user_id:chat_ban_list";
		$chat_ban_list = @json_decode($redis->get($cbkey), true) ?: null;
		if ( !$chat_ban_list ) { // recover from db
			// user=<user_id>:chat_ban_list
			$general = general::select($tb, 'chat_ban_ids');
			$chat_ban_list = $chat_ban_ids = $general['chat_ban_ids'] ?: [];

			if ( sizeof($chat_ban_ids) > 0 ) {
				$ids = implode(',', $chat_ban_ids);
					
				$query = "SELECT general_id, country, name AS username FROM general WHERE general_id IN ($ids)";
				assert_render($rs = $tb->query($query));
				$chat_ban_list = ms_fetch_all($rs) ?: [];
			}
			$redis->setex($cbkey, CACHE_TTL, pretty_json($chat_ban_list));
		}

		$chat_ban_gids = [];
		foreach ($chat_ban_list as $e) {
			if ( !empty($e['general_id']) )
				$chat_ban_gids[] = $e['general_id'];
		}

		$FMAP = ['allies', 'empire', 'neutral'];
		$ckey = "$TAG:chat:" . $FMAP[$recv_force-1];

		$chats = $redis->lrange($ckey, $offset, $limit-1);
		if ( $chats && is_array($chats) && sizeof($chats) > 0 ) {
			foreach ($chats as &$chat) {
				$chat = @json_decode($chat, true) ?: []; // parse jsons
			}
		}

		// fetch from DB if redis was failed
		if ( queryparam_fetch_int('fromdb') > 0 || empty($chats) ) {
			$query = "SELECT chat_force.*, UNIX_TIMESTAMP(chat_force.created_at) AS created_at_utc, ";
			$query .= "general.name AS username FROM chat_force NATURAL JOIN general ";
			$query .= "WHERE recv_force = $recv_force ORDER BY chat_id DESC ";
			$query .= "LIMIT $limit OFFSET $offset";

			assert_render($rs = $tb->query($query));
			$chats = ms_fetch_all($rs) ?: [];

			// needs badword filterings
			if ( !(queryparam_fetch_int('no_badwords_filter') > 0) ) {
				$badwords = chat::get_badwords();
				foreach ($chats as &$chat) {
					$chat['body'] = chat::chat_badword_filter($tb, $chat['body'], '*', $badwords);
				}
			}
		} else
			elog("chats were retreived from redis: " .sizeof($chats));

		// ban filtering here
		if ( sizeof($chat_ban_gids) > 0 ) {
			elog("ban filtering with " . pretty_json($chat_ban_gids));
			$new_chats = [];
			foreach ($chats as $chat) {
				if ( !in_array($chat['general_id'], $chat_ban_gids) )
					$new_chats[] = $chat;
			}
			$chats = $new_chats;
		}

		$map['chats'] = $chats;

		// get notices
		$map['notices'] = [];

		$user = session_GET('user');
		if ( $user ) {
			global $MARKET_TYPE_MAP;
			$market_type = $MARKET_TYPE_MAP[$user['market_type']];
			$force = session_GET('country') == ALLIES ? "allies" : "empire";

			$query = "SELECT value FROM config WHERE name = 'notices'";
			$row = ms_fetch_one($tb->query($query));
			$js = @json_decode($row['value'], true) ?: [];

			if ( !empty($js[$market_type][$force]) ) {
				foreach ($js[$market_type][$force] as $k => $v) {
					$map['notices'][] = $v;
				}
			}
		}

		assert_render($tb->end_txn());

		if ( !$stream )
			render_ok('success', $map);

		// streaming
		$channel = "$TAG:chat:channel:" . $FMAP[$recv_force-1];

		ob_implicit_flush(true);
		flush(); ob_flush();
		render_page('ok', 'success', $map, false);
		flush(); ob_flush();

		try {
			elog("user_id: $user_id begins to subscribe on $recv_force ...");
			$redis->subscribe([$channel], 'chat_subscribe_handler');
		} catch (RedisException $e) {
			$emsg = $e->getMessage();
			elog("got [$emsg] exception on chat_subscribe_handler");
		}

		while(0) {
			sleep(5);
			elog("chat:get:stream:slept ...");
		}

		elog("chat:get:stream:quits");
		// 		render_ok();
		exit;
	}

	public static function notice($tb) {
		global $TAG, $ALLIES, $EMPIRE, $user_id, $general_id, $dev_type, $dev_uuid, $ops, $status, $country;
		global $CHAT_SEND_COOLTIME, $CHAT_SEND_COST_GOLD;
		global $NOTICE_TYPE_SYSTEM, $NOTICE_TYPE_EVENT;

		assert_render(auth::acl_check(['DEPRECATING']), "invalid:acl"); // check ACL

		$notice_type = queryparam_fetch_int('notice_type', 1);
		$body = queryparam_fetch('body') ?: '';
		$body = trim($body);

		assert_render($notice_type == $NOTICE_TYPE_SYSTEM || $notice_type == $NOTICE_TYPE_EVENT, "invalid:notice_type:$notice_type");
		assert_render(strlen($body) > 0, "invalid:body:empty");

		$ejs = $tb->escape($body);

		if ( $notice_type == $NOTICE_TYPE_SYSTEM )
			$query = "REPLACE INTO config (name, value) VALUES ('notice_system', '$ejs')";
		else if ( $notice_type == $NOTICE_TYPE_EVENT )
			$query = "REPLACE INTO config (name, value) VALUES ('notice_event', '$ejs')";

		assert_render($tb->query($query));

		$query = "SELECT * FROM config WHERE name = 'notice_system' OR name = 'notice_event'";
		$rows = ms_fetch_all($tb->query($query));

		$map['notices'] = ['notice_system'=>null, 'notice_event'=>null];

		foreach ($rows as $row) {
			$map['notices'][$row['name']] = $row['value'];
		}

		assert_render($tb->end_txn());

		render_ok('notice set', $map);
	}

	public static function send($tb) {
		global $TAG, $ALLIES, $EMPIRE, $user_id, $general_id, $dev_type, $dev_uuid, $ops, $status, $country;
		global $CHAT_SEND_COOLTIME, $CHAT_SEND_COST_GOLD, $CHAT_SEND_LIMIT;

		$country = session_GET('country');
		$username = session_GET('username');

		$recv_force = queryparam_fetch_int('recv_force');
		if ( $recv_force != NEUTRAL ) {
			if ( !(dev && queryparam_fetch_int('ignore_force') > 0) )
				$recv_force = $country;
		}
		$send_force = $country;

		$FMAP = ['allies', 'empire', 'neutral'];
		$ckey = "$TAG:chat:" . $FMAP[$recv_force-1];

		$body = queryparam_fetch('body');
		assert_render($body, "invalid:body");
		if ( strlen($body) > $CHAT_SEND_LIMIT )
			render_error("message length exceeded limit: $CHAT_SEND_LIMIT", FCODE(25103));

		// cooltime filter
		$redis = conn_redis();

		$mkey = "$TAG:users:user_id=$user_id:chat_mute:" . $FMAP[$recv_force-1];
		if ( $redis->exists($mkey) ) {
			// mkey is already set
			if ( !(dev && queryparam_fetch_int('ignore')) )
				render_error("cannot send by cooltime, try again in seconds", FCODE(25101));
		}
		$redis->setex($mkey, $CHAT_SEND_COOLTIME, $CHAT_SEND_COOLTIME);

		// cost filter
		$general = general::select($tb, 'gold, NOW() AS now, UNIX_TIMESTAMP(NOW()) AS now_utc');
		if ( $general['gold'] < $CHAT_SEND_COST_GOLD ) {
			render_error("not enough gold: need: $CHAT_SEND_COST_GOLD", FCODE(25102));
		}

		$terms = [];
		$terms['general_id'] = $general_id;
		$terms['send_force'] = $send_force;
		$terms['recv_force'] = $recv_force;
		$terms['created_at'] = ms_quote($general['now']);
		$terms['body'] = ms_quote($tb->escape($body));
		$keys = $vals = [];
		join_terms($terms, $keys, $vals);
		$query = "INSERT INTO chat_force ($keys) VALUES ($vals)";
		assert_render($tb->query($query));

		$chat_id = $tb->mc()->insert_id;

		$terms = [];
		$terms['gold'] = "gold - $CHAT_SEND_COST_GOLD";
		$pairs = join_terms($terms);
		$query = "UPDATE general SET $pairs WHERE general_id = $general_id";
		assert_render($tb->query($query));

		$body_filtered = chat::chat_badword_filter($tb, $body);

		$context = [];
		$context['chat_id'] = $chat_id;
		$context['username'] = $username;
		$context['general_id'] = $general_id;
		$context['send_force'] = $send_force;
		$context['recv_force'] = $recv_force;
		$context['body'] = $body_filtered;
		$context['created_at_utc'] = $general['now_utc'];
		$jsc = pretty_json($context);

		// publish first
		$channel = "$TAG:chat:channel:" . $FMAP[$recv_force-1];
		$redis->publish($channel, $jsc);

		// push to chat cache
		$redis->lPush($ckey, $jsc);
		$redis->expire($mkey, $CHAT_SEND_COOLTIME);

		// send chat to oplog also
		$opkey = "$TAG:oplog:chats";
		$context['action_id'] = $redis->incr("$TAG:oplog:action_seq");
		$context['op'] = 'chat_force';
		$context['body'] = $body;
		$jsc = pretty_json($context);

		$redis->lPush($opkey, $jsc);

		assert_render($tb->end_txn());

		render_ok('sent');
	}

	public static function ban_list($tb) {
		global $TAG, $ALLIES, $EMPIRE, $user_id, $general_id, $dev_type, $dev_uuid, $ops, $status, $country;

		$redis = conn_redis();

		$cbkey = "$TAG:users:user_id=$user_id:chat_ban_list";
		$chat_ban_list = @json_decode($redis->get($cbkey), true) ?: null;
		if ( !$chat_ban_list ) { // recover from db
			$general = general::select($tb, 'chat_ban_ids');

			$chat_ban_list = [];
			$chat_ban_ids = $general['chat_ban_ids'] ?: [];

			if ( sizeof($chat_ban_ids) > 0 ) {
				$ids = implode(',', $chat_ban_ids);
					
				$query = "SELECT general_id, country, name AS username FROM general WHERE general_id IN ($ids)";
					
				assert_render($rs = $tb->query($query));
				$chat_ban_list = ms_fetch_all($rs) ?: [];
			}
			$redis->setex($cbkey, CACHE_TTL, pretty_json($chat_ban_list));
		}

		$map['chat_ban_list'] = $chat_ban_list;

		assert_render($tb->end_txn());
			
		render_ok('success', $map);
	}

	public static function ban_add($tb) {
		global $TAG, $ALLIES, $EMPIRE, $user_id, $general_id, $dev_type, $dev_uuid, $ops, $status, $country;

		$ban_gid = queryparam_fetch_int('ban_gid');
		assert_render($ban_gid > 0, "invalid:ban_gid:$ban_gid");

		$general = general::select($tb, 'chat_ban_ids');
		$ban_general = general::select($tb, 'general_id', "general_id = $ban_gid");

		assert_render($ban_general, "invalid:ban_gid:$ban_gid");

		$chat_ban_ids = $general['chat_ban_ids'] ?: [];
			
		if ( !in_array($ban_gid, $chat_ban_ids) ) {
			$chat_ban_ids[] = $ban_gid;

			$terms = [];
			$terms['chat_ban_ids'] = ms_quote($tb->escape(pretty_json($chat_ban_ids)));
			$pairs = join_terms($terms);

			$query = "UPDATE general SET $pairs WHERE general_id = $general_id";
			assert_render($tb->query($query));

			elog("added ban_gid[$ban_gid] to chat_ban_ids: " . pretty_json($chat_ban_ids));
		} else
			elog("ban_gid[$ban_gid] already in chat_ban_ids: " . pretty_json($chat_ban_ids));

		if ( sizeof($chat_ban_ids) > 0 ) {
			$ids = implode(',', $chat_ban_ids);

			$query = "SELECT general_id, country, name AS username FROM general WHERE general_id IN ($ids)";

			assert_render($rs = $tb->query($query));
			$chat_ban_list = ms_fetch_all($rs) ?: [];
		}

		$redis = conn_redis();
		$cbkey = "$TAG:users:user_id=$user_id:chat_ban_list";
		$redis->set($cbkey, pretty_json($chat_ban_list));

		$map['chat_ban_list'] = $chat_ban_list;

		assert_render($tb->end_txn());
			
		render_ok('success', $map);
	}

	public static function ban_del($tb) {
		global $TAG, $ALLIES, $EMPIRE, $user_id, $general_id, $dev_type, $dev_uuid, $ops, $status, $country;

		$raw_ban_gids = queryparam_fetch('ban_gids');
		$ban_gids = @json_decode($raw_ban_gids) ?: [];
		$gids = implode(',', $ban_gids);

		assert_render(sizeof($ban_gids) > 0, "invalid:ban_gids:$raw_ban_gids");

		$general = general::select($tb, 'chat_ban_ids');

		$query = "SELECT general_id FROM general WHERE general_id IN ($gids)";
		assert_render($rs = $tb->query($query));
		$victim_gids = ms_fetch_all($rs);
		assert_render(sizeof($victim_gids) > 0, "invalid:ban_gids:$raw_ban_gids");
		foreach ($victim_gids as &$gid)
			$gid = $gid['general_id'];

		$chat_ban_list = [];
		$chat_ban_ids = $general['chat_ban_ids'] ?: [];
			
		$chat_ban_ids = array_diff($chat_ban_ids, $victim_gids);

		$terms = [];
		$terms['chat_ban_ids'] = ms_quote($tb->escape(pretty_json($chat_ban_ids)));
		$pairs = join_terms($terms);

		$query = "UPDATE general SET $pairs WHERE general_id = $general_id";
		assert_render($tb->query($query));

		if ( sizeof($chat_ban_ids) > 0 ) {
			$ids = implode(',', $chat_ban_ids);

			$query = "SELECT general_id, country, name AS username FROM general WHERE general_id IN ($ids)";

			assert_render($rs = $tb->query($query));
			$chat_ban_list = ms_fetch_all($rs) ?: [];
		}

		$redis = conn_redis();
		$cbkey = "$TAG:users:user_id=$user_id:chat_ban_list";
		$redis->set($cbkey, pretty_json($chat_ban_list));

		$map['chat_ban_list'] = $chat_ban_list;

		assert_render($tb->end_txn());
			
		render_ok('success', $map);
	}

}

if ( strstr($_SERVER["REQUEST_URI"], basename(__FILE__)) ) {

	$ops = explode(',', $op = queryparam_fetch('op', 'get'));

	$user_id = login_check();
	$general_id = session_GET('general_id');

	if ( sizeof(array_intersect_key(['get', 'clear', 'send', 'notice', 'ban_list', 'ban_add', 'ban_del', 'chat_badword_list'], $ops)) ) {
		for ( $retried = 1 ; $retried <= $TXN_RETRY_MAX ; $retried++ ) {
			try {
				$tb = new TxnBlock();

				if ( in_array("clear", $ops) ) chat::clear($tb);

				if ( in_array("send", $ops) ) chat::send($tb);
				else if ( in_array("notice", $ops) ) chat::notice($tb);
				else if ( in_array("ban_list", $ops) ) chat::ban_list($tb);
				else if ( in_array("ban_add", $ops) ) chat::ban_add($tb);
				else if ( in_array("ban_del", $ops) ) chat::ban_del($tb);
				else if ( in_array("chat_badword_list", $ops) ) chat::chat_badword_list($tb);

				chat::get($tb); // embedes end_txn()
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
