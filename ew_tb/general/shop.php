<?php
require_once '../connect.php';
require_once '../general/general.php';
require_once '../army/officer.php';

class shop {

	public static function get_products() {
		if ( $val = fetch_from_cache('constants:products') )
			return $val;

		$timer_bgn = microtime(true);

		$product_info = loadxml_as_dom('xml/product_info.xml');
		if ( !$product_info )
			return null;

		$products = [];
		$product_ids_basic = [];
		$product_ids_daily = [];

		foreach ($product_info->xpath("//products/product") as $node ) {
			$attrs = (array)$node->attributes();
			$pattrs = $attrs['@attributes'];

			$products[$pattrs["product_id"]] = $pattrs;
		}

		// 		$products['product_ids_basic'] = $product_ids_basic;
		// 		$products['product_ids_daily'] = $product_ids_daily;

		$timer_end = microtime(true);
		elog("time took product for product::get_products(): " . ($timer_end - $timer_bgn));

		store_into_cache('constants:products', $products);

		$bnis = array_keys($products);
		elog("product_ids: " . json_encode($bnis));

		return $products;
	}

	public static function select_all($tb, $select_expr = null, $where_condition = null) {
		global $user_id, $general_id;

		if ( $select_expr == null )
			$select_expr = "*";
		if ( $where_condition == null )
			$where_condition = "WHERE general_id = $general_id";
		else
			$where_condition = "WHERE general_id = $general_id AND ($where_condition)";

		$query = "SELECT $select_expr FROM shop $where_condition /*BY_HELPER*/";
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
		$rows = shop::select_all($tb, $select_expr, $where_condition);
		if ( $rows && count($rows) == 1 )
			return $rows[0];
		return null;
	}

	public static function clear($tb) {
		global $TAG, $ALLIES, $EMPIRE, $user_id, $general_id, $dev_type, $dev_uuid, $ops, $status, $country;

		if ( dev ) {
			// 			$query = "UPDATE general SET shop_list = NULL WHERE general_id = $general_id;";
			// 			assert_render($rs = $tb->query($query));

			// 			$query = "DELETE FROM shop WHERE general_id = $general_id;";
			// 			assert_render($rs = $tb->query($query));

			// 			shop::default_shops($tb, $general_id);
		}
	}

	public static function get($tb) {
		global $TAG, $ALLIES, $EMPIRE, $user_id, $general_id, $dev_type, $dev_uuid, $ops, $status, $country;
		global $SHOP_EQUIPS, $SHOP_ITEMS, $SHOP_GOLDS, $SHOP_HONORS, $SHOP_STARS;

		$PRODUCTS = shop::get_products();

		$type = queryparam_fetch_int('type');
		if ( $type ) {
			assert_render(in_array($type, [$SHOP_EQUIPS, $SHOP_ITEMS, $SHOP_GOLDS, $SHOP_HONORS, $SHOP_STARS]), "invalid:type:$type");
		}

		$map['products'] = [];
		$map['products']['equips'] = [];
		$map['products']['items'] = [];
		$map['products']['golds'] = [];
		$map['products']['honors'] = [];
		$map['products']['stars'] = [];

		foreach ($PRODUCTS as $product_id => $PRODUCT) {
			if ( isset($PRODUCT['item_id']) ) $PRODUCT['item_type_minor'] = $PRODUCT['item_id'];

			// TODO: check event types
			if ( $PRODUCT['type'] !== 'Original' )
				continue;

			if ( $PRODUCT['class'] == 'Gold' && (!$type || $type == $SHOP_GOLDS) ) $map['products']['golds'][] = $PRODUCT;
			if ( $PRODUCT['class'] == 'Honor' && (!$type || $type == $SHOP_HONORS) ) $map['products']['honors'][] = $PRODUCT;
			if ( $PRODUCT['class'] == 'Star' && (!$type || $type == $SHOP_STARS) ) $map['products']['stars'][] = $PRODUCT;
			if ( $PRODUCT['class'] == 'item' && (!$type || $type == $SHOP_ITEMS) ) $map['products']['items'][] = $PRODUCT;
			if ( $PRODUCT['class'] == 'equips' && (!$type || $type == $SHOP_EQUIPS) ) $map['products']['equips'][] = $PRODUCT;
		}

		assert_render($tb->end_txn());

		render_ok('success', $map);
	}

