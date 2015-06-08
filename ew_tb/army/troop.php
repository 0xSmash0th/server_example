<?php
require_once '../connect.php';
require_once '../general/general.php';
require_once '../build/construction.php';
require_once '../auth/event.php';
require_once '../auth/register.php';
require_once '../army/troop.php';

function compare_by_qty_then_cost_command_desc($a, $b) {
	$res = compare_by_key($b, $a, 'qty'); // DESC
	if ( $res == 0 )
		$res = compare_by_key($b, $a, 'cost_command'); // DESC
	return $res;
}

class troop {

	public static function get_units() {
		if ( $val = fetch_from_cache('constants:units') )
			return $val;

		$timer_bgn = microtime(true);

		$unitinfo = loadxml_as_dom('xml/unitinfo.xml');
		if ( !$unitinfo ) {
			elog("failed to loadxml: " . 'xml/unitinfo.xml');
			return null;
		}

		if (0) {
			// TODO: remove me, by combining unitinfo and unitinfo2
			$unitinfo2 = loadxml_as_dom('xml/unitinfo2.xml');
			if ( !$unitinfo2 ) {
				elog("failed to loadxml: " . 'xml/unitinfo2.xml');
				return null;
			}
		}

		$units = array();
		$units_allies = array();
		$units_empire = array();
		$unit_ids = [];
		$unit_ids['tanks'] = $unit_ids['humans'] = [];

		foreach ($unitinfo->xpath("/unitinfo/unit") as $node ) {
			$attrs = (array)$node->attributes();
			$pattrs = $attrs['@attributes'];

			$ukey = $pattrs["id"];

			$newattrs = array();
			foreach ( $pattrs as $key => $val ) {
				// skip client-side effective related keys
				if ( strpos($key, "cl_") === 0 || strpos($key, "ef") === 0 || strpos($key, "spr") === 0 || strpos($key, "sound") === 0)
					continue;

				$newattrs[$key] = $val;
			}
			$units[$ukey] = $newattrs;

			if (0) {
				// TODO: remove me, by combining unitinfo and unitinfo2
				$obtain_node = $unitinfo2->xpath("/unitinfo/unit[@id = $ukey]")[0];
				$obtain_attrs = (array)$obtain_node->attributes();
				$obtain_pattrs = $obtain_attrs['@attributes'];

				if ( empty($units[$ukey]['obtain_gold']) ) $units[$ukey]['obtain_gold'] = $obtain_pattrs['obtain_gold'];
				if ( empty($units[$ukey]['obtain_EXP']) ) $units[$ukey]['obtain_EXP'] = $obtain_pattrs['obtain_EXP'];
				if ( empty($units[$ukey]['obtain_honor']) ) $units[$ukey]['obtain_honor'] = $obtain_pattrs['obtain_honor'];
			}

			if ( $pattrs['force'] == ALLIES )
				$units_allies[] = $ukey;
			else if ( $pattrs['force'] == EMPIRE )
				$units_empire[] = $ukey;
			if ( $pattrs['type'] == '1' ) // do not user type_major
				$unit_ids['humans'][] = $ukey;
			else
				$unit_ids['tanks'][] = $ukey;
		}

		$units['units_allies'] = $units_allies;
		$units['units_empire'] = $units_empire;
		$units['unit_ids'] = $unit_ids;

		$timer_end = microtime(true);
		elog("time took troop for troop::get_units(): " . ($timer_end - $timer_bgn));

		store_into_cache('constants:units', $units);

		$bnis = array_keys($units);
		elog("troop unitkeys: " . json_encode($bnis));
		elog("troop units_allies: " . json_encode($units_allies));
		elog("troop units_empire: " . json_encode($units_empire));
		elog("troop unit_ids: " . json_encode($unit_ids));

		return $units;
	}

	public static function merge_all_troops_by_type_minor($tb) {
		global $user_id, $general_id;
		global $TROOP_TRAINING, $TROOP_TRAINED, $TROOP_BANDED;

		// remove qty == 0 troops
		$query = "DELETE FROM troop WHERE general_id = $general_id AND qty = 0";
		assert_render($tb->query($query));

		// merge troops by type_minor
		$query = "SELECT type_minor, COUNT(*) AS count, SUM(qty) AS total, MIN(troop_id) AS min_tid FROM troop "
				."WHERE general_id = $general_id AND status = $TROOP_TRAINED GROUP BY type_minor";
		assert_render($rs = $tb->query($query));
		$groups = ms_fetch_all($rs);
		foreach ( $groups as $group ) {
			if ( $group['count'] > 1 ) {
				elog("merge troops: ". pretty_json($group));

				$query = "DELETE FROM troop WHERE general_id = $general_id AND status = $TROOP_TRAINED "
				."AND type_minor = ". $group['type_minor'] ." AND troop_id > " . $group['min_tid'];
				assert_render($rs = $tb->query($query));

				$query = "UPDATE troop SET qty = ". $group['total'] ." WHERE general_id = $general_id AND troop_id = " . $group['min_tid'];
				assert_render($rs = $tb->query($query));
			}
		}
	}

	/**
	 * Resolve training's completion of current general
	 * @param Integer $general_id
	 * @param TxnBlock $tb
	 * @return mysqli:resultset
	 */
	public static function resolve_training($general_id, $tb) {
		global $TROOP_TRAINING, $TROOP_TRAINED, $TROOP_BANDED;

		$with_tb = $tb;

		if ( !$with_tb )
			$tb = new TxnBlock();

		$query = "UPDATE troop SET status = $TROOP_TRAINED WHERE general_id = $general_id AND status = $TROOP_TRAINING AND trained_at <= NOW()";
		assert_render($rs = $tb->query($query));

		if ( $tb->mc()->affected_rows > 0 ) {
			elog("resolve_training: updated: ". $tb->mc()->affected_rows . " rows");

			troop::merge_all_troops_by_type_minor($tb);
		}

		if ( !$with_tb )
			assert_render($tb->end_txn());

		return $rs;
	}

