<?php
require_once '../connect.php';
require_once '../general/general.php';

class construction {

	public static function get_buildings() {
		if ( $val = fetch_from_cache('constants:buildings') )
			return $val;

		$timer_bgn = microtime(true);

		$buildinginfo = loadxml_as_dom('xml/buildinginfo.xml');
		$building_levelinfo = loadxml_as_dom('xml/building_levelinfo.xml');
		if ( !$buildinginfo || !$building_levelinfo )
			return null;

		$buildings = array();
		$buildings_allies = array();
		$buildings_empire = array();
		$buildings_common = array();
		$buildings_hq = array();
		$buildings_unlimited = array();

		foreach ($buildinginfo->xpath("/buildinginfo/building") as $node ) {
			$attrs = (array)$node->attributes();
			$pattrs = $attrs['@attributes'];

			// 			$buildings[$pattrs["id"]] = $pattrs;
			$buildings[$pattrs["nameindex"]] = $pattrs;

			if ( $pattrs['force'] == 'Allies' )
				$buildings_allies[] = $pattrs["nameindex"];
			else if ( $pattrs['force'] == 'Empire' )
				$buildings_empire[] = $pattrs["nameindex"];
			else if ( $pattrs['force'] == 'Common' )
				$buildings_common[] = $pattrs["nameindex"];
			if ( stristr($pattrs['id'], 'hq') )
				$buildings_hq[] = $pattrs["nameindex"];
			if ( $pattrs['buildLimit'] > 1 )
				$buildings_unlimited[] = $pattrs["nameindex"];
		}

		foreach ($buildings as $nameindex => $building ) {
			$bid = $building['id'];
			if ( strstr($bid, 'HQ') ) {
				$xpath = "//building[@id='HQ']/level";
			} else {
				if ( strstr($bid, 'EM_') )
					$xpath = "//building[@subId='$bid']/level";
				else
					$xpath = "//building[@id='$bid']/level";
			}

			$levels = array();
			foreach($building_levelinfo->xpath($xpath) as $level ) {
				$level_attrs = (array)$level->attributes();
				$level_pattrs = $level_attrs['@attributes'];
					
				$levels[$level_pattrs['level']] = $level_pattrs;
			}

			elog(sprintf("setting [%2d] levels for building:id [%s]", count($levels), $bid));
			$buildings[$nameindex]['levels'] = $levels;
		}

		$buildings['buildings_allies'] = $buildings_allies;
		$buildings['buildings_empire'] = $buildings_empire;
		$buildings['buildings_common'] = $buildings_common;
		$buildings['buildings_hq'] = $buildings_hq;
		$buildings['buildings_unlimited'] = $buildings_unlimited;

		$timer_end = microtime(true);
		elog("time took building for construction::get_buildings(): " . ($timer_end - $timer_bgn));

		store_into_cache('constants:buildings', $buildings);

		$bnis = array_keys($buildings);
		elog("building nameindexes: " . json_encode($bnis));
		elog("buildings_allies nameindexes: " . json_encode($buildings_allies));
		elog("buildings_empire nameindexes: " . json_encode($buildings_empire));
		elog("buildings_common nameindexes: " . json_encode($buildings_common));
		elog("buildings_hq nameindexes: " . json_encode($buildings_hq));
		elog("buildings_unlimited nameindexes: " . json_encode($buildings_unlimited));

		return $buildings;
	}

