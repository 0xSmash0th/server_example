<?php
require_once '../connect.php';
require_once '../general/general.php';
require_once '../army/officer.php';

function compare_by_key($a, $b, $compare_key) {
	if (isset($a[$compare_key]) && isset($b[$compare_key]) ) {
		if ( $a[$compare_key] < $b[$compare_key] )
			return -1;
		else if ( $a[$compare_key] > $b[$compare_key] )
			return 1;
	}
	return 0;
}

function compare_by_line_up($a, $b) {
	return compare_by_key($a, $b, 'line_up');
}

function compare_by_type_major_and_line_up($a, $b) {
	$res = compare_by_key($a, $b, 'type_major');
	if ( $res == 0 )
		$res = compare_by_key($a, $b, 'line_up');

	return $res;
}

class item {

	public static function get_consumes() {
		if ( $val = fetch_from_cache('constants:item_consumes') )
			return $val;

		$timer_bgn = microtime(true);

		$item_consume_info = loadxml_as_dom('xml/item_consume_info.xml');
		if ( !$item_consume_info )
			return null;

		$item_consumes = [];

		foreach ($item_consume_info->xpath("//item_consumes/item_consume") as $node ) {
			$attrs = (array)$node->attributes();
			$pattrs = $attrs['@attributes'];

			$item_consumes[$pattrs["id"]] = $pattrs;
		}


		$timer_end = microtime(true);
		elog("time took item_consume for item_consume::get_consumes(): " . ($timer_end - $timer_bgn));

		store_into_cache('constants:item_consumes', $item_consumes);

		$bnis = array_keys($item_consumes);
		elog("item_consume_ids: " . json_encode($bnis));

		return $item_consumes;
	}

	public static function get_combats() {
		if ( $val = fetch_from_cache('constants:item_combats') )
			return $val;

		$timer_bgn = microtime(true);

		$item_combat_info = loadxml_as_dom('xml/item_combat_info.xml');
		if ( !$item_combat_info )
			return null;

		$item_combats = [];

		foreach ($item_combat_info->xpath("//item_combats/item_combat") as $node ) {
			$attrs = (array)$node->attributes();
			$pattrs = $attrs['@attributes'];

			$item_combats[$pattrs["id"]] = $pattrs;
		}

		$timer_end = microtime(true);
		elog("time took item_combat for item_combat::get_consumes(): " . ($timer_end - $timer_bgn));

		store_into_cache('constants:item_combats', $item_combats);

		$bnis = array_keys($item_combats);
		elog("item_combat_ids: " . json_encode($bnis));

		return $item_combats;
	}

	public static function merge_combat_items_by_type_minor($tb) {
		global $user_id, $general_id;
		global $ITEM_OWNERLESS, $ITEM_MAKING, $ITEM_GENERAL_OWNED, $ITEM_MAIL_OWNED, $ITEM_OFFICER_OWNED;
		global $ITEM_TYPE_MAJOR_EQUIPS, $ITEM_TYPE_MAJOR_COMBATS, $ITEM_TYPE_MAJOR_CONSUMES;

		elog("merge_combat_items_by_type_minor ...");

		// merge combat items by type_minor
		$query = "SELECT type_minor, COUNT(*) AS count, SUM(qty) AS total, MIN(item_id) AS min_tid FROM item ".
				"WHERE general_id = $general_id AND status = $ITEM_GENERAL_OWNED ".
				"AND type_major = $ITEM_TYPE_MAJOR_COMBATS GROUP BY type_minor";

		assert_render($rs = $tb->query($query));
		$groups = ms_fetch_all($rs);

		$querys = [];
		foreach ( $groups as $group ) {
			if ( $group['count'] > 1 ) {
				elog("merge items: ". pretty_json($group));

				$query = "DELETE FROM item WHERE general_id = $general_id AND status = $ITEM_GENERAL_OWNED ".
						"AND type_major = $ITEM_TYPE_MAJOR_COMBATS ".
						"AND type_minor = ". $group['type_minor'] ." AND item_id > " . $group['min_tid'];
				$querys[] = $query;

				$query = "UPDATE item SET qty = ". $group['total'] ." WHERE general_id = $general_id AND item_id = " . $group['min_tid'];
				$querys[] = $query;
			}
		}

		if ( sizeof($querys) > 0 ) {
			// update general.item_storage_slot_cur
			$query = "UPDATE general SET item_storage_slot_cur = ".
					"(SELECT COUNT(*) FROM item WHERE general_id = $general_id AND status = $ITEM_GENERAL_OWNED) ".
					"WHERE general_id = $general_id";
			// 			$querys[] = $query;

			assert_render($tb->multi_query($querys));
		}
	}

	/**
	 * Resolve making's completion of current general
	 * @param Integer $general_id
	 * @param TxnBlock $tb
	 * @return mysqli:resultset
	 */
	public static function resolve_making($general_id, $tb) {
		global $ITEM_OWNERLESS, $ITEM_MAKING, $ITEM_GENERAL_OWNED, $ITEM_MAIL_OWNED, $ITEM_OFFICER_OWNED;

		$with_tb = $tb;

		if ( !$with_tb )
			$tb = new TxnBlock();

		// note that only COMBAT item will be resolved
		$query = "UPDATE item SET status = $ITEM_GENERAL_OWNED WHERE "
		." general_id = $general_id AND status = $ITEM_MAKING AND willbe_made_at <= NOW()";
		assert_render($tb->query($query));

		if ( $tb->mc()->affected_rows > 0 ) {
			elog("resolve_making: updated: ". $tb->mc()->affected_rows . " rows");

			// merge items
			item::merge_combat_items_by_type_minor($tb);
		}

		if ( !$with_tb )
			assert_render($tb->end_txn());
	}