	/**
	 * @deprecated wont be used as of 0923
	 * @param unknown $tb
	 * @param unknown $country
	 * @param number $qty
	 * @return multitype:multitype:number unknown
	 */
	public static function generate_units($tb, $country, $qty = 1) {
		global $user_id, $general_id, $ops, $unit_id, $status;
		global $TROOP_TRAINING, $TROOP_TRAINED, $TROOP_BANDED;
		global $TROOP_TRAIN_QTY_MAX;

		assert_render(1 <= $country && $country <=3);

		$UNITS = troop::get_units();

		if ( $country == 1 )
			$unit_candidate_ids = $UNITS['units_allies'];
		else if ( $country == 2 )
			$unit_candidate_ids = $UNITS['units_empire'];

		assert_render(count($unit_candidate_ids) > 0, "count(unit_candidate_ids) > 0");

		$generated_units = array();

		for ( $i = 0 ; $i < $qty ; $i++ ) {
			$unit_type = $unit_candidate_ids[mt_rand(0, count($unit_candidate_ids)-1)];

			$UNIT = $UNITS[$unit_type];

			$new_unit = array();

			$new_unit['type_major'] = 2;
			if ( $UNIT['type'] == '1' )
				$new_unit['type_major'] = 1;
			$new_unit['type_minor'] = $unit_type;
			$new_unit['status'] = $TROOP_BANDED;

			$new_unit['qty'] = mt_rand($TROOP_TRAIN_QTY_MAX/2, $TROOP_TRAIN_QTY_MAX);
			$new_unit['slot'] = $i + 1; // sequential slot

			$generated_units[] = $new_unit;

			elog("generated unit: " . json_encode($new_unit));
		}
		return $generated_units;
	}


	public static function clear($tb) {
		global $TAG, $ALLIES, $EMPIRE, $user_id, $general_id, $dev_type, $dev_uuid, $ops, $status, $country;
		global $troop_id, $type_major;

		if ( dev ) {
			$query = "DELETE FROM troop WHERE general_id = $general_id;";
			assert_render($rs = $tb->query($query));
		}
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

		$query = "SELECT $select_expr FROM troop $where_condition /*BY_HELPER*/";
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
		$rows = troop::select_all($tb, $select_expr, $where_condition);
		if ( $rows && count($rows) == 1 )
			return $rows[0];
		return null;
	}

	public static function get($tb) {
		global $TAG, $ALLIES, $EMPIRE, $user_id, $general_id, $dev_type, $dev_uuid, $ops, $status, $country;
		global $troop_id, $type_major;
		global $TROOP_TRAINING, $TROOP_TRAINED, $TROOP_BANDED;

		$officer_id = queryparam_fetch_int('officer_id');

		$terms = [];
		$post_where = null;

		if ( $troop_id )
			$terms[] = "troop_id = $troop_id";
		if ( $status )
			$terms[] = "status = $status";
		if ( $type_major ) {
			if ( !(1 <= $type_major && $type_major <= 3 ) )
				render_error("invalid:type_major: $type_major");
			$terms[] = "type_major = $type_major";
		}
		if ( $officer_id && $officer_id > 0 ) {
			$terms[] = "officer_id = $officer_id";
			$post_where = " ORDER BY slot ";
		}

		$where = implode(' AND ', $terms);

		$troops = troop::select_all($tb, null, $where, $post_where);
		assert_render($tb->end_txn());

		if ( $troop_id && sizeof($troops) == 0 )
			render_error("invalid:troop_id: $troop_id");

		$map['troops'] = $troops;

		render_ok('success', $map);
	}