	/**
	 * Check that do we need default buildings, usually HQ or EM_HQ
	 * @param unknown $tb
	 * @param unknown $general_id
	 * @param unknown $country
	 * @param string $count_before
	 */
	public static function default_buildings($tb, $general_id, $country, $count_before = false) {
		global $BLD_POS_MIN, $BLD_POS_MAX, $BLD_COOLTIME_LIMIT;
		global $BLD_EMPTY, $BLD_BUILDING, $BLD_UPGRADING, $BLD_COMPLETED;
		global $BLD_DEFAULT_HQ_POSITION, $BLD_DEFAULT_HQ_ID_ALLIES, $BLD_DEFAULT_HQ_ID_EMPIRE;

		$HQ_POSITION = $BLD_DEFAULT_HQ_POSITION;
		$position = $HQ_POSITION;
		if ( $country == 1 )
			$building_id = $BLD_DEFAULT_HQ_ID_ALLIES;
		else if ( $country == 2 )
			$building_id = $BLD_DEFAULT_HQ_ID_EMPIRE;

		if ( $count_before ) {
			$query = "SELECT COUNT(*) FROM construction WHERE general_id = $general_id AND position = $HQ_POSITION";
			assert_render($rs = $tb->query($query));
			$count = ms_fetch_single_cell($rs);
			if ( $count && $count >= 1 ) {
				// 				elog("HQ already exists: $count");
				return;
			}
		}

		$BUILDINGS = construction::get_buildings();
		$BUILDING = $BUILDINGS[$building_id];

		assert_render($BUILDING, "invalid:building_id:$building_id");

		// check general's FORCES against building_id
		if ( !($BUILDING['force'] == 'Common'
				|| ($BUILDING['force'] == 'Allies' && $country == 1)
				|| ($BUILDING['force'] == 'Empire' && $country == 2)) ) {
			render_error("invalid:force: " . $BUILDING['force']);
		}

		elog("putting default_buildings: HQ");

		$query = "INSERT INTO construction "
				."(general_id, building_id, position, created_at, status) "
						."VALUES ($general_id, $building_id, $position, NOW(), $BLD_COMPLETED)";
		assert_render($rs = $tb->query_with_affected($query, 1), "invalid:building:build");

		$construction_id = $tb->mc()->insert_id;

		$building_list['hq_cid'] = $construction_id;
		$building_list['hq_bid'] = $building_id;
		$building_list['hq_level'] = 1;
		$building_list['shq_bid'] = null;
		$building_list['non_hq'] = null;

		$js = pretty_json($building_list);
		$ejs = $tb->escape($js);

		$query = "UPDATE general SET building_list = '$ejs' WHERE general_id = $general_id";
		assert_render($tb->query_with_affected($query, 1));

		general::calculate_and_update_static_effects($tb);
	}

	public static function select_all($tb, $select_expr = null, $where_condition = null) {
		global $user_id, $general_id;

		if ( $select_expr == null )
			$select_expr = "*";
		if ( $where_condition == null )
			$where_condition = "WHERE general_id = $general_id";
		else
			$where_condition = "WHERE general_id = $general_id AND ($where_condition)";

		$query = "SELECT $select_expr FROM construction $where_condition /*BY_HELPER*/";
		assert_render($rows = ms_fetch_all($tb->query($query)));

		$json_keys = array('extra');

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
		$rows = construction::select_all($tb, $select_expr, $where_condition);
		if ( $rows && count($rows) == 1 )
			return $rows[0];
		return null;
	}

	public static function clear($tb) {
		global $TAG, $ALLIES, $EMPIRE, $user_id, $general_id, $dev_type, $dev_uuid, $ops, $status, $country;

		if ( dev ) {
			$query = "UPDATE general SET building_list = NULL, bld_cool_end_at = NULL WHERE general_id = $general_id;";
			assert_render($rs = $tb->query($query));

			$query = "DELETE FROM construction WHERE general_id = $general_id;";
			assert_render($rs = $tb->query($query));

			$country = session_GET('country');
			construction::default_buildings($tb, $general_id, $country, true);

			general::calculate_and_update_static_effects($tb);
		}
	}

	public static function get($tb) {
		global $TAG, $ALLIES, $EMPIRE, $user_id, $general_id, $dev_type, $dev_uuid, $ops, $status, $country;

		$where = null;
		if ( is_numeric($status) )
			$where = "status = $status";

		$map['constructions'] = construction::select_all($tb, null, $where);
		assert_render($tb->end_txn());

		render_ok('success', $map);
	}