	public static function clear($tb) {
		global $TAG, $ALLIES, $EMPIRE, $user_id, $general_id, $dev_type, $dev_uuid, $ops, $status, $country;
		global $item_id;

		if ( dev ) {
			$query = "DELETE FROM item WHERE general_id = $general_id";
			assert_render($rs = $tb->query($query));

			item::default_items($tb, $general_id);
		}
	}

	public static function default_items($tb, $general_id, $count_before = false) {
		global $ITEM_OWNERLESS, $ITEM_MAKING, $ITEM_GENERAL_OWNED, $ITEM_MAIL_OWNED, $ITEM_OFFICER_OWNED;
		global $ITEM_TYPE_MAJOR_EQUIPS, $ITEM_TYPE_MAJOR_COMBATS, $ITEM_TYPE_MAJOR_CONSUMES;

		if ( $count_before ) {
			$query = "SELECT COUNT(*) FROM item WHERE general_id = $general_id AND status = $ITEM_GENERAL_OWNED";
			$count = ms_fetch_single_cell($tb->query($query));
			if ( $count && $count >= 1 ) {
				// 				elog("default items already exist: $count");
				return;
			}
		}

		$COMBATS = item::get_combats();

		elog("putting default [COMBAT] items ...");

		$country = session_GET('country');
		$querys = [];
		foreach ($COMBATS as $id => $item) {
			if ( !($country == $item['force'] || $item['force'] == NEUTRAL) )
				continue;

			$terms = [];
			$terms['general_id'] = $general_id;
			$terms['type_major'] = $ITEM_TYPE_MAJOR_COMBATS;
			$terms['type_minor'] = $item['id'];
			$terms['status'] = $ITEM_GENERAL_OWNED;
			$terms['owner_id'] = $general_id;
			$terms['qty'] = 0;

			$keys = $vals = [];
			join_terms($terms, $keys, $vals);

			$query = "INSERT INTO item ($keys) VALUES ($vals)";
			$querys[] = $query;
		}

		// update general.item_storage_slot_cur
		$query = "UPDATE general SET item_storage_slot_cur = ".
				"(SELECT COUNT(*) FROM item WHERE general_id = $general_id AND status = $ITEM_GENERAL_OWNED) ".
				"WHERE general_id = $general_id";
		// 		$querys[] = $query;

		assert_render($tb->multi_query($querys));
	}

	public static function select_all($tb, $select_expr = null, $where_condition = null, $post_where = null) {
		global $TAG, $ALLIES, $EMPIRE, $user_id, $general_id, $dev_type, $dev_uuid, $ops, $status, $country;

		if ( $select_expr == null )
			$select_expr = "*";
		if ( $where_condition == null )
			$where_condition = "WHERE general_id = $general_id";
		else
			$where_condition = "WHERE general_id = $general_id AND ($where_condition)";
		if ( $post_where )
			$where_condition .= $post_where;

		$query = "SELECT $select_expr FROM item $where_condition /*BY_HELPER*/";
		assert_render($rows = ms_fetch_all($tb->query($query)));

		$json_keys = [];
		$eff_keys = [];

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

			foreach ($eff_keys as $eff_key => $effect_id) {
				if ( !empty($cols[$eff_key]) ) {
					$cols[$eff_key . "_eff"] = general::apply_effects($tb, $effect_id, $cols[$eff_key]);
				}
			}

			$rows[$i] = $cols;
		}