	public static function train($tb) {
		global $TAG, $ALLIES, $EMPIRE, $user_id, $general_id, $dev_type, $dev_uuid, $ops, $status, $country;
		global $troop_id, $type_major;
		global $TROOP_TRAINING, $TROOP_TRAINED, $TROOP_BANDED;
		global $TROOP_TRAIN_QTY_MAX;

		$qty = queryparam_fetch_int('qty');
		$type_major = queryparam_fetch_int('type_major');
		$type_minor = queryparam_fetch_int('type_minor');
			
		assert_render(1 <= $qty && $qty <= $TROOP_TRAIN_QTY_MAX, "invalid:qty: $qty");

		$UNITS = troop::get_units();

		$country = session_GET('country');
		$ukey = $type_minor;
		$UNIT = $UNITS[$ukey];
		assert_render($UNIT, "invalid:type_major or type_minor:$type_major or $type_minor");

		$training_time = queryparam_fetch_int('training_time');
		if ( $training_time == null )
			$training_time = $qty * $UNIT['cost_time'];
		assert_render($training_time > 0, "invalid:training_time:$training_time > 0");

		// check already training troop
		$query = "SELECT COUNT(*) FROM troop WHERE general_id = $general_id AND status = $TROOP_TRAINING";
		assert_render($rs = $tb->query($query));
		if ( ms_fetch_single_cell($rs) > 0 )
			render_error("another troop is already training", FCODE(10105));

		// check gold, honor
		$cost_gold = $UNIT['cost_money'] * $qty;
		$cost_honor = $UNIT['cost_honor'] * $qty;

		// apply effects
		$training_time = general::apply_effects($tb, 106, $training_time); // for all troops
		$cost_gold = general::apply_effects($tb, 113, $cost_gold);
		$cost_honor = general::apply_effects($tb, 113, $cost_honor);

		if ( $type_major == 1 ) { // for humans
			$training_time = general::apply_effects($tb, 108, $training_time);
			$cost_gold = general::apply_effects($tb, 111, $cost_gold);
			$cost_honor = general::apply_effects($tb, 111, $cost_honor);
		}
		else if ( $type_major == 2 ) { // for tanks
			$training_time = general::apply_effects($tb, 107, $training_time);
			$cost_gold = general::apply_effects($tb, 112, $cost_gold);
			$cost_honor = general::apply_effects($tb, 112, $cost_honor);
		}

		$general = general::select($tb, 'gold, honor, level, pop_cur, pop_max, building_list');
		if ( !($cost_gold <= $general['gold'] && $cost_honor <= $general['honor']) ) {
			$map['cost_gold'] = $cost_gold;
			$map['cost_honor'] = $cost_honor;
			$map['cur_gold'] = $general['gold'];
			$map['cur_honor'] = $general['honor'];
			$map['fcode'] = 10104;

			render_error("not enough gold or honor", $map);
		}

		// check general level
		if  ( $UNIT['req_level'] > 0 && $general['level'] < $UNIT['req_level'] ) {
			$map = array();
			$map['req_level'] = $UNIT['req_level'];
			$map['cur_level'] = $general['level'];
			$map['fcode'] = 10107;

			if ( !(dev && queryparam_fetch_int('ignore_building') > 0) )
				render_error("cur_level < req_level", $map);
		}

		// check general troop_capacity (population)
		if (0) {
			$pop_max = general::apply_effects($tb, 115, $general['pop_max']); // DEPRECATED as of 10.16
			if ( !($general['pop_cur'] + $qty <= $pop_max) ) {
				$map = [];
				$map['pop_cur'] = $general['pop_cur'];
				$map['pop_max'] = $pop_max;
				$map['qty'] = $qty;
				$map['fcode'] = 10108;

				if ( !(dev && queryparam_fetch_int('ignore') > 0) )
					render_error("not enough pop", $map);
			}
		}
		// check troop population limit per unit (10.30)
		$prev_troop = troop::select($tb, 'qty', "status = $TROOP_TRAINED AND type_major = $type_major AND type_minor = $type_minor");
		if ( $prev_troop ) {
			global $TROOP_POPULATION_LIMIT;
			elog("testing prev_troop.qty for population limit:$TROOP_POPULATION_LIMIT");
			if ( $prev_troop['qty'] + $qty > $TROOP_POPULATION_LIMIT ) {
				$map['old_qty'] = $prev_troop['qty'];
				$map['new_qty'] = $prev_troop['qty'] + $qty;
				$map['max_qty'] = $TROOP_POPULATION_LIMIT;
				$map['fcode'] = 10109;

				render_error("troop population reached at limit", $map);
			}
		}

		// check building dependency
		if  ( $UNIT['req_building'] > 0 ) {
			$req_building = $UNIT['req_building'];

			if ( construction::find_building($general['building_list'], $req_building) == 0 ) {
				$map = [];
				$map['req_building'] = $req_building;
				$map['fcode'] = 10106;

				if ( !(dev && queryparam_fetch_int('ignore_building') > 0) )
					render_error("no req_building was found", $map);
			}

			// DEPRECATED, we use better code above at 10.17
			if (0) {
				// check special-hq
				if ( isset($general['building_list']['shq_bid']) && $general['building_list']['shq_bid'] == $req_building )
					;
				else {
					$rows = construction::select_all($tb, 'cur_level', "building_id = $req_building");
					if ( sizeof($rows) == 0 ) {
						$map['fcode'] = 10106;
						if ( !(dev && queryparam_fetch_int('ignore_building') > 0) )
							render_error("no req_building was found", $map);
					}
				}
			}
		}

		// update general
		$terms = [];
		$terms['gold'] = "gold - $cost_gold";
		$terms['honor'] = "honor - $cost_honor";
		$terms['pop_cur'] = "pop_cur + $qty";

		$pairs = join_terms($terms);
		$query = "UPDATE general SET $pairs WHERE general_id = $general_id";
		assert_render($tb->query_with_affected($query, 1));

		// update troop
		$terms = [];
		$terms['general_id'] = $general_id;
		$terms['type_major'] = $type_major;
		$terms['type_minor'] = $type_minor;
		$terms['status'] = $TROOP_TRAINING;
		$terms['qty'] = $qty;
		$terms['training_at'] = "NOW()";
		$terms['trained_at'] = "TIMESTAMPADD(SECOND, $training_time, NOW())";

		$keys = $vals = '';
		join_terms($terms, $keys, $vals);
		$query = "INSERT INTO troop ($keys) VALUES ($vals)";
		assert_render($rs = $tb->query_with_affected($query, 1));

		$new_troop_id = $tb->mc()->insert_id;

		$troop = troop::select($tb, null, "troop_id = $new_troop_id");

		gamelog(__METHOD__, ['troop'=>$troop]);

		// post push
		$context = [];
		$context['user_id'] = $user_id;
		$context['dev_type'] = session_GET('dev_type');
		$context['dev_uuid'] = session_GET('dev_uuid');
		$context['src_id'] = "troop:train:$new_troop_id";
		$context['send_at'] = $troop['trained_at'];
		$context['body'] = "troop:train:$new_troop_id done";
		event::push_post($tb, $context);

		assert_render($tb->end_txn());

		$troop['training_time'] = $training_time;
		$map['troops'] = [$troop];

		render_ok('train started', $map);
	}

	public static function train_list($tb) {
		global $TAG, $ALLIES, $EMPIRE, $user_id, $general_id, $dev_type, $dev_uuid, $ops, $status, $country;
		global $troop_id, $type_major;
		global $TROOP_TRAINING, $TROOP_TRAINED, $TROOP_BANDED;

		if (0) {
			$query = sprintf("SELECT * FROM troop WHERE general_id = $general_id ");

			if ( $troop_id )
				$query .= " AND troop_id = $troop_id";
			if ( $status )
				$query .= " AND status = $status";
			if ( $type_major ) {
				if ( !(1 <= $type_major && $type_major <= 3 ) )
					render_error("invalid:type_major: $type_major");
				$query .= " AND type_major = $type_major";
			}
			if ( $officer_id && $officer_id > 0 ) {
				$query .= " AND officer_id = $officer_id ORDER BY slot ";
			}

			assert_render($rs = $tb->query($query));
		}

		$UNITS = troop::get_units();

		$general = general::select($tb, 'gold, honor, level, building_list');

		$constructions = construction::select_all($tb, 'building_id, cur_level');
		$cons = ['_'=>'_'];
		foreach ( $constructions as $ccols )
			$cons[$ccols['building_id']] = $ccols['cur_level'];

		// check available units by building or resources
		$result_units = array();

		$country = session_GET('country');

		$ukeys = array_keys($UNITS);
		sort($ukeys);

		foreach ($ukeys as $ukey) {
			if ( !is_numeric($ukey) )
				continue;

			$uval = $UNITS[$ukey];
			// check force
			if ( ($country == 1 && $uval['force'] == '1')
			|| ($country == 2 && $uval['force'] == '2') ) {
				$valid = 1;
				$reason = null;

				// 0905, do not check gold and honor
				// 				if ( $uval['cost'] <= $general['gold'] && $uval['honor'] <= $general['honor'] )
				// 					$valid = 1;

				if ( $uval['req_level'] > 0 && $general['level'] < $uval['req_level'] ) {
					$valid = 0;
					$reason['req_level'] = $uval['req_level'];
					$reason['cur_level'] = $general['level'];
				}
				if  ( $uval['req_building'] > 0 ) {
					if ( isset($general['building_list']['shq_bid']) && $general['building_list']['shq_bid'] == $uval['req_building'] )
						; // special-hq
					else if ( !key_exists($uval['req_building'], $cons) ) {
						$valid = 0;
						$reason['building_id'] = $uval['req_building'];
					}
				}

				$tuple = [];
				$tuple['type_minor'] = $uval['id'];
				$tuple['valid'] = $valid;
				$tuple['reason'] = $reason;

				$result_units[] = $tuple;
			}
		}

		assert_render($tb->end_txn());

		$map['units'] = $result_units;

		render_ok('success', $map);
	}