	public static function build($tb) {
		global $TAG, $ALLIES, $EMPIRE, $user_id, $general_id, $dev_type, $dev_uuid, $ops, $status, $country;
		global $BLD_POS_MIN, $BLD_POS_MAX, $BLD_COOLTIME_LIMIT;
		global $BLD_EMPTY, $BLD_BUILDING, $BLD_UPGRADING, $BLD_COMPLETED, $BLD_COUNT_MAX;

		$building_id = queryparam_fetch_int('building_id');
		$position = queryparam_fetch_int('position');

		$BUILDINGS = construction::get_buildings();

		if ( !array_key_exists($building_id, $BUILDINGS) )
			render_error("invalid:building_id: $building_id", FCODE(50101));
		if ( !($BLD_POS_MIN <= $position && $position <= $BLD_POS_MAX) )
			render_error("invalid:position: $position", FCODE(50102));

		$BUILDING = $BUILDINGS[$building_id];

		$general = general::select($tb, "*, TIMESTAMPDIFF(SECOND, NOW(), bld_cool_end_at) AS diff_bld_cool_end_at");

		// check general's FORCES against building_id
		if ( !($BUILDING['force'] == 'Common'
				|| ($BUILDING['force'] == 'Allies' && $general['country'] == ALLIES)
				|| ($BUILDING['force'] == 'Empire' && $general['country'] == EMPIRE)) ) {
			render_error("invalid:force: " . $BUILDING['force'] . "," . $general['country'], FCODE(50103));
		}

		// cannot build default hq
		if ( in_array($building_id, $BUILDINGS['buildings_hq']) )
			render_error("cannot build hq with this api", FCODE(50111, array('building_id'=>$building_id)));

		// check building count limit by general level
		$bld_count = 1; // HQ
		if ( isset($general['building_list']['non_hq']) ) {
			foreach ($general['building_list']['non_hq'] as $bid => $cid_level)
				$bld_count += count($cid_level);
		}
		$GLEVELS = general::get_levels();
		$bld_count_limit = MIN($BLD_COUNT_MAX, $GLEVELS[$general['level']]['building_count_max']);
		if ( $bld_count >= $bld_count_limit ) {
			if ( !(dev && queryparam_fetch_int('ignore') > 0) )
				render_error("building count limit at max: bld_count >= bld_count_limit, $bld_count >= $bld_count_limit", FCODE(50112));
		}

		// check HQ level
		$cur_level = 0;
		$next_level = $cur_level + 1; //starting from level 1, always

		$req_hq_level = 10*(($next_level-1)/10);
		$cur_hq_level = $general['building_list']['hq_level'];
		if ( !$cur_hq_level )
			render_error("no hq found", FCODE(50109));
		if ( $cur_hq_level < $req_hq_level )
			render_error("not enough hq level: $cur_hq_level < $req_hq_level", FCODE(50108));

		// check position duplication and limit
		$query = "SELECT position, building_id FROM construction WHERE (general_id = $general_id AND position = $position)";
		$query .= " OR (general_id = $general_id AND building_id = $building_id)";

		assert_render($rows = ms_fetch_all($tb->query($query)));
		if ( $rows && sizeof($rows) > 0 ) {
			foreach( $rows as $b ) {
				if ( $b['position'] == $position )
					render_error("duplicated:position: $position", FCODE(50104));
				if ( $b['building_id'] == $building_id ) {
					// check building limit
					$cur_building_qty = sizeof($rows);
					$max_building_qty = $BUILDING['buildLimit'];
					$map['cur_building_qty'] = $cur_building_qty;
					$map['max_building_qty'] = $max_building_qty;
					if ( $cur_building_qty + 1 > $max_building_qty )
						render_error("duplicated:limit: $building_id, cur_building_qty+1 SHOULD <= max_building_qty", FCODE(50105, $map));
				}
			}
		}

		$query = "INSERT INTO construction "
				."(general_id, building_id, position, created_at) "
						."VALUES ($general_id, $building_id, $position, NOW())";
		assert_render($rs = $tb->query_with_affected($query, 1), "invalid:building:build");

		$construction_id = $tb->mc()->insert_id;

		$bld_cooltime = 1;
		$bld_cost_gold = 0;
		$bld_cost_honor = 0; // always 0 at this time(0924)

		if ( array_key_exists('levels', $BUILDING) ) {
			if ( array_key_exists($next_level, $BUILDING['levels']) ) {
				$bld_cost_gold = $BUILDING['levels'][$next_level]['cost'];
				$bld_cooltime = $BUILDING['levels'][$next_level]['coolTime'];

				$bld_cost_gold = general::apply_effects($tb, 100, $bld_cost_gold);
				$bld_cooltime = general::apply_effects($tb, 101, $bld_cooltime);

				elog("bld cooltime/cost_gold at level[$next_level]: $bld_cooltime, $bld_cost_gold");
			} else
				elog('items at key [levels] were not found, free building');
		} else
			elog('no key [levels] found, free building');

		// check bld_cool
		if ( $general['diff_bld_cool_end_at'] && $general['diff_bld_cool_end_at'] >= $BLD_COOLTIME_LIMIT ) {
			$map['general'] = $general;
			$map['fcode'] = 50106;

			render_error("build_cooltime: $BLD_COOLTIME_LIMIT <= " . $general['diff_bld_cool_end_at'], $map);
		}

		// check gold, honor
		if ( !($bld_cost_gold <= $general['gold'] && $bld_cost_honor <= $general['honor']) )
			render_error("not enough gold or honor", FCODE(50110));

		$bld_cool_end_base = 'NOW()';
		if ( $general['bld_cool_end_at'] && $general['diff_bld_cool_end_at'] > 0 )
			$bld_cool_end_base = 'bld_cool_end_at';

		$terms = [];
		$terms['bld_cool_end_at'] = "TIMESTAMPADD(SECOND, $bld_cooltime, $bld_cool_end_base)";
		$terms['gold'] = "gold - $bld_cost_gold";
		$terms['honor'] = "honor - $bld_cost_honor";

		// UPDATE building_list
		if ( !isset($general['building_list']['non_hq']) )
			$general['building_list']['non_hq'] = array();

		if ( !isset($general['building_list']['non_hq'][$building_id]) )
			$general['building_list']['non_hq'][$building_id] = array();

		if ( !isset($general['building_list']['non_hq'][$building_id][$construction_id]) )
			$general['building_list']['non_hq'][$building_id][$construction_id] = $next_level;

		$js = pretty_json($general['building_list']);
		$ejs = $tb->escape($js);
		$terms['building_list'] = ms_quote($ejs);

		// calculate officer hire relateds for Pub(주점)
		global $BLD_PUB_ID;
		if ( $building_id == $BLD_PUB_ID ) {
			global $OFFICER_UNHIRED_MIN, $OFFICER_HIRED_MIN;

			$officer_unhired_max = $OFFICER_UNHIRED_MIN + $BUILDING['levels'][$next_level]['value'];
			$terms['officer_unhired_max'] = $officer_unhired_max;
		}

		$pairs = join_terms($terms);

		$query = "UPDATE general SET $pairs WHERE general_id = $general_id";
		assert_render($rs = $tb->query_with_affected($query, 1));

		general::calculate_and_update_static_effects($tb);

		quest::resolve_quests($tb, ['Building_HQ', 'Building_Training', 'Building_MachineFactory']);

		$constructions = construction::select_all($tb, null, "construction_id = $construction_id");

		gamelog(__METHOD__, ['constructions'=>$constructions]);

		assert_render($tb->end_txn());

		$map['constructions'] = $constructions;

		render_ok('built', $map);
	}