	public static function buy($tb) {
		global $TAG, $ALLIES, $EMPIRE, $user_id, $general_id, $dev_type, $dev_uuid, $ops, $status, $country;
		global $SHOP_EQUIPS, $SHOP_ITEMS, $SHOP_GOLDS, $SHOP_HONORS, $SHOP_STARS;
		global $ITEM_OWNERLESS, $ITEM_MAKING, $ITEM_GENERAL_OWNED, $ITEM_MAIL_OWNED, $ITEM_OFFICER_OWNED;
		global $ITEM_TYPE_MAJOR_EQUIPS, $ITEM_TYPE_MAJOR_COMBATS, $ITEM_TYPE_MAJOR_CONSUMES;

		$product_id = queryparam_fetch_int('product_id');
		assert_render($product_id > 0, "invalid:product_id:$product_id");

		$PRODUCTS = shop::get_products();
		assert_render(isset($PRODUCTS[$product_id]), "invalid:product_id:$product_id");
		$PRODUCT = $PRODUCTS[$product_id];

		$general = general::select($tb, 'gold, gold_max, honor, honor_max, star, item_storage_slot_cur, item_storage_slot_cap');

		$terms = [];
		$querys = [];
		$exchange_type = $PRODUCT['exchange_type'];
		$cur = 0;
		$max = 0;
		$mod = $PRODUCT['total_count'];

		if (0);
		else if ( $exchange_type == 'star' ) {
			if ( !dev )
				assert_render(secure_connection(), "you are accessing with non-secure line");
				
			$receipt = queryparam_fetch('receipt');
			elog("skipping cash payments verification for star");

			// put info into payment also

			$terms[$exchange_type] = "$exchange_type + $mod";

			$pairs = join_terms($terms);
			$query = "UPDATE general SET $pairs WHERE general_id = $general_id";
			assert_render($tb->query($query));

			$query = "UPDATE user SET payment_sum = payment_sum + $mod WHERE user_id = $user_id";
			assert_render($tb->query($query));

			$redis = conn_redis();

			$user = session_GET('user');
			$detail = ['user_id'=>$user_id, 'market_type'=>$user['market_type']];
			$detail['action_id'] = $redis->incr("$TAG:oplog:action_seq");
			$detail['op'] = 'payment_sum_add';
			$detail['mod'] = $mod;
			$detail['price'] = $PRODUCT['pay_price'];
			$detail['action_at_utc'] = time();
			$detail['remote_addr'] = @$_SERVER['REMOTE_ADDR'] ?: null;

			$rkey = "$TAG:oplog:users";
			$redis->lPush($rkey, pretty_json($detail));

			if ( $PRODUCT['pay_type'] == 'Cash' )
				gamelog2('shop', 'buy_cash',
						['exchange_type'=>$exchange_type,
						'mod'=>$mod,
						'pay_type'=>$PRODUCT['pay_type'],
						'price'=>$PRODUCT['pay_price']]);
			else
				gamelog(__METHOD__, ['exchange_type'=>$exchange_type, 'mod'=>$mod, 'pay_type'=>$PRODUCT['pay_type']]);
		}
		else if ( $exchange_type == 'item') {
			// check payments
			assert_render($PRODUCT['pay_type'] == 'Star', "invalid:pay_type");
			if ( $general['star'] < $PRODUCT['pay_price'] ) {
				$map = [];
				$map['cost_star'] = $PRODUCT['pay_price'];
				$map['cur_star'] = $general['star'];
				$map['fcode'] = 60102;
				render_error("not enough star", $map);
			}
			$terms = [];
			$terms['star'] = "star - " . $PRODUCT['pay_price'];
			$pairs = join_terms($terms);
			$query = "UPDATE general SET $pairs WHERE general_id = $general_id";
			$querys[] = $query;

			$CONSUMES = item::get_consumes();

			$type_minor = $PRODUCT['item_id'];
			assert_render(isset($CONSUMES[$type_minor]), "invalid:type_minor:$type_minor");
			$CONSUME = $CONSUMES[$type_minor];

			// check force
			assert_render($CONSUME['force'] == NEUTRAL || $CONSUME['force'] == $country, "invalid:force:cannot buy");

			$items = item::select_all($tb, null, "status = $ITEM_GENERAL_OWNED AND type_major = $ITEM_TYPE_MAJOR_CONSUMES AND type_minor = $type_minor");

			// overlappable with previous items?
			assert_render($CONSUME['limit_overlap'] > 0, "CONSUME['limit_overlap'] > 0");
			if ( $CONSUME['limit_overlap'] == 1 ) {
				if ( $CONSUME['limit_owned'] == 1 && sizeof($items)	> 0 )
					render_error("limit_owned == 1 and limit_overlap == 1, but item already exists", FCODE(27101));

				assert_render($general['item_storage_slot_cur'] < $general['item_storage_slot_cap'], "not enough item_storage_slot");

				// just create new item into storage
				$terms = [];
				$terms['general_id'] = $general_id;
				$terms['status'] = $ITEM_GENERAL_OWNED;
				$terms['type_major'] = $ITEM_TYPE_MAJOR_CONSUMES;
				$terms['type_minor'] = $type_minor;
				$terms['qty'] = $mod;
				$terms['willbe_made_at'] = null;

				$keys = $vals = [];
				$pairs = join_terms($terms, $keys, $vals);

				$query = "INSERT INTO item ($keys) VALUES ($vals)";
				$querys[] = $query;

				$terms = [];
				$terms['item_storage_slot_cur'] = "item_storage_slot_cur + 1";
				$terms['star'] = "star - " . $PRODUCT['pay_price'];
				$pairs = join_terms($terms);
				$query = "UPDATE general SET $pairs WHERE general_id = $general_id";
				$querys[] = $query;
			} else {
				// find remaining overlap slots
				$require_new_slots = 0;
				$remain_slots = $general['item_storage_slot_cap'] - $general['item_storage_slot_cur'];
				$remain_mod = $mod;

				foreach ($items as $item) {
					if ( $remain_mod == 0 )
						break;
					$possible_qty = $CONSUME['limit_overlap'] - $item['qty'];
					$remain_mod -= min($remain_mod, $possible_qty);
				}
				elog("remain_mod: $remain_mod / mod: $mod");

				$require_new_slots = intval(1 + floor(($remain_mod-0.1) / $CONSUME['limit_overlap']));
				elog("require_new_slots: $require_new_slots for remain_mod: $remain_mod");
				if ( $remain_slots < $require_new_slots )
					render_error("not enough item_storage_slot for require_new_slots: $require_new_slots", FCODE(27102));

				// check limit_owned
				if ( sizeof($items) + $require_new_slots > $CONSUME['limit_owned'] )
					render_error("overall storage slot exceeds limit_owned: " . $CONSUME['limit_owned'], FCODE(27103));

				// ok, we're okay to get items
				$remain_mod = $mod;
				// fill previous items first
				foreach ($items as $item) {
					$possible_qty = $CONSUME['limit_overlap'] - $item['qty'];
					if ( $possible_qty == 0 ) continue;

					$mod_fill = min($possible_qty, $remain_mod);
					$remain_mod -= $mod_fill;
					$query = "UPDATE item SET qty = qty + $mod_fill WHERE item_id = " . $item['item_id'];
					$querys[] = $query;
				}

				while ( $remain_mod > 0 ) {
					$mod_fill = min($CONSUME['limit_overlap'], $remain_mod);
					$remain_mod -= $mod_fill;

					$terms = [];
					$terms['general_id'] = $general_id;
					$terms['status'] = $ITEM_GENERAL_OWNED;
					$terms['type_major'] = $ITEM_TYPE_MAJOR_CONSUMES;
					$terms['type_minor'] = $type_minor;
					$terms['qty'] = $mod_fill;
					$terms['willbe_made_at'] = null;

					$keys = $vals = [];
					$pairs = join_terms($terms, $keys, $vals);

					$query = "INSERT INTO item ($keys) VALUES ($vals)";
					$querys[] = $query;
				}
				$query = "UPDATE general SET item_storage_slot_cur = item_storage_slot_cur + $require_new_slots WHERE general_id = $general_id";
				$querys[] = $query;
			}
			assert_render($tb->multi_query($querys));

			gamelog(__METHOD__,
			['exchange_type'=>$exchange_type,
			'mod'=>$mod,
			'pay_type'=>$PRODUCT['pay_type'],
			'type_major'=>$ITEM_TYPE_MAJOR_CONSUMES,
			'type_minor'=>$type_minor]);
		}
		else {
			$max = $general[$exchange_type . "_max_eff"];
			$cur = $general[$exchange_type];

			// check storage
			if ( $cur + $mod > $max ) {
				$map = [];
				$map['max'] = $max;
				$map['mod'] = $mod;
				$map['cur'] = $cur;
				$map['fcode'] = 27104;
				if ( !(dev && queryparam_fetch_int('ignore') > 0) )
					render_error("storage reached at max for purchase", $map);
			}

			// check payments
			assert_render($PRODUCT['pay_type'] == 'Star', "invalid:pay_type");
			if ( $general['star'] < $PRODUCT['pay_price'] ) {
				$map = [];
				$map['cost_star'] = $PRODUCT['pay_price'];
				$map['cur_star'] = $general['star'];
				$map['fcode'] = 60102;
				render_error("not enough star", $map);
			}

			$terms[$exchange_type] = "$exchange_type + $mod";
			$terms['star'] = "star - " . $PRODUCT['pay_price'];

			$pairs = join_terms($terms);
			$query = "UPDATE general SET $pairs WHERE general_id = $general_id";
			assert_render($tb->query($query));

			gamelog(__METHOD__, ['exchange_type'=>$exchange_type, 'mod'=>$mod, 'pay_type'=>$PRODUCT['pay_type']]);
		}

		elog("product [$exchange_type: $mod] was granted");

		$general = general::select($tb);

		assert_render($tb->end_txn());

		$map['general'] = $general;

		render_ok('success', $map);
	}
}

if ( strstr($_SERVER["REQUEST_URI"], basename(__FILE__)) ) {

	$ops = explode(',', $op = queryparam_fetch('op', 'get'));

	$user_id = login_check();
	$general_id = session_GET('general_id');

	$status = queryparam_fetch_int('status');

	if ( sizeof(array_intersect_key(['get', 'clear', 'buy'], $ops)) ) {
		for ( $retried = 1 ; $retried <= $TXN_RETRY_MAX ; $retried++ ) {
			try {
				$tb = new TxnBlock();

				if ( in_array("clear", $ops) ) shop::clear($tb);

				if ( in_array("buy", $ops) ) shop::buy($tb);

				shop::get($tb); // embedes end_txn()
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