	public static function train_haste($tb) {
		global $TAG, $ALLIES, $EMPIRE, $user_id, $general_id, $dev_type, $dev_uuid, $ops, $status, $country;
		global $troop_id, $type_major;
		global $TROOP_TRAINING, $TROOP_TRAINED, $TROOP_BANDED;
		global $TROOP_TRAIN_HASTE_COST_STAR_PER_HOUR;

		$ignore_check_star = queryparam_fetch_int('ignore_check_star');
		$troop_id = queryparam_fetch_int('troop_id');  // troop_id to haste
		assert_render($troop_id > 0, 'invalid:troop_id');

		$query = "SELECT trained_at, TIMESTAMPDIFF(SECOND, NOW(), trained_at) AS dt_diff FROM troop "
				."WHERE general_id = $general_id AND troop_id = $troop_id AND status = $TROOP_TRAINING";
		$cols = ms_fetch_one($tb->query($query));
		if ( $cols == null )
			render_error("invalid:troop_id:or not training: $troop_id", FCODE(10103));

		if ( $cols['dt_diff'] <= 0 )
			render_error("invalid:troop_id:training done: $troop_id", FCODE(10101));

		$remain_seconds = $cols['dt_diff'];

		// star for an hour
		$cost_star = ($TROOP_TRAIN_HASTE_COST_STAR_PER_HOUR) * (int)(($remain_seconds-1)/3600)+1;

		elog("remain_seconds: $remain_seconds, cost_star: $cost_star");

		$general = general::select($tb, 'star');
		if ( $general['star'] < $cost_star && !(dev && $ignore_check_star > 0))
			render_error("not enough star: " . $general['star'] . " < $cost_star", array('fcode'=>10102, 'cost_star'=>$cost_star));

		$query = "UPDATE general SET star = star - $cost_star WHERE general_id = $general_id";
		assert_render($tb->query_with_affected($query, 1), "train_haste:update:star");
			
		$query = "UPDATE troop SET trained_at = NOW() WHERE general_id = $general_id AND troop_id = $troop_id AND status = $TROOP_TRAINING;";
		assert_render($tb->query_with_affected($query, 1), "train_haste:reset:trained_at");

		troop::resolve_training($general_id, $tb);

		$map['troops'] = $troops = troop::select_all($tb, null, "troop_id = $troop_id");

		gamelog(__METHOD__, ['troop'=>$troops[0]]);

		event::push_cancel($tb, "troop:$troop_id");

		assert_render($tb->end_txn());

		render_ok('train_haste', $map);
	}

	public static function band($tb) {
		global $TAG, $ALLIES, $EMPIRE, $user_id, $general_id, $dev_type, $dev_uuid, $ops, $status, $country;
		global $troop_id, $type_major;
		global $TROOP_TRAINING, $TROOP_TRAINED, $TROOP_BANDED;
		global $OFFICER_UNHIRED, $OFFICER_HIRED, $OFFICER_TRAINING, $OFFICER_HEALING, $OFFICER_DEAD;

		$bands_str = queryparam_fetch('bands');
		assert_render($bands_str, "invalid:bands");
		elog("bands_str: $bands_str");
		assert_render($bands = json_decode($bands_str, true), "json_decode:bands");
		assert_render(sizeof($bands) == 1, "bands should have 1 slot info");

		$command_max = -1;
		$command_cur = -1;
		$banded_troop_ids = [];
		$target_officer_id = null;

		$UNITS = troop::get_units();

		foreach ($bands as $band) {
			$qty = $band['qty']; // amount to band
			$slot = $band['slot']; // slot to use
			$troop_id = $band['troop_id'];  // source troop_id
			$officer_id = $band['officer_id']; // target officer_id
			if ( $target_officer_id == null )
				$target_officer_id = $officer_id;
			if ( $target_officer_id != $officer_id )
				render_error("invalid:officer_id:not universally equal:$officer_id");

			assert_render(1 <= $qty, '1 <= qty');
			assert_render(1 <= $slot && $slot <= 6, '1 <= slot <= 6');
			assert_render($troop_id > 0, 'troop_id');
			assert_render($officer_id > 0, 'officer_id');

			// check src troop is ok
			$query = sprintf("SELECT * FROM troop WHERE general_id = $general_id AND troop_id = $troop_id"
					." AND status = $TROOP_TRAINED AND qty >= $qty;");
			if ( !($rs = $tb->query($query)) )
				render_error("invalid:troop_id:$troop_id");
			$troop = ms_fetch_one($rs);
			assert_render($troop, "invalid:source:troop:$troop_id");

			// check officer_id is ok
			$officer = officer::select($tb, null, "officer_id = $officer_id AND status >= $OFFICER_HIRED");
			assert_render($officer, "invalid:officer:$officer_id");

			// check target officer/slot troop
			$query = sprintf("SELECT * FROM troop WHERE general_id = $general_id AND officer_id = $officer_id AND slot = $slot;");
			if ( !($rs = $tb->query($query)) )
				render_error("invalid:slot:$slot");
			$target_troop = ms_fetch_one($rs);
			assert_render(!$target_troop, 'invalid:slot:duplicated', FCODE(10302));

			// check officer's command_cur
			if ( $command_max < 0 ) {
				$command_max = general::apply_effects($tb, 117, $officer['command_max']);
			}

			$command_mod = ($qty*$UNITS[$troop['type_minor']]['cost_command']);
			if ( $command_cur < 0 )
				$command_cur = $officer['command_cur'];

			if ( !($command_cur + $command_mod <= $command_max) ) {
				if ( !(dev && queryparam_fetch_int('ignore_command') > 0) )
					render_error("not enough command : cur: $command_cur, mod: $command_mod, max: $command_max", FCODE(10301));
			}
			$command_cur += $command_mod;

			// band(split) troop
			if ( $qty == $troop['qty'] ) {
				$query = "UPDATE troop SET status = $TROOP_BANDED, officer_id = $officer_id, slot = $slot "
				." WHERE general_id = $general_id AND troop_id = $troop_id";

				assert_render($tb->query_with_affected($query, 1), 'troop:band');

				$banded_troop_id = $troop_id;
			} else {
				$out_keys = $out_vals = $terms = [];
				$terms['general_id'] = $general_id;
				$terms['type_major'] = $troop['type_major'];
				$terms['type_minor'] = $troop['type_minor'];
				$terms['status'] = $TROOP_BANDED;
				$terms['qty'] = $qty;
				$terms['training_at'] = ms_quote($troop['training_at']);
				$terms['trained_at'] = ms_quote($troop['trained_at']);
				$terms['officer_id'] = $officer_id;
				$terms['slot'] = $slot;

				join_terms($terms, $out_keys, $out_vals);

				$query = "INSERT INTO troop ($out_keys) VALUES ($out_vals)";
				assert_render($tb->query_with_affected($query, 1), 'troop:band');

				$banded_troop_id = $tb->mc()->insert_id;

				// adjust qty on source troop
				$query = "UPDATE troop SET qty = qty - $qty WHERE general_id = $general_id AND troop_id = $troop_id ";
				assert_render($tb->query_with_affected($query, 1), 'troop:band');
			}

			$banded_troop_ids[] = $banded_troop_id; // push
		}

		// update officer's command_cur
		if ( $target_officer_id ) {
			$query = "UPDATE officer SET command_cur = $command_cur WHERE officer_id = $target_officer_id";
			assert_render($tb->query_with_affected($query, 1));
		}

		if ( sizeof($banded_troop_ids) == 1 )
			$where = "troop_id = " . $banded_troop_ids[0];
		else {
			$listed_banded_troop_ids = implode(',', $banded_troop_ids);
			elog("banded_troop_ids: $listed_banded_troop_ids");
			$where = "troop_id IN ($listed_banded_troop_ids)";
		}

		$map['troops'] = $troops = troop::select_all($tb, null, $where);

		gamelog(__METHOD__, ['bands'=>$bands]);

		assert_render($tb->end_txn(), 'troop:band');

		render_ok('banded', $map);
	}