	public static function build_shq($tb) {
		global $TAG, $ALLIES, $EMPIRE, $user_id, $general_id, $dev_type, $dev_uuid, $ops, $status, $country;
		global $BLD_POS_MIN, $BLD_POS_MAX, $BLD_COOLTIME_LIMIT;
		global $BLD_EMPTY, $BLD_BUILDING, $BLD_UPGRADING, $BLD_COMPLETED;
		global $BLD_DEFAULT_HQ_ID_ALLIES, $BLD_DEFAULT_HQ_ID_EMPIRE;

		$building_id = queryparam_fetch_int('building_id');

		$BUILDINGS = construction::get_buildings();

		if ( !array_key_exists($building_id, $BUILDINGS) )
			render_error("invalid:building_id: $building_id", FCODE(50101));

		if ( !in_array($building_id, $BUILDINGS['buildings_hq']) )
			render_error("invalid:building_id: not a hq building_id: $building_id", FCODE(50101));

		if ( $building_id == $BLD_DEFAULT_HQ_ID_ALLIES || $building_id == $BLD_DEFAULT_HQ_ID_EMPIRE )
			render_error("invalid:building_id: not a hq special building_id: $building_id", FCODE(50403));

		$BUILDING = $BUILDINGS[$building_id];

		$general = general::select($tb, "country, star, level, building_list");

		// check general's FORCES against building_id
		if ( !($BUILDING['force'] == 'Common'
				|| ($BUILDING['force'] == 'Allies' && $general['country'] == 1)
				|| ($BUILDING['force'] == 'Empire' && $general['country'] == 2)) ) {
			render_error("invalid:force: " . $BUILDING['force'] . "," . $general['country'], FCODE(50103));
		}

		// check shq_bid
		$shq_bid = $general['building_list']['shq_bid'];
		if ( $shq_bid && $shq_bid == $building_id )
			render_error("same shq bid was requested: $building_id", FCODE(50401));

		// check general level
		if ( !isset($BUILDING['req_level']) )
			elog(pretty_json($BUILDING));
		$req_general_level = $BUILDING['req_level'];
		$cur_general_level = $general['level'];
		if ( $cur_general_level < $req_general_level ) {
			if ( !(dev && queryparam_fetch_int('ignore')) )
				render_error("not enough general level: $cur_general_level < $req_general_level", FCODE(50107));
		}

		// check star
		$bld_cost_star = $BUILDING['cost_star'];
		if ( !($bld_cost_star <= $general['star']) ) {
			$map['cost_star'] = $bld_cost_star;
			$map['cur_star'] = $general['star'];

			render_error("not enough star, should be $bld_cost_star <= " . $general['star'], FCODE(50402, $map));
		}

		// UPDATE building_list
		$general['building_list']['shq_bid'] = $building_id;
		$js = pretty_json($general['building_list']);
		$ejs = $tb->escape($js);

		$terms = [];
		$terms['star'] = "star - $bld_cost_star";
		$terms['building_list'] = ms_quote($ejs);

		$pairs = join_terms($terms);

		$query = "UPDATE general SET $pairs WHERE general_id = $general_id";
		assert_render($rs = $tb->query_with_affected($query, 1));

		quest::resolve_quests($tb, ['Building_HQ', 'Building_Training', 'Building_MachineFactory']);

		general::calculate_and_update_static_effects($tb);

		gamelog(__METHOD__, ['building_id'=>$building_id]);

		assert_render($tb->end_txn());

		render_ok('success');
	}