		return $rows;
	}
	public static function select($tb, $select_expr = null, $where_condition = null) {
		$rows = item::select_all($tb, $select_expr, $where_condition);
		if ( $rows && count($rows) == 1 )
			return $rows[0];
		return null;
	}

	public static function get($tb) {
		global $TAG, $ALLIES, $EMPIRE, $user_id, $general_id, $dev_type, $dev_uuid, $ops, $status, $country;
		global $item_id;
		global $TROOP_TRAINING, $TROOP_TRAINED, $TROOP_BANDED;
		global $ITEM_OWNERLESS, $ITEM_MAKING, $ITEM_GENERAL_OWNED, $ITEM_MAIL_OWNED, $ITEM_OFFICER_OWNED;
		global $ITEM_TYPE_MAJOR_EQUIPS, $ITEM_TYPE_MAJOR_COMBATS, $ITEM_TYPE_MAJOR_CONSUMES;
		global $BLD_LABORATORY_ID;

		$officer_id = queryparam_fetch_int('officer_id');
		$type_major = queryparam_fetch_int('type_major');
		$type_minor = queryparam_fetch_int('type_minor');

		$terms = [];
		$post_where = null;

		if ( $item_id )
			$terms[] = "item_id = $item_id";

		$terms[] = "status BETWEEN $ITEM_MAKING AND $ITEM_GENERAL_OWNED";
		if ( $status )
			$terms[] = "status = $status";
			
		if ( $type_major ) {
			assert_render(1 <= $type_major && $type_major <= 3, "invalid:type_major: $type_major");
			$terms[] = "type_major = $type_major";
		}
		if ( $type_minor ) {
			$terms[] = "type_minor = $type_minor";
		}
		if ( $officer_id && $officer_id > 0 ) {
			$terms[] = "officer_id = $officer_id";
			// 			$post_where = " ORDER BY slot ";
		}

		$where = implode(' AND ', $terms);

		$items = item::select_all($tb, null, $where, $post_where);

		if ( $item_id && sizeof($items) == 0 )
			render_error("invalid:item_id: $item_id");

		if ( sizeof($items) > 1 ) {
			uasort($items, 'compare_by_type_major_and_line_up'); // sort items by type major ASC, line_up ASC
			$items = array_values($items);
		}

		// fill valid info for combat_items
		$general = null;
		$lab_max_level = 0;
		foreach ($items as &$item) {
			$item['valid'] = 1;
			$item['reason'] = null;

			if ( $item['type_major'] != $ITEM_TYPE_MAJOR_COMBATS )
				continue;

			if ( !$general ) {
				$general = general::select($tb, 'gold, honor, star, building_list');
				$lab_max_level = construction::find_building($general['building_list'], $BLD_LABORATORY_ID);
			}

			$COMBATS = item::get_combats();

			$ukey = $item['type_minor'];
			$uval = $COMBATS[$ukey];

			// check force
			$valid = 1;
			$reason = null;

			// check costs
			$uval['cost_gold'] = empty($uval['cost_gold']) ? 0 : $uval['cost_gold'];
			$uval['cost_honor'] = empty($uval['cost_honor']) ? 0 : $uval['cost_honor'];
			$uval['cost_star'] = empty($uval['cost_star']) ? 0 : $uval['cost_star'];

			if ( $uval['cost_gold'] <= $general['gold'] && $uval['cost_honor'] <= $general['honor'] && $uval['cost_star'] <= $general['star']  )
				$valid = 1;
			else {
				$valid = 0;
				if ( !($uval['cost_gold'] <= $general['gold']) ) {
					$reason['cost_gold'] = $uval['cost_gold'];
					$reason['cur_gold'] = $general['gold'];
				}
				if ( !($uval['cost_honor'] <= $general['honor']) ) {
					$reason['cost_honor'] = $uval['cost_honor'];
					$reason['cur_honor'] = $general['honor'];
				}
				if ( !($uval['cost_star'] <= $general['star']) ) {
					$reason['cost_star'] = $uval['cost_star'];
					$reason['cur_star'] = $general['star'];
				}
			}

			// check laboratory level
			if ( !empty($uval['req']) && $lab_max_level < $uval['req'] ) {
				$valid = 0;
				$reason['req_lab_level'] = $uval['req'];
				$reason['cur_lab_level'] = $lab_max_level;
			}

			// DO NOT check also limit_owned, limit_overlap (it costs)
			$item['valid'] = $valid;
			$item['reason'] = $reason;
		}

		assert_render($tb->end_txn());

		$map['items'] = $items;

		render_ok('success', $map);
	}

	public static function make($tb) {
		global $TAG, $ALLIES, $EMPIRE, $user_id, $general_id, $dev_type, $dev_uuid, $ops, $status, $country;
		global $item_id;
		global $ITEM_OWNERLESS, $ITEM_MAKING, $ITEM_GENERAL_OWNED, $ITEM_MAIL_OWNED, $ITEM_OFFICER_OWNED;
		global $ITEM_TYPE_MAJOR_EQUIPS, $ITEM_TYPE_MAJOR_COMBATS, $ITEM_TYPE_MAJOR_CONSUMES;
		global $BLD_LABORATORY_ID;

		$qty = queryparam_fetch_int('qty');
		$type_major = queryparam_fetch_int('type_major');
		$type_minor = queryparam_fetch_int('type_minor');

		assert_render($type_major == $ITEM_TYPE_MAJOR_COMBATS, "invalid:type_major:$type_major");
		assert_render(1 <= $qty, "invalid:qty:$qty");

		// check already making item
		$query = "SELECT item_id FROM item WHERE general_id = $general_id AND status = $ITEM_MAKING";
		if ( $item = ms_fetch_one($tb->query($query)) )
			render_error("another item is already making: " . $item['item_id'], FCODE(70101));

		$country = session_GET('country');
		$cost_time = 0;
		$cost_gold = 0;
		$cost_honor = 0;
		$cost_star = 0;
		$req_lab_level = 0;

		if ( $type_major == $ITEM_TYPE_MAJOR_COMBATS ) {
			$COMBATS = item::get_combats();

			assert_render(isset($COMBATS[$type_minor]), "invalid:type_minor:$type_minor");
			$COMBAT = $COMBATS[$type_minor];

			assert_render($COMBAT['force'] == NEUTRAL || $country == $COMBAT['force'], "invalid:force:$type_minor", FCODE(70102));
			$req_lab_level = $COMBAT['req'];

			assert_render($qty <= $COMBAT['limit_product'], "invalid:qty:limit_product:$qty", FCODE(70103));

			// combat items are always owned, and it's slot-limit is 1
			// 			if ( $COMBAT['limit_owned'] > 0 ) {
			// 				$item = item::select($tb, null, "status = $ITEM_GENERAL_OWNED OR status = $ITEM_OFFICER_OWNED");
			// 				assert_render(!$item, "limit_owned:restrict:to:1", FCODE(70104));
			// 			}

			if ( $COMBAT['limit_overlap'] > 1 ) {
				// overlap
				$items = item::select_all($tb, null,
						"(status = $ITEM_GENERAL_OWNED OR status = $ITEM_OFFICER_OWNED) ".
						" AND type_major = $type_major AND type_minor = $type_minor");

				if ( $items && sizeof($items) == 1 ) {
					assert_render(sizeof($items) == 1, "sizeof(items) == 1");
					$item = $items[0];

					$new_qty = $item['qty'] + $qty;
					if ( $new_qty > $COMBAT['limit_overlap'] ) {
						$map['old_qty'] = $item['qty'];
						$map['new_qty'] = $new_qty;
						$map['max_qty'] = $COMBAT['limit_overlap'];
						$map['fcode'] = 70105;

						render_error("exceeded qty overlap", $map);
					}
				} else
					elog("no overlap was triggered, new item");
			}

			$cost_time = $qty * $COMBAT['time'];
			$cost_gold = $qty * $COMBAT['cost_gold'];
			$cost_honor = $qty * $COMBAT['cost_honor'];
			$cost_star = $qty * $COMBAT['cost_star'];
		} else {
			// 			$CONSUMES = item::get_consumes();

			render_error("Unsupported type_major: " . $type_major);
		}

		if ( dev ) {
			if ( ($ct = queryparam_fetch_int('cost_time', -1)) >= 0 ) {
				elog("overriding cost_time from $cost_time to $ct");
				$cost_time = $ct;
			}
		}

		// apply effects
		$cost_time = max(0, general::apply_effects($tb, 109, $cost_time));
		$cost_gold = max(0, general::apply_effects($tb, 110, $cost_gold));
		$cost_honor = max(0, general::apply_effects($tb, 110, $cost_honor));
		$cost_star = max(0, general::apply_effects($tb, 110, $cost_star));

		$general = general::select($tb, 'gold, honor, star, pop_cur, pop_max, building_list, item_storage_slot_cur, item_storage_slot_cap');
		if ( !($cost_gold <= $general['gold'] && $cost_honor <= $general['honor'] && $cost_star <= $general['star']) ) {
			$map['cost_gold'] = $cost_gold;
			$map['cost_honor'] = $cost_honor;
			$map['cost_star'] = $cost_star;
			$map['cur_gold'] = $general['gold'];
			$map['cur_honor'] = $general['honor'];
			$map['cur_star'] = $general['star'];
			$map['fcode'] = 10104;

			render_error("not enough gold or honor or star", $map);
		}

		// check storage capacity
		// 		$general = general::select($tb, 'item_storage_slot_cur, item_storage_slot_cap');
		// 		$storage_cur = $general['item_storage_slot_cur'];
		// 		$storage_cap = $general['item_storage_slot_cap'];
		// 		$storage_mod = 1;

		// 		if ( $storage_cur + $storage_mod > $storage_cap ) {
		// 			$map = [];
		// 			$map['storage_cur'] = $storage_cur;
		// 			$map['storage_cap'] = $storage_cap;
		// 			$map['fcode'] = '26101';
		// 			if ( !(dev && queryparam_fetch_int('ignore') > 0) )
		// 				render_error('not enough storage', $map);
		// 		}

		// check building dependency
		if ( $req_lab_level > 0 ) {
			$lab_max_level = construction::find_building($general['building_list'], $BLD_LABORATORY_ID);

			if ( $lab_max_level < $req_lab_level ) {
				$map['req_lab_level'] = $req_lab_level;
				$map['cur_lab_level'] = $lab_level_max;
				$map['fcode'] = 70106;
				render_error("lab_max_level < req_lab_level", $map);
			}
		}

		// update item
		$terms = [];
		$terms['general_id'] = $general_id;
		$terms['type_major'] = $type_major;
		$terms['type_minor'] = $type_minor;
		$terms['status'] = $ITEM_MAKING;
		$terms['qty'] = $qty;
		$terms['willbe_made_at'] = "TIMESTAMPADD(SECOND, $cost_time, NOW())";

		$keys = $vals = [];
		join_terms($terms, $keys, $vals);
		$query = "INSERT INTO item ($keys) VALUES ($vals)";
		assert_render($tb->query_with_affected($query, 1));

		$new_item_id = $tb->mc()->insert_id;

		// update general
		$terms = [];
		if ( $cost_gold > 0 ) $terms['gold'] = "gold - $cost_gold";
		if ( $cost_honor > 0 ) $terms['honor'] = "honor - $cost_honor";
		if ( $cost_star > 0 ) $terms['star'] = "star - $cost_star";
		// 		$terms['item_storage_slot_cur'] = "(SELECT COUNT(*) FROM item WHERE general_id = $general_id AND status BETWEEN $ITEM_MAKING AND $ITEM_GENERAL_OWNED)";

		if ( sizeof($terms) > 0 ) {
			$pairs = join_terms($terms);
			$query = "UPDATE general SET $pairs WHERE general_id = $general_id";
			assert_render($tb->query($query));
		}

		$item = item::select($tb, null, "item_id = $new_item_id");

		gamelog(__METHOD__, ['item_id' => $new_item_id, 'type_major'=>$type_major, 'type_minor'=>$type_minor, 'qty'=>$qty]);

		// post push
		$context = [];
		$context['user_id'] = $user_id;
		$context['dev_type'] = session_GET('dev_type');
		$context['dev_uuid'] = session_GET('dev_uuid');
		$context['src_id'] = "item:make:$new_item_id";
		$context['send_at'] = $item['willbe_made_at'];
		$context['body'] = "item:make:$new_item_id done";
		event::push_post($tb, $context);

		assert_render($tb->end_txn());

		$item['cost_time'] = $cost_time;
		$map['items'] = [$item];

		render_ok('make started', $map);
	}

	// DEPRECATING
	public static function make_list($tb) {
		global $TAG, $ALLIES, $EMPIRE, $user_id, $general_id, $dev_type, $dev_uuid, $ops, $status, $country;
		global $item_id;
		global $ITEM_OWNERLESS, $ITEM_MAKING, $ITEM_GENERAL_OWNED, $ITEM_MAIL_OWNED, $ITEM_OFFICER_OWNED;
		global $ITEM_TYPE_MAJOR_EQUIPS, $ITEM_TYPE_MAJOR_COMBATS, $ITEM_TYPE_MAJOR_CONSUMES;
		global $BLD_LABORATORY_ID;

		$EQUIPS = [];
		$CONSUMES = item::get_consumes();
		$COMBATS = item::get_combats();

		$general = general::select($tb, 'gold, honor, star, building_list');

		$lab_max_level = construction::find_building($general['building_list'], $BLD_LABORATORY_ID);

		$country = session_GET('country');
		$result = [];

		$result['equips'] = [];
		$result['combats'] = [];
		$result['consumes'] = [];

		// check available items by building or resources
		$type_majors = ['equips'=>$ITEM_TYPE_MAJOR_EQUIPS, 'combats'=>$ITEM_TYPE_MAJOR_COMBATS, 'consumes'=>$ITEM_TYPE_MAJOR_CONSUMES];
		foreach (['combats'=>$COMBATS, 'consumes'=>$CONSUMES] as $item_category => $BASE ) {
			$items = ['_'=>'_'];

			foreach ($BASE as $ukey => $uval) {
				// check force
				if ( $uval['force'] == NEUTRAL || ($country == ALLIES && $uval['force'] == ALLIES) || ($country == EMPIRE && $uval['force'] == EMPIRE) ) {
					$valid = 1;
					$reason = null;

					// check costs
					$uval['cost_gold'] = empty($uval['cost_gold']) ? 0 : $uval['cost_gold'];
					$uval['cost_honor'] = empty($uval['cost_honor']) ? 0 : $uval['cost_honor'];
					$uval['cost_star'] = empty($uval['cost_star']) ? 0 : $uval['cost_star'];

					if ( $uval['cost_gold'] <= $general['gold'] && $uval['cost_honor'] <= $general['honor'] && $uval['cost_star'] <= $general['star']  )
						$valid = 1;
					else {
						$valid = 0;
						if ( !($uval['cost_gold'] <= $general['gold']) ) {
							$reason['cost_gold'] = $uval['cost_gold'];
							$reason['cur_gold'] = $general['gold'];
						}
						if ( !($uval['cost_honor'] <= $general['honor']) ) {
							$reason['cost_honor'] = $uval['cost_honor'];
							$reason['cur_honor'] = $general['honor'];
						}
						if ( !($uval['cost_star'] <= $general['cur_star']) ) {
							$reason['cost_star'] = $uval['cost_star'];
							$reason['cur_star'] = $general['star'];
						}
					}

					// check laboratory level
					if ( !empty($uval['req']) && $lab_max_level < $uval['req'] ) {
						$valid = 0;
						$reason['req_lab_level'] = $uval['req'];
						$reason['cur_lab_level'] = $lab_max_level;
					}

					// DO NOT check also limit_owned, limit_overlap (it costs)

					$tuple = [];
					$tuple['type_major'] = $type_majors[$item_category];
					$tuple['id'] = $uval['id'];
					$tuple['valid'] = $valid;
					$tuple['reason'] = $reason;

					$items[] = $tuple;
				}
			}

			uasort($items, 'compare_by_line_up'); // sort items by compare_by_line_up

			elog("items: " . pretty_json($items));

			$result[$item_category] = $items;
		}

		assert_render($tb->end_txn());

		$map['items'] = $result;

		render_ok('success', $map);
	}

	public static function make_haste($tb) {
		global $TAG, $ALLIES, $EMPIRE, $user_id, $general_id, $dev_type, $dev_uuid, $ops, $status, $country;
		global $item_id;
		global $ITEM_OWNERLESS, $ITEM_MAKING, $ITEM_GENERAL_OWNED, $ITEM_MAIL_OWNED, $ITEM_OFFICER_OWNED;
		global $ITEM_MAKE_HASTE_COST_STAR_PER_HOUR;

		$ignore = queryparam_fetch_int('ignore');
		$item_id = queryparam_fetch_int('item_id');  // item_id to haste
		assert_render($item_id > 0, 'invalid:item_id');

		$item = item::select($tb, "TIMESTAMPDIFF(SECOND, NOW(), willbe_made_at) AS dt_diff", "item_id = $item_id AND status = $ITEM_MAKING");
		assert_render($item, "invalid:item_id:or not making: $item_id", FCODE(70201));
		assert_render($item['dt_diff'] > 0, "invalid:item_id:making done: $item_id", FCODE(70202));

		$remain_seconds = $item['dt_diff'];

		// star for an hour
		$cost_star = ($ITEM_MAKE_HASTE_COST_STAR_PER_HOUR) * (int)(($remain_seconds-1)/3600)+1;

		elog("remain_seconds: $remain_seconds, cost_star: $cost_star");

		$general = general::select($tb, 'star');
		if ( $general['star'] < $cost_star && !(dev && $ignore > 0))
			render_error("not enough star: " . $general['star'] . " < $cost_star", array('fcode'=>10102, 'cost_star'=>$cost_star));

		$querys = [];

		$terms = [];
		$terms['star'] = "star - $cost_star";
		$pairs = join_terms($terms);
		$querys[] = "UPDATE general SET $pairs WHERE general_id = $general_id";

		$terms = [];
		$terms['willbe_made_at'] = null;
		$terms['status'] = $ITEM_GENERAL_OWNED;
		$pairs = join_terms($terms);
		$querys[] = "UPDATE item SET $pairs WHERE item_id = $item_id";

		assert_render($tb->multi_query($querys), "make_haste");

		item::merge_combat_items_by_type_minor($tb);

		$map['items'] = $items = item::select_all($tb, null, "item_id = $item_id");

		event::push_cancel($tb, "item:$item_id");

		assert_render($tb->end_txn());

		render_ok('make_haste', $map);
	}

	public static function consume($tb) {
		global $TAG, $ALLIES, $EMPIRE, $user_id, $general_id, $dev_type, $dev_uuid, $ops, $status, $country;
		global $item_id;
		global $ITEM_OWNERLESS, $ITEM_MAKING, $ITEM_GENERAL_OWNED, $ITEM_MAIL_OWNED, $ITEM_OFFICER_OWNED;
		global $ITEM_MAKE_HASTE_COST_STAR_PER_HOUR;

		$item_id = queryparam_fetch_int('item_id');
		assert_render($item_id > 0, 'invalid:item_id');

		$qty = queryparam_fetch_int('qty');
		assert_render($qty > 0, 'invalid:qty');

		$item = item::select($tb, null, "item_id = $item_id");
		assert_render($item && $item['status'] == $ITEM_GENERAL_OWNED, "invalid:item_id::$item_id");

		assert_render($item && $item['qty'] > 0, "invalid:item_id:qty==0", FCODE(70301));
		assert_render($qty <= $item['qty'], "invalid:item_id:not enough qty to consume", FCODE(70304));
		assert_render($item && $item['type_major'] == 3,"invalid:item_id:not consumable", FCODE(70302));

		$CONSUMES = item::get_consumes();
		assert_render(isset($CONSUMES[$item['type_minor']]), "invalid:type_minor:" . $item['type_minor']);
		$CONSUME = $CONSUMES[$item['type_minor']];

		// check force
		if ( $CONSUME['force'] != NEUTRAL ) {
			$country = session_GET('country');
			assert_render($country == $CONSUME['force'], "invalid:force:not allowed to this force " . $CONSUME['force']);
		}

		$querys = [];

		// take effects from items
		$class = $CONSUME['class'];
		assert_render($class == 1 || $class == 2, "invalid:class:$class");
		if ( $class == 2 ) {
			// targets to general
			if ( $CONSUME['id'] == 331201 ) {
				$general = general::select($tb, 'activity_cur, activity_max');
				$cur = $general['activity_cur'];
				$max = general::apply_effects($tb, 118, $general['activity_max']);
				$mod = $qty * $CONSUME['value'];

				if ( $cur + $mod > $max ) {
					$map['activity_cur'] = $cur;
					$map['activity_max'] = $max;
					$map['activity_mod'] = $mod;
					$map['fcode'] = 70303;
					if ( !(dev && queryparam_fetch_int('ignore') > 0) )
						render_error("activity cur($cur) + mod($mod) > max($max)", $map);
				}
				$querys[] = "UPDATE general SET activity_cur = activity_cur + $mod WHERE general_id = $general_id";
			}
		} else if ( $class == 1 ) {
			// targets to officer
			$officer_id = queryparam_fetch_int('officer_id');

			if ( $CONSUME['id'] == 331101 ) {
				$officer = officer::select($tb, 'exp_cur, exp_max, level, grade', "officer_id = $officer_id");
				assert_render($officer, "invalid:officer:officer_id:$officer_id");

				$OLEVELS = officer::get_levels();
				$officer_at_max_level = false;
				if ( $officer['level'] >= $OLEVELS['max_level'] ) {
					elog("officer reached at max_level");
					$officer_at_max_level = true;
				}

				$cur = $officer['exp_cur'];
				$max = $officer['exp_max'];
				$mod = $qty * $CONSUME['value'];
					
				$query = "UPDATE officer SET exp_cur = exp_cur + $mod WHERE officer_id = $officer_id";
				assert_render($tb->query($query));

				if ( ($cur + $mod > $max) && !$officer_at_max_level ) {
					$GRADES = officer::get_grades();
					$GRADE = $GRADES[$officer['grade']];
					if ( $GRADE['max_level'] < $officer['level'] )
						officer::check_officer_levelup($tb, $officer_id);
				}
			}
		}

		// adjust consume items
		$deleted = false;
		if ( $qty == $item['qty'] ) {
			elog("used up all qty($qty), deleting ... $item_id");

			$query = "DELETE FROM item WHERE item_id = $item_id;";
			$querys[] = $query;

			$terms = [];
			$terms['item_storage_slot_cur'] = "item_storage_slot_cur - 1";
			$pairs = join_terms($terms);
			$query = "UPDATE general SET $pairs WHERE general_id = $general_id";
			$querys[] = $query;
		} else {
			elog("used up some qty($qty), updating ... $item_id");

			$terms = [];
			$terms['qty'] = "qty - $qty";
			$pairs = join_terms($terms);
			$query = "UPDATE item SET $pairs WHERE item_id = $item_id;";
			$querys[] = $query;
		}

		assert_render($tb->multi_query($querys));

		$map['items'] = $items = item::select_all($tb, null, "item_id = $item_id");

		gamelog(__METHOD__, ['item_id' => $item_id, 'item' => $item, 'qty'=>$qty]);

		assert_render($tb->end_txn());

		render_ok('consumed', $map);
	}

	public static function sell($tb) {
		global $TAG, $ALLIES, $EMPIRE, $user_id, $general_id, $dev_type, $dev_uuid, $ops, $status, $country;
		global $item_id;
		global $ITEM_OWNERLESS, $ITEM_MAKING, $ITEM_GENERAL_OWNED, $ITEM_MAIL_OWNED, $ITEM_OFFICER_OWNED;
		global $ITEM_TYPE_MAJOR_EQUIPS, $ITEM_TYPE_MAJOR_COMBATS, $ITEM_TYPE_MAJOR_CONSUMES;

		$item_id = queryparam_fetch_int('item_id');
		assert_render($item_id > 0, 'invalid:item_id');

		$qty = queryparam_fetch_int('qty');
		assert_render($qty > 0, 'invalid:qty');

		$item = item::select($tb, null, "item_id = $item_id");
		assert_render($item && $item['status'] == $ITEM_GENERAL_OWNED, "invalid:item_id::$item_id");

		assert_render($qty <= $item['qty'], "invalid:item_id:not enough qty to sell", FCODE(70501));
		assert_render(in_array($item['type_major'], [$ITEM_TYPE_MAJOR_EQUIPS, $ITEM_TYPE_MAJOR_COMBATS, $ITEM_TYPE_MAJOR_CONSUMES]));

		$storage_mod = 0;
		$sell_gold = 0;
		$type_minor = $item['type_minor'];
		if ( $item['type_major'] == $ITEM_TYPE_MAJOR_EQUIPS ) {
			$EQUIPS = [];
			$storage_mod = -1;
			render_error("NYI");
		} else if ( $item['type_major'] == $ITEM_TYPE_MAJOR_COMBATS ) {
			$COMBATS = item::get_combats();

			assert_render(isset($COMBATS[$type_minor]), "invalid:type_minor:$type_minor");
			$COMBAT = $COMBATS[$type_minor];
			$sell_gold = isset($COMBAT['sell_gold']) ? $COMBAT['sell_gold'] : 0;
		} else if ( $item['type_major'] == $ITEM_TYPE_MAJOR_CONSUMES ) {
			$CONSUMES = item::get_consumes();

			assert_render(isset($CONSUMES[$type_minor]), "invalid:type_minor:$type_minor");
			$CONSUME = $CONSUMES[$type_minor];
			$sell_gold = isset($CONSUME['sell_gold']) ? $CONSUME['sell_gold'] : 0;
			$storage_mod = -1;
		}

		if ( !(dev && queryparam_fetch_int('ignore') > 0) )
			assert_render($sell_gold > 0, "cannot sell this item", FCODE(70502));

		$get_gold = $sell_gold * $qty;

		if ( $qty == $item['qty'] ) {
			$query = "DELETE FROM item WHERE item_id = $item_id;";
		} else {
			$storage_mod = 0; // reset mod on update

			$terms = [];
			$terms['qty'] = "qty - $qty";
			$pairs = join_terms($terms);
			$query = "UPDATE item SET $pairs WHERE item_id = $item_id;";
		}
		assert_render($tb->query($query));

		$terms = [];
		$terms['gold'] = "gold + $get_gold";
		if ( $storage_mod != 0 )
			$terms['item_storage_slot_cur'] = "item_storage_slot_cur + ($storage_mod)";
		$pairs = join_terms($terms);
		$query = "UPDATE general SET $pairs WHERE general_id = $general_id;";
		assert_render($tb->query($query));

		$map['items'] = $items = item::select_all($tb, null, "item_id = $item_id");

		gamelog(__METHOD__, ['item_id' => $item_id, 'item' => $item, 'qty'=>$qty]);

		assert_render($tb->end_txn());

		render_ok('success', $map);
	}

	public static function expand_storage_slot_cap($tb) {
		global $user_id, $general_id, $ops, $officer_id, $status;
		global $ITEM_STORAGE_SLOT_MIN, $ITEM_STORAGE_SLOT_MAX, $ITEM_EXPAND_STORAGE_SLOT_COST_STAR;

		$general = general::select($tb, 'star, item_storage_slot_cur, item_storage_slot_cap');

		// check max
		if ( $general['item_storage_slot_cap'] >= $ITEM_STORAGE_SLOT_MAX )
			render_error("item_storage_slot_cap at max: $ITEM_STORAGE_SLOT_MAX", FCODE(70401));

		// check star
		$cur_star = $general['star'];
		if ( $cur_star < $ITEM_EXPAND_STORAGE_SLOT_COST_STAR )
			render_error("not enough star: $cur_star < $ITEM_EXPAND_STORAGE_SLOT_COST_STAR", FCODE(70402));

		$terms = [];
		$terms['star'] = "star - $ITEM_EXPAND_STORAGE_SLOT_COST_STAR";
		$terms['item_storage_slot_cap'] = "item_storage_slot_cap + 1";
		$pairs = join_terms($terms);
		$query = "UPDATE general SET $pairs WHERE general_id = $general_id";

		$map = [];
		$map['star'] = $cur_star - $ITEM_EXPAND_STORAGE_SLOT_COST_STAR;
		$map['item_storage_slot_cap'] = $general['item_storage_slot_cap'] + 1;

		assert_render($tb->query_with_affected($query, 1));

		gamelog(__METHOD__, ['item_storage_slot_cap_cur_new' => $general['item_storage_slot_cap']+1]);

		assert_render($tb->end_txn());

		render_ok("success", $map);
	}

	public static function gift($tb) {
		global $TAG, $ALLIES, $EMPIRE, $user_id, $general_id, $dev_type, $dev_uuid, $ops, $status, $country;
		global $item_id;
		global $ITEM_OWNERLESS, $ITEM_MAKING, $ITEM_GENERAL_OWNED, $ITEM_MAIL_OWNED, $ITEM_OFFICER_OWNED;
		global $ITEM_TYPE_MAJOR_EQUIPS, $ITEM_TYPE_MAJOR_COMBATS, $ITEM_TYPE_MAJOR_CONSUMES;

		assert_render(auth::acl_check(['operator']), "invalid:acl"); // check ACL

		$qty = queryparam_fetch_int('qty');
		$type_major = queryparam_fetch_int('type_major');
		$type_minor = queryparam_fetch_int('type_minor');

		assert_render(1 <= $qty, "invalid:qty:$qty");

		$country = session_GET('country');
		$storage_mod = 0;
		$item = null; // overlap base item
		if ( $type_major == $ITEM_TYPE_MAJOR_COMBATS ) {
			$COMBATS = item::get_combats();

			assert_render(isset($COMBATS[$type_minor]), "invalid:type_minor:$type_minor");
			$COMBAT = $COMBATS[$type_minor];

			assert_render($COMBAT['force'] == NEUTRAL || $country == $COMBAT['force'], "invalid:force:$type_minor", FCODE(70102));

			if ( $COMBAT['limit_overlap'] > 1 ) {
				// overlap
				$items = item::select_all($tb, null,
						"(status = $ITEM_GENERAL_OWNED OR status = $ITEM_OFFICER_OWNED) ".
						" AND type_major = $type_major AND type_minor = $type_minor");

				if ( $items && sizeof($items) == 1 ) {
					assert_render(sizeof($items) == 1, "sizeof(items) == 1");
					$item = $items[0];

					$new_qty = $item['qty'] + $qty;
					if ( $new_qty > $COMBAT['limit_overlap'] ) {
						$map['old_qty'] = $item['qty'];
						$map['new_qty'] = $new_qty;
						$map['max_qty'] = $COMBAT['limit_overlap'];
						$map['fcode'] = 70105;

						render_error("exceeded qty overlap", $map);
					}
				} else
					elog("no overlap was triggered, new item");
			}
		} else if ( $type_major == $ITEM_TYPE_MAJOR_CONSUMES ) {
			$CONSUMES = item::get_consumes();

			assert_render(isset($CONSUMES[$type_minor]), "invalid:type_minor:$type_minor");
			$CONSUME = $CONSUMES[$type_minor];

			assert_render($CONSUME['force'] == NEUTRAL || $country == $CONSUME['force'], "invalid:force:$type_minor", FCODE(70102));

			if ( $CONSUME['limit_overlap'] > 1 ) {
				// overlap
				$items = item::select_all($tb, null,
						"(status = $ITEM_GENERAL_OWNED OR status = $ITEM_OFFICER_OWNED) ".
						" AND type_major = $type_major AND type_minor = $type_minor");
					
				if ( $items && sizeof($items) == 1 ) {
					assert_render(sizeof($items) == 1, "sizeof(items) == 1");
					$item = $items[0];

					$new_qty = $item['qty'] + $qty;
					if ( $new_qty > $CONSUME['limit_overlap'] ) {
						$map['old_qty'] = $item['qty'];
						$map['new_qty'] = $new_qty;
						$map['max_qty'] = $CONSUME['limit_overlap'];
						$map['fcode'] = 70105;
							
						render_error("exceeded qty overlap", $map);
					}
				} else
					elog("no overlap was triggered, new item");
			}

			$storage_mod = 1;
		} else {
			render_error("Unsupported type_major: " . $type_major);
		}
			
		// check storage capacity
		$general = general::select($tb, 'item_storage_slot_cur, item_storage_slot_cap');
		$storage_cur = $general['item_storage_slot_cur'];
		$storage_cap = $general['item_storage_slot_cap'];

		if ( $storage_cur + $storage_mod > $storage_cap ) {
			$map = [];
			$map['storage_cur'] = $storage_cur;
			$map['storage_cap'] = $storage_cap;
			$map['fcode'] = '26101';
			if ( !(dev && queryparam_fetch_int('ignore') > 0) )
				render_error('not enough storage', $map);
		}

		// update item
		$terms = [];
		$terms['general_id'] = $general_id;
		$terms['type_major'] = $type_major;
		$terms['type_minor'] = $type_minor;
		$terms['status'] = $ITEM_GENERAL_OWNED;
		$terms['qty'] = $item ? "qty + $qty" : $qty;
		$terms['willbe_made_at'] = null;

		$keys = $vals = [];
		$pairs = join_terms($terms, $keys, $vals);

		// overlapped
		if ( $item ) {
			$item_id = $item['item_id'];
			$query = "UPDATE item SET $pairs WHERE item_id = $item_id";
			assert_render($tb->query_with_affected($query, 1));
		} else {
			$query = "INSERT INTO item ($keys) VALUES ($vals)";
			assert_render($tb->query_with_affected($query, 1));
			$item_id = $tb->mc()->insert_id;
		}

		// update general
		$terms = [];
		$terms['item_storage_slot_cur'] = "(SELECT COUNT(*) FROM item WHERE general_id = $general_id AND status BETWEEN $ITEM_MAKING AND $ITEM_GENERAL_OWNED AND type_major > $ITEM_TYPE_MAJOR_COMBATS)";

		if ( sizeof($terms) > 0 ) {
			$pairs = join_terms($terms);
			$query = "UPDATE general SET $pairs WHERE general_id = $general_id";
			assert_render($tb->query($query));
		}

		$item = item::select($tb, null, "item_id = $item_id");

		gamelog(__METHOD__, ['item_id' => $item_id, 'type_major'=>$type_major, 'type_minor'=>$type_minor, 'qty'=>$qty]);

		assert_render($tb->end_txn());

		$map['items'] = [$item];

		render_ok('success', $map);
	}
}