	public static function band_full($tb) {
		global $TAG, $ALLIES, $EMPIRE, $user_id, $general_id, $dev_type, $dev_uuid, $ops, $status, $country;
		global $troop_id, $type_major;
		global $TROOP_TRAINING, $TROOP_TRAINED, $TROOP_BANDED;
		global $OFFICER_UNHIRED, $OFFICER_HIRED, $OFFICER_TRAINING, $OFFICER_HEALING, $OFFICER_DEAD;

		$bands_str = queryparam_fetch('bands');
		assert_render($bands_str, "invalid:bands");
		elog("bands_str: $bands_str");
		assert_render($bands = json_decode($bands_str, true), "json_decode:bands");
		assert_render(1 <= sizeof($bands) && sizeof($bands) <= 6, "bands should have [1,6] slot info");

		$command_max = -1;
		$command_cur = 0;
		$banded_troop_ids = [];
		$target_officer_id = null;
		$src_troops = ["d"=>null];
		$slots_used = [];
		$target_officer = null;

		$UNITS = troop::get_units();

		foreach ($bands as $band) {
			$qty = int_check($band['qty']); // amount to band
			$slot = int_check($band['slot']); // slot to use
			$type_major = int_check($band['type_major']);  // source troop's type_major
			$type_minor = int_check($band['type_minor']);  // source troop's type_minor
			$officer_id = int_check($band['officer_id']); // target officer_id

			assert_render(1 <= $qty, "EXPECTED 1 <= qty BUT $qty");
			assert_render(1 <= $slot && $slot <= 6, "EXPECTED 1 <= slot <= 6 BUT $slot");
			assert_render($officer_id > 0, 'officer_id');

			// check slot is okay (no duplicate)
			assert_render(!in_array($slot, $slots_used), "invalid:slot:duplicated:$slot", FCODE(10303));
			$slots_used[] = $slot;

			// check officer_id is ok
			if ( $target_officer_id == null )
				$target_officer_id = $officer_id;
			if ( $target_officer_id != $officer_id )
				render_error("invalid:officer_id:not universally equal:$officer_id");

			if ( !$target_officer ) {
				$target_officer = officer::select($tb, null, "officer_id = $officer_id AND status >= $OFFICER_HIRED");
				assert_render($target_officer, "invalid:officer:$officer_id");

				// disband all troops
				$query = "UPDATE troop SET officer_id = NULL, slot = NULL, status = $TROOP_TRAINED WHERE general_id = $general_id AND officer_id = $officer_id";
				assert_render($tb->query($query));

				troop::merge_all_troops_by_type_minor($tb);
			}

			// check src troop is ok
			if ( !isset($src_troops[$type_minor]) ) {
				$src_troop = troop::select($tb, null, "status = $TROOP_TRAINED AND type_major = $type_major AND type_minor = $type_minor AND qty >= $qty");
				assert_render($src_troop, "invalid:source:troop:type_minor:$type_minor");

				$src_troops[$type_minor] = $src_troop;
				$src_troops[$type_minor]['qty_remain'] = $src_troops[$type_minor]['qty'];
			}
			$troop = $src_troops[$type_minor];
			$src_troop_id = $troop['troop_id'];

			// check qty
			if ( $src_troops[$type_minor]['qty_remain'] < $qty )
				assert_render("not enough qty:troop_id:$type_minor");
			$src_troops[$type_minor]['qty_remain'] -= $qty;

			// check officer's command_cur
			if ( $command_max < 0 )
				$command_max = general::apply_effects($tb, 117, $target_officer['command_max']);

			$command_mod = ($qty*$UNITS[$troop['type_minor']]['cost_command']);
			if ( !($command_cur + $command_mod <= $command_max) ) {
				if ( !(dev && queryparam_fetch_int('ignore_command') > 0) )
					render_error("not enough command : cur: $command_cur, mod: $command_mod, max: $command_max", FCODE(10301));
			}
			$command_cur += $command_mod;

			// band(split) troop
			$out_keys = $out_vals = $terms = [];
			$terms['general_id'] = $general_id;
			$terms['type_major'] = $troop['type_major'];
			$terms['type_minor'] = $troop['type_minor'];
			$terms['status'] = $TROOP_BANDED;
			$terms['qty'] = $qty;
			$terms['training_at'] = ms_quote($troop['training_at']);
			$terms['trained_at'] = ms_quote($troop['trained_at']);
			$terms['officer_id'] = $officer_id;
			$terms['slot'] = $slot;

			join_terms($terms, $out_keys, $out_vals);

			$query = "INSERT INTO troop ($out_keys) VALUES ($out_vals)";
			assert_render($tb->query_with_affected($query, 1), 'troop:band');

			$banded_troop_id = $tb->mc()->insert_id;

			// adjust qty on source troop
			$query = "UPDATE troop SET qty = qty - $qty WHERE general_id = $general_id AND troop_id = $src_troop_id";
			assert_render($tb->query_with_affected($query, 1), 'troop:band');

			$banded_troop_ids[] = $banded_troop_id; // push
		}

		// update officer's command_cur
		assert_render($target_officer_id);

		$query = "UPDATE officer SET command_cur = $command_cur WHERE officer_id = $target_officer_id";
		assert_render($tb->query($query));

		if ( sizeof($banded_troop_ids) == 1 )
			$where = "troop_id = " . $banded_troop_ids[0];
		else {
			$listed_banded_troop_ids = implode(',', $banded_troop_ids);
			elog("banded_troop_ids: $listed_banded_troop_ids");
			$where = "troop_id IN ($listed_banded_troop_ids)";
		}

		$map['troops'] = $troops = troop::select_all($tb, null, $where);

		gamelog(__METHOD__, ['bands'=>$bands]);

		assert_render($tb->end_txn(), 'troop:band');

		render_ok('banded', $map);
	}