	public static function remove($tb) {
		global $TAG, $ALLIES, $EMPIRE, $user_id, $general_id, $dev_type, $dev_uuid, $ops, $status, $country;
		global $BLD_POS_MIN, $BLD_POS_MAX, $BLD_COOLTIME_LIMIT;
		global $BLD_EMPTY, $BLD_BUILDING, $BLD_UPGRADING, $BLD_COMPLETED;

		$construction_id = queryparam_fetch_int('construction_id');

		assert_render($construction_id, "invalid:construction_id:$construction_id");

		$construction = construction::select($tb, 'building_id', "construction_id = $construction_id");
		assert_render($construction, "invalid:construction_id");

		$BUILDINGS = construction::get_buildings();
		$BUILDING = $BUILDINGS[$construction['building_id']];
		$bld_name = $BUILDING['id'];
		$building_id = $construction['building_id'];

		$terms = array();

		// 훈련소/기계공장/장군부/연구소/생산공장: 사용 중이어도 철거 가능, 병원: 장교 치료중이면 불가능
		if ( stristr($BUILDING['id'], 'HQ') ) // HQ is not removable
			render_error("invalid:cannot remove hq: $construction_id ($bld_name)", FCODE(50301));

		global $BLD_HOSPITAL_ID;
		if ( $building_id == $BLD_HOSPITAL_ID ) {
			global $OFFICER_HEALING;
			$query = "SELECT COUNT(*) FROM officer WHERE general_id = $general_id AND status = $OFFICER_HEALING";
			assert_render($count = ms_fetch_single_cell($tb->query($query)));

			if ( $count > 0 )
				render_error("cannot remove hospital when healing officer(s)", FCODE(50302));
		}

		// UPDATE building_list
		$general = general::select($tb, 'building_list');
		if ( isset($general['building_list']['non_hq'][$building_id][$construction_id]) )
			unset($general['building_list']['non_hq'][$building_id][$construction_id]);

		$js = pretty_json($general['building_list']);
		$ejs = $tb->escape($js);

		$terms = [];
		$terms['building_list'] = ms_quote($ejs);

		// RE-calculate officer hire relateds for Pub(주점)
		global $BLD_PUB_ID;
		if ( $building_id == $BLD_PUB_ID ) {
			global $OFFICER_UNHIRED_MIN, $OFFICER_HIRED_MIN;
			// no pub, so reset to minimum
			$officer_unhired_max = $OFFICER_UNHIRED_MIN;
			$terms['officer_unhired_max'] = $officer_unhired_max;
		}

		$pairs = join_terms($terms);
		$query = "UPDATE general SET $pairs WHERE general_id = $general_id";
		assert_render($rs = $tb->query_with_affected($query, 1));

		$query = "DELETE FROM construction WHERE construction_id = $construction_id;";
		assert_render($rs = $tb->query_with_affected($query, 1), "invalid:construction_id: $construction_id not affected");

		general::calculate_and_update_static_effects($tb);

		gamelog(__METHOD__, ['construction'=>$construction]);

		assert_render($tb->end_txn());

		render_ok("removed: $construction_id");
	}