if ( strstr($_SERVER["REQUEST_URI"], basename(__FILE__)) ) {

	$ops = explode(',', $op = queryparam_fetch('op', 'get'));

	$user_id = login_check();
	$general_id = session_GET('general_id');

	$item_id = queryparam_fetch_int('item_id');

	if ( sizeof(array_intersect_key(['get', 'clear', 'make_list', 'make', 'make_haste', 'consume', 'sell', 'gift', 'expand_storage_slot_cap'], $ops)) ) {
		for ( $retried = 1 ; $retried <= $TXN_RETRY_MAX ; $retried++ ) {
			try {
				$tb = new TxnBlock();

				if ( in_array("clear", $ops) ) item::clear($tb);

				item::resolve_making($general_id, $tb);

				if ( in_array("make", $ops) ) item::make($tb);
				else if ( in_array("make_list", $ops) ) item::make_list($tb);
				else if ( in_array("make_haste", $ops) ) item::make_haste($tb);
				else if ( in_array("consume", $ops) ) item::consume($tb);
				else if ( in_array("sell", $ops) ) item::sell($tb);
				else if ( in_array("gift", $ops) ) item::gift($tb);
				else if ( in_array("expand_storage_slot_cap", $ops) ) item::expand_storage_slot_cap($tb);

				item::get($tb); // embedes end_txn()

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