	public static function band_auto($tb) {
		global $TAG, $ALLIES, $EMPIRE, $user_id, $general_id, $dev_type, $dev_uuid, $ops, $status, $country;
		global $troop_id, $type_major;
		global $TROOP_TRAINING, $TROOP_TRAINED, $TROOP_BANDED;
		global $OFFICER_UNHIRED, $OFFICER_HIRED, $OFFICER_TRAINING, $OFFICER_HEALING, $OFFICER_DEAD;

		$officer_id = queryparam_fetch_int('officer_id');
		$officer = officer::select($tb, null, "officer_id = $officer_id AND status >= $OFFICER_HIRED");
		assert_render($officer, "invalid:officer_id:$officer_id");

		// disband all troops belong to officer
		$terms = [];
		$terms['officer_id'] = null;
		$terms['slot'] = null;
		$terms['status'] = $TROOP_TRAINED;
		$pairs = join_terms($terms);

		$query = "UPDATE troop SET $pairs WHERE general_id = $general_id AND officer_id = $officer_id";
		assert_render($tb->query($query));

		troop::merge_all_troops_by_type_minor($tb);

		$command_cur = 0;
		$command_min = 300; // TODO: where this came from...
		$command_max = general::apply_effects($tb, 117, $officer['command_max']);

		$UNITS = troop::get_units();

		$troops_all = troop::select_all($tb, null, "status = $TROOP_TRAINED");
		$troops = [];

		$A = intval($command_min / 6);
		foreach ($troops_all as $troop) {
			$UNIT = $UNITS[$troop['type_minor']];
			if ( $troop['qty'] * $UNIT['cost_command'] > $A )
				$troops[] = $troop;
		}
		$B = 0;
		foreach ($troops as &$troop) {
			$UNIT = $UNITS[$troop['type_minor']];
			$B += $troop['qty'] * $UNIT['cost_command'];
		}
		assert_render($B >= $command_min, "not enough candidate troops commands", FCODE(10501));

		$slot_limit = intval($command_max/6);
		$slots = [];
		$humans_num = 0;
		$tanks_num = 0;
		for ($slot_num = 1 ; $slot_num <= 6 ; $slot_num++ ) {
			$ftroops = [];
			if ( $slot_num <= 3 ) {
				// filter out, [heal, repair] or [range_min >= 2] units
				foreach ($troops as &$troop) {
					$UNIT = $UNITS[$troop['type_minor']];
					if ( $UNIT['weapon'] == 6 || $UNIT['weapon'] == 7 ) // [heal, repair]
						continue;
					if ( $UNIT['range_min'] >= 2 ) // [range_min >= 2]
						continue;
					$troop['cost_command'] = $UNIT['cost_command'];
					$ftroops[] = &$troop;
				}
				assert_render(sizeof($ftroops) > 0, "front-line band failure: no possible units", FCODE(10502));
				usort($ftroops, 'compare_by_qty_then_cost_command_desc');
			}
			else if ( 4 <= $slot_num && $slot_num <= 5 ) {
				// filter out, [range_max <= 1] units
				foreach ($troops as &$troop) {
					$UNIT = $UNITS[$troop['type_minor']];
					if ( $UNIT['range_max'] <= 1 ) // [range_max <= 1]
						continue;
					$troop['cost_command'] = $UNIT['cost_command'];
					$ftroops[] = &$troop;
				}
				assert_render(sizeof($ftroops) > 0, "back-line(cover) band failure: no possible units", FCODE(10503));
				usort($ftroops, 'compare_by_qty_then_cost_command_desc');
			} else if ( $slot_num == 6 ) {
				$heals = [];
				$repairs = [];
				$ranges = [];
				foreach ($troops as &$troop) {
					$UNIT = $UNITS[$troop['type_minor']];
					if ( $UNIT['weapon'] == 6 ) $heals[] = &$troop;
					else if ( $UNIT['weapon'] == 7 ) $repairs[] = &$troop;
					else if ( $UNIT['range_max'] > 1 ) // [range_max > 1]
						$ranges[] = &$troop;
					else
						continue;

					$troop['cost_command'] = $UNIT['cost_command'];
					$ftroops[] = &$troop;
				}
				assert_render(sizeof($ftroops) > 0, "back-line(support) band failure: no possible units", FCODE(10504));

				if ( sizeof($heals) == 0 ) $humans_num = 0;
				if ( sizeof($repairs) == 0 ) $tanks_num = 0;
				elog("human, tank numbers [$humans_num, $tanks_num] for support_troop");

				// 				elog("heals: " . pretty_json($heals));
				// 				elog("repairs: " . pretty_json($repairs));
				// 				elog("ranges: " . pretty_json($ranges));

				$support_troop = null;
				if ( $humans_num > 0 || $tanks_num > 0 ) {
					if ( $humans_num >= $tanks_num && sizeof($heals) > 0 ) {
						$support_troop = $heals[mt_rand(0, sizeof($heals)-1)];
						elog("support_troop was selected from HEALS: " . pretty_json($support_troop));
					} else if ( $humans_num <= $tanks_num && sizeof($repairs) > 0 ) {
						$support_troop = $repairs[mt_rand(0, sizeof($repairs)-1)];
						elog("support_troop was selected from REPAIRS: " . pretty_json($support_troop));
					}
				}
				if ( !$support_troop ) {
					usort($ranges, 'compare_by_qty_then_cost_command_desc');
					$support_troop = $ranges[0];
					elog("support_troop was selected from RANGES: " . pretty_json($support_troop));
				}
				$ftroops = [&$support_troop];
			}
			$stroop = &$ftroops[0];
			// 			elog("stroop: " . pretty_json($stroop));
			elog(sprintf("slot_num[$slot_num] selected by [minor: %d, qty: %d]", $stroop['type_minor'], $stroop['qty']));

			$new_qty = intval(floor(floatval($slot_limit) / $stroop['cost_command']));
			$new_qty = min($new_qty, $stroop['qty']);
			$stroop['qty'] -= $new_qty; // this modifies original $troop

			$slots[$slot_num] = ['slot_num'=>$slot_num];
			$slots[$slot_num]['qty'] = $new_qty;
			$slots[$slot_num]['troop'] = $stroop;

			$command_mod = $new_qty * $stroop['cost_command'];
			$command_cur += $command_mod;

			if ( $UNITS[$troop['type_minor']]['type'] == 1 ) $humans_num += $command_mod;
			else if ( $UNITS[$troop['type_minor']]['type'] == 2 ) $tanks_num += $command_mod;
		}

		$bands = [];
		$banded_troop_ids = [];
		foreach ($slots as $slot_num => $slot) {
			$troop = $slot['troop'];
			$qty = $slot['qty'];

			if ( $qty <= 0 )
				continue;

			// band(split) troop
			$terms = [];
			$terms['general_id'] = $general_id;
			$terms['type_major'] = $troop['type_major'];
			$terms['type_minor'] = $troop['type_minor'];
			$terms['status'] = $TROOP_BANDED;
			$terms['qty'] = $qty;
			$terms['training_at'] = ms_quote($troop['training_at']);
			$terms['trained_at'] = ms_quote($troop['trained_at']);
			$terms['officer_id'] = $officer_id;
			$terms['slot'] = $slot_num;

			$keys = $vals = [];
			join_terms($terms, $keys, $vals);

			$query = "INSERT INTO troop ($keys) VALUES ($vals)";
			assert_render($tb->query_with_affected($query, 1), 'troop:band');

			$banded_troop_id = $tb->mc()->insert_id;

			// adjust qty on source troop
			$src_troop_id = $troop['troop_id'];
			$query = "UPDATE troop SET qty = qty - $qty WHERE general_id = $general_id AND troop_id = $src_troop_id";
			assert_render($tb->query_with_affected($query, 1), "troop:band:src_troop_id:$src_troop_id");

			$band = $terms;
			$band['troop_id'] = $banded_troop_id;
			$bands[] = $band;

			$banded_troop_ids[] = $banded_troop_id; // push
		}

		assert_render($command_cur <= $command_max, "not enough command, band_auto has problem in logic", FCODE(10505));

		// update officer's command_cur
		$query = "UPDATE officer SET command_cur = $command_cur WHERE officer_id = $officer_id";
		assert_render($tb->query($query));

		troop::merge_all_troops_by_type_minor($tb);

		gamelog(__METHOD__, ['bands'=>$bands]);

		assert_render($tb->end_txn(), 'troop:band');

		render_ok('banded');
	}