	public static function upgrade($tb) {
		global $TAG, $ALLIES, $EMPIRE, $user_id, $general_id, $dev_type, $dev_uuid, $ops, $status, $country;
		global $BLD_POS_MIN, $BLD_POS_MAX, $BLD_COOLTIME_LIMIT;
		global $BLD_EMPTY, $BLD_BUILDING, $BLD_UPGRADING, $BLD_COMPLETED;

		$construction_id = queryparam_fetch_int('construction_id');

		assert_render($construction_id, "invalid:construction_id:$construction_id");

		$general = general::select($tb, "*, TIMESTAMPDIFF(SECOND, NOW(), bld_cool_end_at) AS diff_bld_cool_end_at");

		$construction = construction::select($tb, null, "construction_id = $construction_id");
		assert_render($construction, "invalid:construction_id");

		$building_id = $construction['building_id'];
		$cur_level = $construction['cur_level'];

		$BUILDINGS = construction::get_buildings();

		if ( !array_key_exists($building_id, $BUILDINGS) )
			render_error("invalid:building_id: $building_id");

		$BUILDING = $BUILDINGS[$building_id];

		// check table for this is upgradable by level
		if ( !array_key_exists($cur_level + 1, $BUILDING['levels']) )
			render_error("invalid:max_level: $cur_level", FCODE(50201));

		$query = "UPDATE construction SET cur_level = cur_level + 1 WHERE construction_id = $construction_id";
		assert_render($tb->query_with_affected($query, 1));

		$next_level = $cur_level + 1;

		// check general level
		if ( stristr($BUILDING['id'], 'HQ') ) {
			$req_general_level = 10*(($next_level-1)/10);
			$cur_general_level = $general['level'];
			$map['req_general_level'] = $req_general_level;
			$map['cur_general_level'] = $cur_general_level;
			if ( $cur_general_level < $req_general_level ) {
				if ( !(dev && queryparam_fetch_int('ignore')) )
					render_error("not enough general level: $cur_general_level < $req_general_level", FCODE(50107, $map));
			}
		} else {
			$cur_hq_level = $general['building_list']['hq_level'];
			if ( !$cur_hq_level )
				render_error("no hq found", FCODE(50109));

			$req_general_level = $next_level;
			$cur_general_level = $general['level'];
			$map['req_general_level'] = $req_general_level;
			$map['cur_general_level'] = $cur_general_level;
			if ( $cur_general_level < $req_general_level ) {
				if ( !(dev && queryparam_fetch_int('ignore')) )
					render_error("not enough general level: $cur_general_level < $req_general_level", FCODE(50107, $map));
			}
		}

		$bld_cooltime = 1;
		$bld_cost_gold = 0;
		$bld_cost_honor = 0;

		if ( array_key_exists('levels', $BUILDING) ) {
			if ( array_key_exists($next_level, $BUILDING['levels']) ) {
				$bld_cooltime = $BUILDING['levels'][$next_level]['coolTime'];
				$bld_cost_gold = $BUILDING['levels'][$next_level]['cost'];

				$bld_cost_gold = general::apply_effects($tb, 100, $bld_cost_gold);
				$bld_cooltime = general::apply_effects($tb, 101, $bld_cooltime);

				elog("bld cooltime/cost_gold at level[$next_level]: $bld_cooltime, $bld_cost_gold");
			} else
				elog('items at key [levels] were not found, free building');
		} else
			elog('no key [levels] found, free building');

		// check bld_cool
		if ( $general['diff_bld_cool_end_at'] && $general['diff_bld_cool_end_at'] >= $BLD_COOLTIME_LIMIT ) {
			$map['general'] = $general;
			$map['fcode'] = 50106;

			if ( !(dev && queryparam_fetch_int('ignore') > 0) )
				render_error("build_cooltime: $BLD_COOLTIME_LIMIT <= " . $general['diff_bld_cool_end_at'], $map);
		}

		// check gold, honor
		if ( !($bld_cost_gold <= $general['gold'] && $bld_cost_honor <= $general['honor']) )
			render_error("not enough gold or honor", FCODE(50110));

		$bld_cool_end_base = 'NOW()';
		if ( $general['bld_cool_end_at'] && $general['diff_bld_cool_end_at'] > 0 )
			$bld_cool_end_base = 'bld_cool_end_at';
			
		$terms = array();

		// UPDATE building_list
		if ( stristr($BUILDING['id'], 'HQ') ) {
			$general['building_list']['hq_level'] = $next_level;
		} else {
			if ( !isset($general['building_list']['non_hq']) )
				$general['building_list']['non_hq'] = array();

			if ( !isset($general['building_list']['non_hq'][$building_id]) )
				$general['building_list']['non_hq'][$building_id] = array();

			$general['building_list']['non_hq'][$building_id][$construction_id] = $next_level;
		}
		$js = pretty_json($general['building_list']);
		$ejs = $tb->escape($js);

		$terms = [];
		$terms['building_list'] = ms_quote($ejs);
		$terms['bld_cool_end_at'] = "TIMESTAMPADD(SECOND, $bld_cooltime, $bld_cool_end_base)";
		$terms['gold'] = "gold - $bld_cost_gold";
		$terms['honor'] = "honor - $bld_cost_honor";

		// RE-calculate officer hire relateds for Pub(주점)
		global $BLD_PUB_ID;
		if ( $building_id == $BLD_PUB_ID ) {
			global $OFFICER_UNHIRED_MIN, $OFFICER_HIRED_MIN;

			$officer_unhired_max = $OFFICER_UNHIRED_MIN + $BUILDING['levels'][$next_level]['value'];
			$terms['officer_unhired_max'] = $officer_unhired_max;
		}

		$pairs = join_terms($terms);
		$query = "UPDATE general SET $pairs WHERE general_id = $general_id";
		assert_render($rs = $tb->query_with_affected($query, 1));

		general::calculate_and_update_static_effects($tb);

		quest::resolve_quests($tb, ['Building_HQ', 'Building_Training', 'Building_MachineFactory']);

		$constructions = construction::select_all($tb, null, "construction_id = $construction_id");

		gamelog(__METHOD__, ['construction'=>$constructions[0]]);

		assert_render($tb->end_txn());

		$map['constructions'] = $constructions;

		render_ok('upgraded', $map);
	}