	public static function band_reinforce($tb) {
		global $TAG, $ALLIES, $EMPIRE, $user_id, $general_id, $dev_type, $dev_uuid, $ops, $status, $country;
		global $troop_id, $type_major;
		global $TROOP_TRAINING, $TROOP_TRAINED, $TROOP_BANDED;

		render_error("nyi");
	}

	public static function disband($tb) {
		global $TAG, $ALLIES, $EMPIRE, $user_id, $general_id, $dev_type, $dev_uuid, $ops, $status, $country;
		global $troop_id, $type_major;
		global $TROOP_TRAINING, $TROOP_TRAINED, $TROOP_BANDED;

		$troop_id = queryparam_fetch_int('troop_id');  // troop_id to disband
		assert_render($troop_id > 0, 'invalid:troop_id');

		// check src troop is ok
		$query = sprintf("SELECT * FROM troop WHERE general_id = $general_id AND troop_id = $troop_id AND status = $TROOP_BANDED;");
		if ( !($rs = $tb->query($query)) )
			render_error("invalid:troop_id");
		$troop = ms_fetch_one($rs);
		assert_render($troop, 'troop');
		$qty = $troop['qty'];
		$disbanding_officer_id = $troop['officer_id'];

		// check merge target is available
		$query = sprintf("SELECT * FROM troop WHERE general_id = $general_id AND status = $TROOP_TRAINED "
				." AND type_major = %s AND type_minor = %s;",
				$troop['type_major'], $troop['type_minor']);
		if ( !($rs = $tb->query($query)) )
			render_error();
		$merge_target_troop = ms_fetch_one($rs);

		if ( $merge_target_troop ) {
			// needs merge as type_major, type_minor exists
			elog("troop: MERGING " . json_encode($troop, JSON_NUMERIC_CHECK)
			. " TO " . json_encode($merge_target_troop, JSON_NUMERIC_CHECK));

			$query = sprintf("UPDATE troop SET qty = qty + %s "
					." WHERE general_id = $general_id AND troop_id = %s", $troop['qty'], $merge_target_troop['troop_id']);
			if ( !($rs = $tb->query($query)) )
				render_error();
			assert_render($rs && $tb->mc()->affected_rows == 1, 'troop:disband:partial');

			$query = "DELETE FROM troop WHERE general_id = $general_id AND troop_id = $troop_id";
			assert_render($tb->query_with_affected($query, 1), 'troop:disband:partial');
		}
		else {
			$query = "UPDATE troop SET status = $TROOP_TRAINED, officer_id = NULL, slot = NULL "
			." WHERE general_id = $general_id AND troop_id = $troop_id";

			assert_render($tb->query_with_affected($query, 1), 'troop:disband:entire');
		}

		// update officer's command_cur
		$UNITS = troop::get_units();
		$command_mod = $qty * $UNITS[$troop['type_minor']]['cost_command'];
		$query = "UPDATE officer SET command_cur = command_cur - $command_mod WHERE officer_id = $disbanding_officer_id";

		assert_render($tb->query_with_affected($query, 1));

		gamelog(__METHOD__, ['troop'=>$troop]);

		assert_render($tb->end_txn());

		render_ok('disbanded');
	}

	public static function disband_all($tb) {
		global $TAG, $ALLIES, $EMPIRE, $user_id, $general_id, $dev_type, $dev_uuid, $ops, $status, $country;
		global $troop_id, $type_major;
		global $TROOP_TRAINING, $TROOP_TRAINED, $TROOP_BANDED;

		$officer_id = queryparam_fetch_int('officer_id');
		$officer = officer::select($tb, 'officer_id', "officer_id = $officer_id");
		assert_render($officer, "invalid:officer_id:$officer_id");

		$query = "UPDATE troop SET status = $TROOP_TRAINED, officer_id = NULL, slot = NULL WHERE general_id = $general_id AND officer_id = $officer_id";
		assert_render($tb->query($query));

		$query = "UPDATE officer SET command_cur = 0 WHERE officer_id = $officer_id";
		assert_render($tb->query($query));

		troop::merge_all_troops_by_type_minor($tb);

		gamelog(__METHOD__, ['troop'=>$troop]);

		assert_render($tb->end_txn());

		render_ok('disbanded');
	}

	public static function gift($tb) {
		global $TAG, $ALLIES, $EMPIRE, $user_id, $general_id, $dev_type, $dev_uuid, $ops, $status, $country;
		global $TROOP_TRAINING, $TROOP_TRAINED, $TROOP_BANDED;
		global $OFFICER_UNHIRED, $OFFICER_HIRED, $OFFICER_TRAINING, $OFFICER_HEALING, $OFFICER_DEAD;

		assert_render(auth::acl_check(['operator']), "invalid:acl"); // check ACL

		$country = session_GET('country');
		$UNITS = troop::get_units();

		$gift_all_units = queryparam_fetch_int('gift_all_units');

		$gifts = [];
		$querys = [];
		if ( $gift_all_units > 0 ) {
			$qty_all = 0;
			foreach ($UNITS as $ukey => $UNIT) {
				if ( !is_numeric($ukey) ) continue;

				if ( $UNIT['force'] == NEUTRAL || $UNIT['force'] == $country ) {
					$terms = [];
					$terms['general_id'] = $general_id;
					$terms['type_major'] = $UNIT['type'];
					$terms['type_minor'] = $UNIT['id'];
					$terms['status'] = $TROOP_TRAINED;
					$terms['qty'] = $gift_all_units;
					$terms['training_at'] = "NOW()";

					$keys = $vals = [];
					join_terms($terms, $keys, $vals);
					$query = "INSERT INTO troop ($keys) VALUES ($vals)";
					$querys[] = $query;

					$gifts[] = $terms; // for gamelog

					$qty_all += $gift_all_units;
				}
			}
			if (sizeof($querys) > 0) {
				$query = "UPDATE general SET pop_cur = pop_cur + $qty_all WHERE general_id = $general_id";
				$querys[] = $query;

				assert_render($tb->multi_query($querys));
			}
		} else {
			render_error("NYI");

			$raw_troop_gifts = queryparam_fetch('troop_gifts');
			$troop_gifts = @json_decode($raw_troop_gifts, true);
			assert_render($troop_gifts, "invalid:troop_gifts:$raw_troop_gifts");

			foreach($troop_gifts as $gift) {
				$qty = $gift['qty'];
				// 			$type_major = $gifr['type_major'];
				$type_minor = $gifr['type_minor'];

				assert_render(1 <= $qty, "invalid:qty:$qty");

				$UNIT = @$UNITS[$type_minor];
			}
		}
		troop::merge_all_troops_by_type_minor($tb);

		gamelog(__METHOD__, ['gifts'=>$gifts]);
	}
}

if ( strstr($_SERVER["REQUEST_URI"], basename(__FILE__)) ) {

	$ops = explode(',', $op = queryparam_fetch('op', 'get'));

	$user_id = login_check();
	$general_id = session_GET('general_id');

	$status = queryparam_fetch_int('status');
	$troop_id = queryparam_fetch_int('troop_id');
	$type_major = queryparam_fetch_int('type_major');

	if ( $status )
		assert_render($TROOP_TRAINING <= $status && $status <= $TROOP_BANDED, "invalid:status: $status");

	if ( sizeof(array_intersect_key(['get', 'train', 'train_list', 'train_haste', 'clear']
			+ ['band', 'band_full', 'band_auto', 'band_reinforce', 'disband', 'disband_all']
			+ ['gift'], $ops)) ) {
		for ( $retried = 1 ; $retried <= $TXN_RETRY_MAX ; $retried++ ) {
			try {
				$tb = new TxnBlock();

				if ( in_array("clear", $ops) ) troop::clear($tb);

				troop::resolve_training($general_id, $tb);

				if ( in_array("train", $ops) ) troop::train($tb);
				else if ( in_array("train_list", $ops) ) troop::train_list($tb);
				else if ( in_array("train_haste", $ops) ) troop::train_haste($tb);
				else if ( in_array("band", $ops) ) troop::band($tb);
				else if ( in_array("band_full", $ops) ) troop::band_full($tb);
				else if ( in_array("band_auto", $ops) ) troop::band_auto($tb);
				else if ( in_array("band_reinforce", $ops) ) troop::band_reinforce($tb);
				else if ( in_array("disband", $ops) ) troop::disband($tb);
				else if ( in_array("disband_all", $ops) ) troop::disband_all($tb);
				else if ( in_array("gift", $ops) ) troop::gift($tb);

				troop::get($tb); // embedes end_txn()

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