	public static function find_building($building_list, $building_id) {
		$max_level = 0;

		if ( isset($building_list['hq_bid']) && $building_list['hq_bid'] == $building_id )
			$max_level = max($max_level, $building_list['hq_level'] ?: 0);
		else if ( isset($building_list['shq_bid']) && $building_list['shq_bid'] == $building_id )
			$max_level = max($max_level, $building_list['hq_level'] ?: 0);
		else if ( !empty($building_list['non_hq']) ) {
			foreach ( $building_list['non_hq'] as $bid => $cid_level ) {
				if ( $bid == $building_id ) {
					foreach ( $cid_level as $cid => $level )
						$max_level = max($max_level, $level);
				}
			}
		}
		return $max_level;
	}
}

if ( strstr($_SERVER["REQUEST_URI"], basename(__FILE__)) ) {

	$ops = explode(',', $op = queryparam_fetch('op', 'get'));

	$user_id = login_check();
	$general_id = session_GET('general_id');

	$status = queryparam_fetch_int('status');

	if ( sizeof(array_intersect_key(['get', 'clear', 'build', 'build_shq', 'remove', 'upgrade'], $ops)) ) {
		for ( $retried = 1 ; $retried <= $TXN_RETRY_MAX ; $retried++ ) {
			try {
				$tb = new TxnBlock();

				if ( in_array("clear", $ops) ) construction::clear($tb);

				// 				$country = session_GET('country');
				// 				construction::default_buildings($tb, $general_id, $country, true);

				if ( in_array("build", $ops) ) construction::build($tb);
				else if ( in_array("build_shq", $ops) ) construction::build_shq($tb);
				else if ( in_array("remove", $ops) ) construction::remove($tb);
				else if ( in_array("upgrade", $ops) ) construction::upgrade($tb);
				else if ( in_array("collect_tax", $ops) ) construction::collect_tax($tb);
				else if ( in_array("extra_collect_tax", $ops) ) construction::extra_collect_tax($tb);

				construction::get($tb); // embedes end_txn()
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
