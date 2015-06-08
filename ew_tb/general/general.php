<?php
require_once '../connect.php';
require_once '../build/construction.php';
require_once '../auth/event.php';
require_once '../general/quest.php';
require_once '../battlefield/tile.php';
require_once '../army/item.php';

class general {

	public static function get_skills() {
		if ( $val = fetch_from_cache('constants:skills') )
			return $val;

		$timer_bgn = microtime(true);

		$skillinfo = loadxml_as_dom('xml/skillinfo.xml');
		if ( !$skillinfo ) {
			elog("failed to loadxml: " . 'xml/skillinfo.xml');
			return null;
		}

		$skills = array();
		$skills_ids_basic = array(); // basic has no dependency
		$skills_ids_offense = array();
		$skills_ids_defense = array();
		$skills_ids_support = array();

		foreach ($skillinfo->xpath("/skillinfo/skill") as $node ) {
			$attrs = (array)$node->attributes();
			$pattrs = $attrs['@attributes'];

			$ukey = $pattrs["id"];

			$skills[$ukey] = $pattrs;

			if ( $pattrs['require_point'] == 0 && $pattrs['require_skill_id'] == 0 && $pattrs['require_skill_level'] == 0 )
				$skills_ids_basic[] = $ukey;
			if ( $pattrs['type'] == 1 )
				$skills_ids_offense[] = $ukey;
			if ( $pattrs['type'] == 2 )
				$skills_ids_defense[] = $ukey;
			if ( $pattrs['type'] == 3 )
				$skills_ids_support[] = $ukey;

			$levels = array();
			$max_level = 0;
			foreach($skillinfo->xpath("//skillinfo/skill[@id=$ukey]/level") as $level ) {
				$level_attrs = (array)$level->attributes();
				$level_pattrs = $level_attrs['@attributes'];
					
				$levels[$level_pattrs['level']] = $level_pattrs;

				$max_level = max($max_level, $level_pattrs['level']);
			}

			elog(sprintf("setting [%2d] levels (max: %2d) for skill:id [%s]", count($levels), $max_level, $ukey));

			$skills[$ukey]['max_level'] = $max_level;
			$skills[$ukey]['levels'] = $levels;
		}

		$bnis = array_keys($skills);
		$skills['skills_ids_basic'] = $skills_ids_basic;
		$skills['skills_ids_offense'] = $skills_ids_offense;
		$skills['skills_ids_defense'] = $skills_ids_defense;
		$skills['skills_ids_support'] = $skills_ids_support;

		$timer_end = microtime(true);
		elog("time took for general::get_skills(): " . ($timer_end - $timer_bgn));

		store_into_cache('constants:skills', $skills);

		elog("general skills keys: " . json_encode($bnis));
		elog("general skills_ids_basic keys: " . json_encode($skills_ids_basic));
		elog("general skills_ids_offense keys: " . json_encode($skills_ids_offense));
		elog("general skills_ids_defense keys: " . json_encode($skills_ids_defense));
		elog("general skills_ids_support keys: " . json_encode($skills_ids_support));

		return $skills;
	}

	public static function get_badges() {
		if ( $val = fetch_from_cache('constants:badges') )
			return $val;

		$timer_bgn = microtime(true);

		$skillinfo = loadxml_as_dom('xml/badgeinfo.xml');
		if ( !$skillinfo ) {
			elog("failed to loadxml: " . 'xml/badgeinfo.xml');
			return null;
		}

		$badges = ['_'=>'_'];

		foreach ($skillinfo->xpath("/skillinfo/skill") as $node ) {
			$attrs = (array)$node->attributes();
			$pattrs = $attrs['@attributes'];

			$ukey = $pattrs["id"];

			$newattrs = array();
			foreach ( $pattrs as $key => $val ) {
				if ( strpos($key, "cl_") === 0 )
					continue; // skip client-side effective related keys
					
				$newattrs[$key] = $val;
			}
			$badges[$ukey] = $newattrs;

			$levels = array();
			$max_level = 0;
			foreach($node->xpath("level") as $level ) {
				$level_attrs = (array)$level->attributes();
				$level_pattrs = $level_attrs['@attributes'];

				$effects = array();
				foreach($level->xpath("effect") as $effect) {
					$effect_attrs = (array)$effect->attributes();
					$effect_pattrs = $effect_attrs['@attributes'];

					$effects[$effect_pattrs['id']] = $effect_pattrs;
				}

				$levels[$level_pattrs['level']] = $level_pattrs;
				$levels[$level_pattrs['level']]['effects'] = $effects;

				$max_level = max($max_level, $level_pattrs['level']);
			}

			elog(sprintf("setting [%2d] levels (max: %2d) for skill:id [%s]", count($levels), $max_level, $ukey));

			$badges[$ukey]['max_level'] = $max_level;
			$badges[$ukey]['levels'] = $levels;
		}

		$bnis = array_keys($badges);

		$timer_end = microtime(true);
		elog("time took for general::get_badges(): " . ($timer_end - $timer_bgn));

		store_into_cache('constants:badges', $badges);

		elog("general badges keys: " . json_encode($bnis));

		return $badges;
	}

	public static function get_levels() {
		if ( $val = fetch_from_cache('constants:general_levels') )
			return $val;

		$timer_bgn = microtime(true);

		$levelinfo = loadxml_as_dom('xml/general_info.xml');
		if ( !$levelinfo ) {
			elog("failed to loadxml: " . 'xml/general_info.xml');
			return null;
		}

		$levels = array();
		$max_level = 0;

		foreach ($levelinfo->xpath("//level") as $node ) {
			$attrs = (array)$node->attributes();
			$pattrs = $attrs['@attributes'];

			$ukey = $pattrs["level"];

			$newattrs = array();
			foreach ( $pattrs as $key => $val ) {
				// skip client-side effective related keys
				if ( strpos($key, "cl_") === 0 || strpos($key, "ef") === 0 || strpos($key, "spr") === 0 || strpos($key, "sound") === 0)
					continue;

				$newattrs[$key] = $val;
			}
			$max_level = max($max_level, intval($newattrs['level']));

			$levels[$ukey] = $newattrs;
		}

		$levels['max_level'] = $max_level;

		$timer_end = microtime(true);
		elog("time took general for general::get_levels(): " . ($timer_end - $timer_bgn));

		store_into_cache('constants:general_levels', $levels);

		$bnis = array_keys($levels);
		elog("general levelkeys: " . json_encode($bnis));

		return $levels;
	}

	public static function get_effect_info() {
		if ( $val = fetch_from_cache('constants:effects_info') )
			return $val;

		$timer_bgn = microtime(true);

		$effect_info = loadxml_as_dom('xml/effect_info.xml');
		if ( !$effect_info ) {
			elog("failed to loadxml: " . 'xml/effect_info.xml');
			return null;
		}

		$effects = array();

		foreach ($effect_info->xpath("//effect") as $node ) {
			$attrs = (array)$node->attributes();
			$pattrs = $attrs['@attributes'];

			$ukey = $pattrs["effect_id"];

			$newattrs = array();
			foreach ( $pattrs as $key => $val ) {
				// skip client-side effective related keys
				if ( strpos($key, "cl_") === 0 || strpos($key, "spr") === 0 || strpos($key, "sound") === 0)
					continue;

				$newattrs[$key] = $val;
			}
			$effects[$ukey] = $newattrs;
		}

		$timer_end = microtime(true);
		elog("time took general for general::get_effect_info(): " . ($timer_end - $timer_bgn));

		store_into_cache('constants:effects_info', $effects);

		$bnis = array_keys($effects);
		elog("effectkeys: " . json_encode($bnis));

		return $effects;
	}

	public static function get_tutorials() {
		if ( $val = fetch_from_cache('constants:tutorials') )
			return $val;

		$timer_bgn = microtime(true);

		$tutorial_info = loadxml_as_dom('xml/tutorialinfo.xml');
		if ( !$tutorial_info )
			return null;

		$tutorials = [];
		$tutorial_ids = [];

		foreach ($tutorial_info->xpath("//tutorialinfo/tutorial") as $node ) {
			$attrs = (array)$node->attributes();
			$pattrs = $attrs['@attributes'];

			$tutorials[$pattrs["id"]] = $pattrs;

			$tutorial_ids[] = $pattrs["id"];
		}

		$tutorials['tutorial_ids'] = $tutorial_ids;

		$timer_end = microtime(true);
		elog("time took tutorial for tutorial::get_tutorials(): " . ($timer_end - $timer_bgn));

		store_into_cache('constants:tutorials', $tutorials);

		$bnis = array_keys($tutorials);
		elog("tutorial_ids: " . json_encode($bnis));

		return $tutorials;
	}


	public static function select($tb, $select_expr = null, $where_condition = null, $assert_on_empty_row = true) {
		global $user_id, $general_id;

		if ( $select_expr == null )
			$select_expr = "*";
		if ( $where_condition == null )
			$where_condition = "WHERE general_id = $general_id";
		else
			$where_condition = "WHERE $where_condition";

		$query = "SELECT $select_expr, NOW() AS now FROM general $where_condition /*BY_HELPER*/";
		$cols = ms_fetch_one($tb->query($query));
		if ( !$assert_on_empty_row && is_null($cols) )
			return null;

		assert_render($cols);

		$json_keys = array('extra', 'skills', 'badge_list', 'building_list', 'effects', 'quest_list', 'chat_ban_ids', 'tutorial_list');

		foreach ( $json_keys as $json_key ) {
			if ( array_key_exists($json_key, $cols) && $cols[$json_key] ) {
				$js = @json_decode($cols[$json_key], true);
				if ( $js )
					$cols[$json_key] = $js;
				else
					$cols[$json_key] = null;
			}
		}

		if ( !$general_id && isset($cols['general_id']) )
			$general_id = $cols['general_id'];

		$eff_keys = ['gold_max'=>116, 'honor_max'=>116, 'activity_max'=>118]; // dropped 'pop_max'=>115 as of 11.21
		foreach ($eff_keys as $eff_key => $effect_id) {
			if ( !empty($cols[$eff_key]) )
				$cols[$eff_key . "_eff"] = general::apply_effects($tb, $effect_id, $cols[$eff_key]);
		}

		return $cols;
	}

	public static function recover($tb, $redis) {
		global $TAG, $ALLIES, $EMPIRE, $user_id, $general_id, $dev_type, $dev_uuid, $ops, $status, $country;

		elog("RECOVER starts: " . __METHOD__);
			


		elog("RECOVER finished: " . __METHOD__);
	}

	public static function clear($tb) {
		global $TAG, $ALLIES, $EMPIRE, $user_id, $general_id, $dev_type, $dev_uuid, $ops, $status, $country;

		if ( dev ) {
			global $OFFICER_UNHIRED_MIN, $OFFICER_HIRED_MIN;
			global $GENERAL_INITIAL_GOLD, $GENERAL_INITIAL_HONOR, $GENERAL_INITIAL_STAR, $GENERAL_INITIAL_ACTIVITY;
			global $GENERAL_INITIAL_TAX_COLLECTABLE_COUNT;
			global $ITEM_STORAGE_SLOT_MIN;
			global $TROOP_POPULATION_LIMIT;

			$GENERAL_INITIAL_LEVEL = 1;

			$LEVELS = general::get_levels();
			$TUTORIALS = general::get_tutorials();

			$terms = [];
			$terms['tax_collectable_count'] = $GENERAL_INITIAL_TAX_COLLECTABLE_COUNT;
			$terms['tax_timer_willbe_refreshed_at'] = "NULL";
			$terms['effects'] = "NULL";
			$terms['skills'] = "NULL";
			$terms['building_list'] = "NULL";
			$terms['badge_list'] = "NULL";
			$terms['badge_equipped_id'] = "NULL";
			$terms['badge_willbe_refreshed_at'] = "NULL";
			$terms['officer_unhired_max'] = $OFFICER_UNHIRED_MIN;
			$terms['officer_hired_max'] = $OFFICER_HIRED_MIN;
			$terms['officer_hired_level_max'] = 0;
			$terms['officer_list_willbe_reset_at'] = "NULL";
			$terms['item_storage_slot_cur'] = 0;
			$terms['item_storage_slot_cap'] = $ITEM_STORAGE_SLOT_MIN;
			$terms['level'] = $GENERAL_INITIAL_LEVEL;
			$terms['gold'] = $GENERAL_INITIAL_GOLD;
			$terms['gold_max'] = $LEVELS[$GENERAL_INITIAL_LEVEL]['gold_capacity'];
			$terms['honor'] = $GENERAL_INITIAL_HONOR;
			$terms['honor_max'] = $LEVELS[$GENERAL_INITIAL_LEVEL]['honor_capacity'];
			$terms['activity_max'] = $LEVELS[$GENERAL_INITIAL_LEVEL]['activity_max'];
			$terms['activity_cur'] = $GENERAL_INITIAL_ACTIVITY;
			$terms['activity_willbe_refreshed_at'] = "NULL";
			$terms['star'] = $GENERAL_INITIAL_STAR;
			$terms['running_combat_id'] = "NULL";
			$terms['bld_cool_end_at'] = "NULL";
			$terms['pop_cur'] = 0;
			$terms['pop_max'] = $TROOP_POPULATION_LIMIT; // $LEVELS[$GENERAL_INITIAL_LEVEL]['troop_capacity']; // deprecatd at 11.05
			$terms['exp_cur'] = 0;
			$terms['exp_max'] = $LEVELS[$GENERAL_INITIAL_LEVEL+1]['req_exp'];
			$terms['quest_list'] = null;
			$terms['chat_ban_ids'] = null;
			$terms['mail_unchecked'] = 0;
			$terms['pvp_combat_count'] = 0;
			$terms['pve_combat_count'] = 0;
			$terms['pvl_combat_count'] = 0;
			$terms['pve_combat_win'] = 0;
			$terms['pvp_combat_win'] = 0;
			$terms['pve_combat_top_rank_count'] = 0;
			$terms['pvp_combat_top_rank_count'] = 0;

			$terms['mail_pulled_public_at'] = 'NOW()';
			$terms['mail_pulled_legion_at'] = 'NOW()';

			$terms['tutorial_list'] = ms_quote($tb->escape(pretty_json(empty($TUTORIALS['tutorial_ids']) ? [] : $TUTORIALS['tutorial_ids'])));

			if ( dev && ($gold = queryparam_fetch_int('gold')) ) $terms['gold'] = $gold;
			if ( dev && ($honor = queryparam_fetch_int('honor')) ) $terms['honor'] = $honor;
			if ( dev && ($star = queryparam_fetch_int('star')) ) $terms['star'] = $star;
			if ( dev && ($activity = queryparam_fetch_int('activity')) ) $terms['activity_cur'] = $activity;
			if ( dev && ($tax_collectable_count = queryparam_fetch_int('tax_collectable_count')) ) $terms['tax_collectable_count'] = $tax_collectable_count;

			$pairs = join_terms($terms);
			$query = "UPDATE general SET $pairs WHERE general_id = $general_id";
			assert_render($tb->query_with_affected($query, 1));

			$querys = [];
			$querys[] = "DELETE FROM item WHERE general_id = $general_id;";
			$querys[] = "DELETE FROM officer WHERE general_id = $general_id;";
			$querys[] = "DELETE FROM troop WHERE general_id = $general_id;";
			$querys[] = "DELETE FROM combat WHERE general_id = $general_id;";
			$querys[] = "DELETE FROM construction WHERE general_id = $general_id;";
			$querys[] = "DELETE FROM pushes WHERE user_id = $user_id;";
			$querys[] = "DELETE FROM quest WHERE user_id = $user_id;";
			$querys[] = "DELETE FROM chat_force WHERE general_id = $general_id;";
			$querys[] = "DELETE FROM mail WHERE general_id = $general_id;";

			assert_render($tb->multi_query($querys));

			officer::army_dismiss($tb);

			construction::default_buildings($tb, $general_id, session_GET('country'));
			quest::default_quests($tb, $general_id);
			item::default_items($tb, $general_id);
			officer::default_officer_and_troops($tb, $general_id);
		}
	}
	public static function clear_constant_caches($tb) {
		global $TAG, $ALLIES, $EMPIRE, $user_id, $general_id, $dev_type, $dev_uuid, $ops, $status, $country;

		if ( dev ) {
			if ( function_exists('apc_delete') ) {
				elog("clearing apc user cache by tag [$TAG]...");
				$cache_info = apc_cache_info('user');
				foreach ($cache_info['cache_list'] as $cache) {
					$name = $cache['info'];
					if ( strpos($name, "$TAG:constants:") === 0 ) {
						elog(" deleting constant cache: $name");
						apc_delete($name);
					}
				}
			}
			$redis = conn_redis();
			$rkeys = $redis->keys("$TAG:*");
			if ( sizeof($rkeys) > 0 ) {
				elog("clearing redis cache by tag [$TAG]...");
				$redis->multi();
				foreach ($rkeys as $key) {
					if ( strpos($key, "$TAG:constants:") === 0 ) {
						elog(" deleting constant cache: $key");
						$redis->del($key);
					}
				}
				$redis->exec();
			}
		}
		render_ok();
	}
	public static function get($tb) {
		global $TAG, $ALLIES, $EMPIRE, $user_id, $general_id, $dev_type, $dev_uuid, $ops, $status, $country;
		global $COLLECTABLE_TAX_COOLTIME;

		$general = general::select($tb, null, "user_id = $user_id");

		$gskills = $general['skills'];
		if ( $gskills == null || array_key_exists('points_offense', $gskills) == false ) {
			elog("no basic skills were set, triggering general::skills_update_tree");

			$new_gskills = general::skills_update_tree($tb);
			$general['skills'] = $new_gskills;
		}

		$map['general'] = $general;

		// 		gamelog(__METHOD__);

		assert_render($tb->end_txn());

		render_ok('success', $map);
	}

	public static function edit($tb) {
		global $TAG, $ALLIES, $EMPIRE, $user_id, $general_id, $dev_type, $dev_uuid, $ops, $status, $country;

		$candidates = array('name', 'picture');
		if ( dev ) {
			$candidates += array('level', 'gold', 'honor', 'activity_cur', 'activity_max', 'star');
		}

		$parts = array();
		foreach ($candidates as $ck) {
			$val = queryparam_fetch($ck);
			if ( $val )
				$parts[] = sprintf("%s = '%s'", $ck, $tb->escape($val));
		}

		if ( sizeof($parts) > 0 ) {
			$update_clause = implode(",", $parts);

			$query = "UPDATE general SET $update_clause WHERE user_id = $user_id";
			assert_render($tb->query_with_affected($query, 1));
		}

		$general = general::select($tb, null, "user_id = $user_id");

		assert_render($tb->end_txn());

		gamelog(__METHOD__);

		$map['general'] = $general;

		render_ok('success', $map);
	}

	public static function reset_bld_cooltime($tb) {
		global $TAG, $ALLIES, $EMPIRE, $user_id, $general_id, $dev_type, $dev_uuid, $ops, $status, $country;
		global $BLD_POS_MIN, $BLD_POS_MAX, $BLD_COOLTIME_LIMIT;
		global $BLD_EMPTY, $BLD_BUILDING, $BLD_UPGRADING, $BLD_COMPLETED;
		global $BLD_RESET_COOLTIME_COST_STAR_PER_HOUR;

		$ignore_check = queryparam_fetch_int('ignore_check', 1);

		// check bld_cool
		$general = general::select($tb, "*, TIMESTAMPDIFF(SECOND, NOW(), bld_cool_end_at) AS diff_bld_cool_end_at");

		// calculate reset_cost
		if ( !$general['bld_cool_end_at'] )
			render_error('reset_cooltime: no cooltime to reset');

		$cost_star = floor($general['diff_bld_cool_end_at']/3600) + 1;
		$cost_star *= $BLD_RESET_COOLTIME_COST_STAR_PER_HOUR;

		// check cost_star
		if ( $general['star'] < $cost_star && !(dev && $ignore_check > 0))
			render_error("not enough star: " . $general['star'] . " < $cost_star", array('fcode'=>10102, 'cost_star'=>$cost_star));

		$query = "UPDATE general SET bld_cool_end_at = NULL, star = star - $cost_star WHERE general_id = $general_id";
		assert_render($rs = $tb->query_with_affected($query, 1));

		$map['general'] = $general = general::select($tb);
		assert_render($tb->end_txn());

		gamelog(__METHOD__);

		render_ok('reset_bld_cooltime', $map);
	}

	public static function check_general_levelup($tb, $cur_level = null) {
		global $TAG, $ALLIES, $EMPIRE, $user_id, $general_id, $dev_type, $dev_uuid, $ops, $status, $country;

		$LEVELS = general::get_levels();

		if ( $cur_level > 0 && $cur_level >= $LEVELS['max_level'] ) {
			elog(sprintf("general reached at max_level: %d, skipping levelup test", $LEVELS['max_level']));
			return $cur_level;
		}

		$new_level = 0;

		$general = general::select($tb, 'level, exp_cur, exp_max, skills', "general_id = $general_id AND exp_cur >= exp_max", false);
		if ( $general ) {
			$old_level = $cur_level = $general['level'];
			$next_level = $cur_level + 1;

			$terms = [];
			$exp_cur = $general['exp_cur'];
			$exp_max = $general['exp_max'];

			while (true) {
				elog("general WOULD level up from $cur_level to: $next_level [$exp_cur/$exp_max]");

				if ( !isset($LEVELS[$next_level]) ) {
					// render_error("general level at max: $cur_level");
					elog("general level at max: $cur_level");
					if ( $exp_cur > $terms['exp_max'] )
						$terms['exp_cur'] = $terms['exp_max'];

					if ( sizeof($terms) > 0 ) { // needs update query
						$terms['level'] = $cur_level;
						break;
					}
					return false;
				}

				$LEVEL = $LEVELS[$next_level];

				$gskills = $general['skills'];
				if ( key_exists('points_total', $gskills) )
					$gskills['points_total'] = $next_level;

				global $TROOP_POPULATION_LIMIT;
				$terms = [];
				$terms['level'] = $cur_level;
				$terms['exp_max'] = $LEVEL['req_exp'];
				$terms['pop_max'] = $TROOP_POPULATION_LIMIT; // deprecating as 2013.11
				$terms['activity_max'] = $LEVEL['activity_max'];
				$terms['gold_max'] = $LEVEL['gold_capacity'];
				$terms['honor_max'] = $LEVEL['honor_capacity'];
				$terms['skills'] = "'" . $tb->escape(pretty_json($gskills)) . "'";

				$exp_max = $terms['exp_max'];
				elog(sprintf("at levels: $cur_level=>$next_level,[$exp_cur/$exp_max]"));

				if ( $exp_cur < $terms['exp_max'] ) {
					elog("stopping at cur_level: $cur_level, [$exp_cur/$exp_max]");
					break;
				}
				$cur_level = $next_level;
				$next_level++;
			}

			$new_level = $terms['level'];

			$pairs = join_terms($terms);
			$query = "UPDATE general SET $pairs WHERE general_id = $general_id";
			assert_render($tb->query_with_affected($query, 1));

			gamelog2('general', 'levelup', ['old_level'=>$old_level, 'new_level'=>$new_level]);
		}
		return $new_level;
	}

	public static function update_columns_by_time($tb) {
		global $TAG, $ALLIES, $EMPIRE, $user_id, $general_id, $dev_type, $dev_uuid, $ops, $status, $country;
		global $COLLECTABLE_TAX_COUNT_MAX, $COLLECTABLE_TAX_COOLTIME;
		global $ACTIVITY_GAIN_COOLTIME;

		//elog(">> BGN update_columns_by_time");

		// checks tax_collectable_count, activity_cur at this time (09.23)

		$query = "SELECT ";
		// tax collectable count
		$query .= "tax_collectable_count, TIMESTAMPDIFF(SECOND, tax_timer_willbe_refreshed_at, NOW()) AS tax_dt_diff, tax_timer_willbe_refreshed_at ";
		// activity
		$query .= ", activity_cur, activity_max, activity_willbe_refreshed_at, TIMESTAMPDIFF(SECOND, activity_willbe_refreshed_at, NOW()) AS activity_dt_diff ";
		$query .= "FROM general WHERE general_id = $general_id AND (";
		$query .= "(tax_collectable_count < $COLLECTABLE_TAX_COUNT_MAX AND (tax_timer_willbe_refreshed_at IS NULL OR tax_timer_willbe_refreshed_at <= NOW()))";
		$query .= " OR ";
		$query .= "( (activity_willbe_refreshed_at IS NULL OR activity_willbe_refreshed_at <= NOW()))";
		$query .= ")";

		$tuple = ms_fetch_one($tb->query($query));

		if ( $tuple ) {
			$terms = [];

			$mod = null;
			$new_mod = null;

			// check activity
			$amax = general::apply_effects($tb, 118, $tuple['activity_max']);
			if ( !$tuple['activity_willbe_refreshed_at'] && $tuple['activity_cur'] < $amax ) {
				elog("activity_willbe_refreshed_at was not set");
				$terms['activity_willbe_refreshed_at'] = "TIMESTAMPADD(SECOND, $ACTIVITY_GAIN_COOLTIME, NOW())";
			} else if ( $tuple['activity_cur'] < $amax ) {
				$activity_dt_diff = $tuple['activity_dt_diff'];
				$acur = $tuple['activity_cur'];
				$mod = 0;
				if ( $activity_dt_diff >= 0 ) {
					$mod = (int)floor(($activity_dt_diff+0.1)/$ACTIVITY_GAIN_COOLTIME) + 1;
					$mod =general::apply_effects($tb, 119, $mod);
				}

				if ( $mod > 0 ) {
					$new_val = min($tuple['activity_cur'] + $mod, $amax);
					$new_mod = $new_val - $tuple['activity_cur'];

					elog("acur: $acur, amax: $amax, mod: $mod, new_val: $new_val, new_mod, $new_mod");

					if ( $new_val == $amax )
						$at_sql = "NULL"; // won't be refreshed
					else if ( $activity_dt_diff > 0 )
						$at_sql = "TIMESTAMPADD(SECOND, $new_mod*$ACTIVITY_GAIN_COOLTIME, NOW())";
					else
						$at_sql = "TIMESTAMPADD(SECOND, $new_mod*$ACTIVITY_GAIN_COOLTIME, activity_willbe_refreshed_at)";

					$terms['activity_cur'] = $new_val;
					$terms['activity_willbe_refreshed_at'] = $at_sql;
				}
			}

			$mod = null;
			$new_mod = null;

			// check tax_collectable_count
			if ( !$tuple['tax_timer_willbe_refreshed_at'] && $tuple['tax_collectable_count'] < $COLLECTABLE_TAX_COUNT_MAX ) {
				elog("tax_timer_willbe_refreshed_at was not set");
				$terms['tax_timer_willbe_refreshed_at'] = "TIMESTAMPADD(SECOND, $COLLECTABLE_TAX_COOLTIME, NOW())";
			} else if ( $tuple['tax_collectable_count'] < $COLLECTABLE_TAX_COUNT_MAX ) {
				elog(sprintf("tax_dt_diff: [%d], tax_timer_willbe_refreshed_at: [%s]",
				$tuple['tax_dt_diff'],$tuple['tax_timer_willbe_refreshed_at']));

				$mod = 0;
				if ( $tuple['tax_dt_diff'] >= 0 )
					$mod = (int)floor(($tuple['tax_dt_diff']+0.1)/$COLLECTABLE_TAX_COOLTIME) + 1;

				if ( $mod > 0 ) {
					$new_count = min([$tuple['tax_collectable_count'] + $mod, $COLLECTABLE_TAX_COUNT_MAX]);
					$new_mod = $new_count - $tuple['tax_collectable_count'];

					elog(sprintf("tuple['tax_dt_diff']: %s, tax_collectable_count: %d, new_mod: $new_mod, new_count: $new_count",
					$tuple['tax_dt_diff'], $tuple['tax_collectable_count']));

					$terms['tax_collectable_count'] = $new_count;

					if ( $new_count == $COLLECTABLE_TAX_COUNT_MAX )
						$terms['tax_timer_willbe_refreshed_at'] = "NULL"; // won't be refreshed
					else if ( $tuple['tax_dt_diff'] > 0 )
						$terms['tax_timer_willbe_refreshed_at'] = "TIMESTAMPADD(SECOND, $new_mod*$COLLECTABLE_TAX_COOLTIME, NOW())";
					else
						$terms['tax_timer_willbe_refreshed_at'] = "TIMESTAMPADD(SECOND, $new_mod*$COLLECTABLE_TAX_COOLTIME, tax_timer_willbe_refreshed_at)";

					elog("<< END update_columns_by_time: mod: $mod, new_mod: $new_mod");
				}
			}

			if ( sizeof($terms) > 0 ) {
				$pairs = join_terms($terms);

				$query = "UPDATE general SET $pairs WHERE general_id = $general_id";
				assert_render($tb->query($query));
			}
		} else {
			//	elog("<< END update_columns_by_time");
		}
	}

	/**
	 *
	 * @param unknown $tb
	 * @param unknown $general should include gold, building_list
	 * @return dict mods which includes mod_gold, mod_honor, mod_star, mod_activity
	 */
	private static function calculate_tax($tb, $general) {
		global $BLD_DEPOT_ID, $BLD_CAMP_ID;

		$mods = array();

		$mod_gold = 0;
		$mod_honor = 0;
		$mod_star = 0;
		$mod_activity = 0;

		// 		elog(pretty_json($general['building_list']));

		$hq_bid = $general['building_list']['hq_bid'];
		$hq_level = $general['building_list']['hq_level'];

		$BUILDINGS = construction::get_buildings();
		$BUILDING = $BUILDINGS[$hq_bid];

		$hq_income = $BUILDING['levels'][$hq_level]['value'];
		$camp_income = 0;

		// sum camp_income, // TODO: temporarily as of 11.21
		if ( isset($general['building_list']['non_hq'][$BLD_CAMP_ID]) ) {
			$camps = $general['building_list']['non_hq'][$BLD_CAMP_ID];
			foreach ($camps as $cid => $level) {
				if ( isset($BUILDINGS[$BLD_CAMP_ID]['levels'][$level]['value']) )
					$camp_income += $BUILDINGS[$BLD_CAMP_ID]['levels'][$level]['value'];
			}
		}
		$mod_gold = general::apply_effects($tb, 102, $hq_income + $camp_income);

		// check storage capacity (gold_max)
		$gold_max = general::apply_effects($tb, 116, $general['gold_max']);
		elog("mod_gold($mod_gold) = (int)((hq_income($hq_income)+camp_income($camp_income)) * (by effects) INTO gold_max($gold_max)");

		$mods['mod_gold'] = $mod_gold;
		$mods['mod_honor'] = $mod_honor;
		$mods['mod_star'] = $mod_star;
		$mods['mod_activity'] = $mod_activity;
		$mods['gold_max'] = $gold_max;

		if ( $general['gold'] + $mod_gold > $gold_max ) {
			if ( !(dev && queryparam_fetch_int('ignore') > 0) )
				render_error("not enough space on depots: " . ($general['gold'] + $mod_gold) . " > $gold_max", FCODE(20301));
		}

		return $mods;
	}

	public static function collect_tax($tb) {
		global $TAG, $ALLIES, $EMPIRE, $user_id, $general_id, $dev_type, $dev_uuid, $ops, $status, $country;
		global $COLLECTABLE_TAX_COUNT_MAX, $COLLECTABLE_TAX_COOLTIME;
		global $BLD_DEPOT_ID, $BLD_CAMP_ID;

		$general = general::select($tb, 'gold, gold_max, tax_collectable_count, tax_timer_willbe_refreshed_at, building_list');

		$tax_collectable_count = $general['tax_collectable_count'];
		assert_render($general['tax_collectable_count'] > 0, "not collectable, tax_collectable_count == $tax_collectable_count", FCODE(20403));

		$mods = general::calculate_tax($tb, $general);

		$terms = [];
		$terms['gold'] = "gold + " . $mods['mod_gold'];
		$terms['tax_collectable_count'] = "tax_collectable_count - 1";
		if ( !$general['tax_timer_willbe_refreshed_at'] && $tax_collectable_count == $COLLECTABLE_TAX_COUNT_MAX )
			$terms['tax_timer_willbe_refreshed_at'] = "TIMESTAMPADD(SECOND, $COLLECTABLE_TAX_COOLTIME, NOW())";

		$pairs = join_terms($terms);
		$query = "UPDATE general SET $pairs WHERE general_id = $general_id";
		assert_render($tb->query_with_affected($query, 1));

		quest::resolve_quests($tb, ['Gold_Have']);

		$general = general::select($tb);

		gamelog(__METHOD__, ['mod_gold'=>$mods['mod_gold']]);

		assert_render($tb->end_txn());

		$map['general'] = $general;

		render_ok('collect_tax', $map);
	}

	public static function extra_collect_tax($tb) {
		global $TAG, $ALLIES, $EMPIRE, $user_id, $general_id, $dev_type, $dev_uuid, $ops, $status, $country;
		global $COLLECTABLE_TAX_COUNT_MAX, $COLLECTABLE_TAX_COOLTIME;
		global $COLLECTABLE_TAX_COST_ACTIVITY;
		global $BLD_DEPOT_ID, $BLD_CAMP_ID;

		$general = general::select($tb, 'activity_cur, gold, gold_max, tax_collectable_count, building_list');
		assert_render($general['tax_collectable_count'] == 0, "not collectable, tax_collectable_count > 0", FCODE(20401));

		$activity_cost = $COLLECTABLE_TAX_COST_ACTIVITY;
		$mod_activity = -$activity_cost;
			
		// check activity_cost
		if ( $general['activity_cur'] < $activity_cost )
			render_error("not enough activity: " . $general['activity_cur'] . "< $activity_cost", FCODE(20402));

		$mods = general::calculate_tax($tb, $general);

		$terms = [];
		$terms['gold'] = "gold + " . $mods['mod_gold'];
		$terms['activity_cur'] = "activity_cur + ($mod_activity)";
		$pairs = join_terms($terms);

		$query = "UPDATE general SET $pairs WHERE general_id = $general_id";
		assert_render($tb->query_with_affected($query, 1));

		quest::resolve_quests($tb, ['Gold_Have']);

		$general = general::select($tb);

		gamelog(__METHOD__, ['mod_gold'=>$mods['mod_gold']]);

		assert_render($tb->end_txn());

		$map['general'] = $general;

		render_ok('extra_collect_tax', $map);
	}

	private static function skills_update_tree($tb, $gskills = null) {
		global $TAG, $ALLIES, $EMPIRE, $user_id, $general_id, $dev_type, $dev_uuid, $ops, $status, $country;
		global $COLLECTABLE_TAX_COUNT_MAX, $COLLECTABLE_TAX_COOLTIME;
		global $SKILLS_MULTIPLIER;

		elog(">> BGN skills_update_tree: " . pretty_json($gskills));

		if ( $gskills == null ) {
			$general = general::select($tb, "level, skills");

			$points_total = 1;
			if ( $general['level'] > 0 )
				$points_total = $general['level']*$SKILLS_MULTIPLIER;

			$gskills = $general['skills'];
			if ( $gskills == null )
				$gskills = array('points_offense'=>0, 'points_defense'=>0, 'points_support'=>0, 'points_total'=>$points_total);

			elog("skills_update_tree::parsed gskills: " . pretty_json($gskills));
		}

		$skills = general::get_skills();

		$points_offense = 0;
		$points_defense = 0;
		$points_support = 0;
		$points_total = $gskills['points_total'];

		// pre-calculate ODS points
		foreach ( $gskills as $gsid => $body ) {
			if ( is_array($body) && array_key_exists('level', $body) && $body['level'] > 0 ) {
				$type = $skills[$gsid]['type'];

				if ( $type == 1 )
					$points_offense += $body['level'];
				if ( $type == 2 )
					$points_defense += $body['level'];
				if ( $type == 3 )
					$points_support += $body['level'];
			}
		}
		elog("pre-calculated ODS points: {points_offense: $points_offense, points_defense: $points_defense, points_support: $points_support}");

		$gskills['points_offense'] = $points_offense;
		$gskills['points_defense'] = $points_defense;
		$gskills['points_support'] = $points_support;
		$gskills['points_total'] = $points_total;

		// fill available skills
		foreach ( $skills as $sid => $skill ) {
			if ( strstr($sid, 'ids') != false )
				continue;

			$available_now = 1;

			if ( in_array($sid, $skills['skills_ids_basic']) ) {
				if ( array_key_exists($sid, $gskills) )
					$available_now = 0;
			} else {
				$checked = false;

				if ( $skill['require_skill_id'] > 0 && $skill['require_skill_level'] > 0 ) {
					$checked = true;

					$rkid = $skill['require_skill_id'];
					$rklv = $skill['require_skill_level'];

					// 					elog("checking {sid: $sid, rkid: $rkid, rklv: $rklv} ...");

					if ( !(array_key_exists($rkid, $gskills) && $gskills[$rkid] != null && $gskills[$rkid]['level'] >= $rklv) )
						$available_now = 0;
				}
				if ( $skill['require_point'] > 0 ) {
					$checked = true;

					$rkpt = $skill['require_point'];
					$rktype = $skill['require_type'];

					// 					elog("checking {sid: $sid, rktype: $rktype, rkpt: $rkpt} ...");

					if ( $rktype == 1 && $rkpt > $points_offense )
						$available_now = 0;
					if ( $rktype == 2 && $rkpt > $points_defense )
						$available_now = 0;
					if ( $rktype == 3 && $rkpt > $points_support )
						$available_now = 0;
				}

				if ( $checked == false ) // skip if it was not check
					$available_now = 0;
			}

			//elog("sid: $sid is available_now: $available_now");

			if ( $available_now && !isset($gskills[$sid]) ) {
				elog("filling new available skill: $sid ... ");
				$gskills[$sid] = array('level' => 0);
			}
		}

		$ejs = $tb->escape(pretty_json($gskills));

		$query = "UPDATE general SET skills = '$ejs' WHERE general_id = $general_id";
		assert_render($rs = $tb->query($query));

		elog("<< END skills_update_tree");

		return $gskills;
	}

	public static function skills_levelup($tb) {
		global $TAG, $ALLIES, $EMPIRE, $user_id, $general_id, $dev_type, $dev_uuid, $ops, $status, $country;
		global $COLLECTABLE_TAX_COUNT_MAX, $COLLECTABLE_TAX_COOLTIME;
		global $SKILLS_MULTIPLIER;

		$SKILLS = general::get_skills();

		$skill_id = queryparam_fetch_int('skill_id');
		assert_render(isset($SKILLS[$skill_id]), "invalid:skill_id:$skill_id", FCODE(20100));

		$general = general::select($tb, 'level, skills');

		$points_total = 1;
		if ( $general['level'] > 0 )
			$points_total = $general['level']*$SKILLS_MULTIPLIER;

		$gskills = $general['skills'];
		if ( $gskills == null )
			$gskills = array('points_offense'=>0, 'points_defense'=>0, 'points_support'=>0, 'points_total'=>$points_total);

		elog("skills_levelup::parsed gskills: " . pretty_json($gskills));

		$skill = $SKILLS[$skill_id];

		$cur_level = null;
		if ( array_key_exists($skill_id, $gskills) )
			$cur_level = $gskills[$skill_id]['level'];

		$points_offense = $gskills['points_offense'] > 0 ? $gskills['points_offense'] : 0;
		$points_defense = $gskills['points_defense'] > 0 ? $gskills['points_defense'] : 0;
		$points_support = $gskills['points_support'] > 0 ? $gskills['points_support'] : 0;

		// check remaining points
		if ( ($points_offense + $points_defense + $points_support) >= $points_total ) {
			if ( !(dev && queryparam_fetch_int('ignore_points_total') > 0) )
				render_error("invalid:skill_id:$skill_id:points_total at max: $points_total", FCODE(20108));
		}

		// check level
		if ( $cur_level != null && $cur_level >= $skill['max_level'] )
			render_error("invalid:skill_id:$skill_id:reached at max_level: $cur_level", FCODE(20101));

		// check requirements
		$available_now = true;
		$missing = array();

		if ( $skill['require_skill_id'] > 0 && $skill['require_skill_level'] > 0 && $gskills[$skill_id] != null ) {
			$rkid = $skill['require_skill_id'];
			$rklv = $skill['require_skill_level'];

			elog("checking {skill_id: $skill_id, rkid: $rkid, rklv: $rklv} ...");

			if ( !($gskills[$rkid] != null && $gskills[$rkid]['level'] >= $rklv) ) {
				$missing['require_skill_id'] = $rkid;
				$missing['require_skill_level'] = $rklv;

				$available_now = false;
			}
		}
		if ( $skill['require_point'] > 0 ) {
			$rktype = $skill['require_type'];
			$rkpt = $skill['require_point'];

			elog("checking {skill_id: $skill_id, rktype: $rktype, rkpt: $rkpt} ...");

			if ( $rktype == 1 && $rkpt > $points_offense )
				$available_now = false;
			if ( $rktype == 2 && $rkpt > $points_defense )
				$available_now = false;
			if ( $rktype == 3 && $rkpt > $points_support )
				$available_now = false;
		}

		if ( $available_now == false )
			render_error("invalid:skill_id:$skill_id:requirements missing", array('fcode'=>20102, 'missing'=>$missing));

		// do levelup
		if ( $cur_level == null )
			$gskills[$skill_id] = array('level'=>0);
		$gskills[$skill_id]['level'] += 1;
		elog("skill_levelup: skill_id: $skill_id, to " . $gskills[$skill_id]['level']);

		$new_gskills = general::skills_update_tree($tb, $gskills);

		general::calculate_and_update_static_effects($tb);

		gamelog(__METHOD__, ['new_level'=>$gskills[$skill_id]['level'], 'skill_id'=>$skill_id]);

		assert_render($tb->end_txn(), __METHOD__);

		$map['general'] = array('skills'=>$new_gskills);

		render_ok('success', $map);
	}

	public static function skills_reset($tb) {
		global $TAG, $ALLIES, $EMPIRE, $user_id, $general_id, $dev_type, $dev_uuid, $ops, $status, $country;
		global $COLLECTABLE_TAX_COUNT_MAX, $COLLECTABLE_TAX_COOLTIME;
		global $GENERAL_RESET_SKILLS_COST_STAR;
		global $SKILLS_MULTIPLIER;

		$general = general::select($tb, 'level, star');

		$cost_star = $GENERAL_RESET_SKILLS_COST_STAR;
		if ( $general['star'] < $cost_star )
			render_error("not enough star, ". $general['star'] . " < $cost_star", array('fcode'=>10102, 'cost_star'=>$cost_star));

		$points_total = 1;
		if ( $general['level'] > 0 )
			$points_total = $general['level']*$SKILLS_MULTIPLIER;

		$gskills = array('points_offense'=>0, 'points_defense'=>0, 'points_support'=>0, 'points_total'=>$points_total);
		$new_gskills = general::skills_update_tree($tb, $gskills);

		$query = "UPDATE general SET star = star - $cost_star WHERE general_id = $general_id";
		assert_render($tb->query_with_affected($query, 1));

		general::calculate_and_update_static_effects($tb);

		gamelog(__METHOD__, []);

		assert_render($tb->end_txn(), __METHOD__);

		$map['general'] = array('skills'=>$new_gskills);

		render_ok('success', $map);
	}

	public static function badge_acquire($tb) {
		global $TAG, $ALLIES, $EMPIRE, $user_id, $general_id, $dev_type, $dev_uuid, $ops, $status, $country;

		if (!dev)
			render_error('not allowed');

		$acquire = queryparam_fetch('acquire');
		$BADGES = general::get_badges();

		$badge_list = ['_'=>'_'];

		if ( $acquire == 'all' ) {
			elog("acquiring all badges");

			$keys = array_keys($BADGES);
			foreach ($keys as $key) {
				if ( !is_numeric($key) ) continue;
				$badge_list[$key] = [ 'level' => $BADGES[$key]['max_level'] ];
			}
		} else if ( $acquire == 'random' ) {
			$population = mt_rand((sizeof($BADGES)-1)/3, 2*(sizeof($BADGES)-1)/3);
			elog("acquiring $population badges");

			$keys = array_keys($BADGES);
			$rand_keys = array_rand($keys, $population);

			$picked_keys = [];
			foreach ($rand_keys as $rand_key) {
				$key = $keys[$rand_key];
				if ( !is_numeric($key) ) continue;
				$badge_list[$key] = array('level'=>$BADGES[$key]['max_level']);

				$picked_keys[] = $key;
			}
			elog("picked badges: " . pretty_json($picked_keys));
		} else
			elog("clearing badges");

		$jsstr = $tb->escape(pretty_json($badge_list));
		$query = "UPDATE general SET badge_list = '$jsstr', badge_equipped_id = NULL, badge_willbe_refreshed_at = NULL WHERE general_id = $general_id";
		assert_render($tb->query($query));
			
		general::calculate_and_update_static_effects($tb);

		// then 'get' will call end_txn()
	}

	public static function badge_equip($tb) {
		global $TAG, $ALLIES, $EMPIRE, $user_id, $general_id, $dev_type, $dev_uuid, $ops, $status, $country;
		global $COLLECTABLE_TAX_COUNT_MAX, $COLLECTABLE_TAX_COOLTIME;
		global $BADGE_EQUIP_COOLTIME;

		$badge_id = queryparam_fetch_int('badge_id');
		assert_render($badge_id >= 0, "invalid:badge_id:$badge_id", FCODE(20104));

		$general = general::select($tb,
				"badge_equipped_id, badge_list, TIMESTAMPDIFF(SECOND, NOW(), badge_willbe_refreshed_at) AS dt_diff, badge_willbe_refreshed_at");

		if ( $general['badge_equipped_id'] == $badge_id )
			render_error("invalid:badge_id:equals to badge_equipped_id:$badge_id", FCODE(20105));

		$badge_list = $general['badge_list'];

		if ( $badge_list == null || array_key_exists($badge_id, $badge_list) == false )
			render_error("invalid:badge_id:dont have that badge:$badge_id", FCODE(20106));

		if ( $general['dt_diff'] > 0 ) {
			if ( dev && queryparam_fetch_int('ignore_badge_cooltime') > 0 )
				;
			else {
				$map['fcode'] = 20107;
				$map['general'] = $general;
				render_error("badge equip cooltime error: should wait: ". ($general['dt_diff']), $map);
			}
		}

		$terms = [];
		$terms['badge_equipped_id'] = $badge_id;
		$terms['badge_willbe_refreshed_at'] = "TIMESTAMPADD(SECOND, $BADGE_EQUIP_COOLTIME, NOW())";

		$pairs = join_terms($terms);
		$query = "UPDATE general SET $pairs WHERE general_id = $general_id";
		assert_render($tb->query_with_affected($query, 1));

		general::calculate_and_update_static_effects($tb);

		gamelog(__METHOD__, ['badge_id'=>$badge_id]);

		// post push
		$general = general::select($tb, 'badge_willbe_refreshed_at');
		$context = [];
		$context['user_id'] = $user_id;
		$context['dev_type'] = session_GET('dev_type');
		$context['dev_uuid'] = session_GET('dev_uuid');
		$context['src_id'] = "general:badge_is_equipable:$general_id";
		$context['send_at'] = $general['badge_willbe_refreshed_at'];
		$context['body'] = "general:badge:badge_is_equipable done";
		event::push_post($tb, $context);

		assert_render($tb->end_txn());

		render_ok('success');
	}

	public static function tutorial_reset($tb) {
		global $TAG, $ALLIES, $EMPIRE, $user_id, $general_id, $dev_type, $dev_uuid, $ops, $status, $country;

		$TUTORIALS = general::get_tutorials();

		$ejs = ms_quote($tb->escape(pretty_json(empty($TUTORIALS['tutorial_ids']) ? [] : $TUTORIALS['tutorial_ids'])));

		$query = "UPDATE general SET tutorial_list = $ejs WHERE general_id = $general_id";
		assert_render($tb->query($query));

		gamelog(__METHOD__);

		// 		assert_render($tb->end_txn());
		// 		render_ok('success');
	}

	public static function tutorial_finish($tb) {
		global $TAG, $ALLIES, $EMPIRE, $user_id, $general_id, $dev_type, $dev_uuid, $ops, $status, $country;

		$tutorial_id = queryparam_fetch_int('tutorial_id');
		$TUTORIALS = general::get_tutorials();
		$TUTORIAL = @$TUTORIALS[$tutorial_id];

		assert_render($TUTORIAL, "invalid:tutorial_id:$tutorial_id");

		$general = general::select($tb, 'tutorial_list');
		$tutorial_list = $general['tutorial_list'];

		// TODO: do we have rewards for tutorial?
		$tutorial_list = array_values(array_diff($tutorial_list, [$tutorial_id]));

		$ejs = ms_quote($tb->escape(pretty_json($tutorial_list)));

		$query = "UPDATE general SET tutorial_list = $ejs WHERE general_id = $general_id";
		assert_render($tb->query($query));

		$detail = [];
		$detail['tutorial_id'] = $tutorial_id;

		gamelog(__METHOD__, $detail);

		// 		assert_render($tb->end_txn());
		// 		render_ok('success');
	}

	private static function fetch_effect_value_from_badge($BADGES, $general, $badge_id) {
		$badge_list = $general['badge_list'];

		if ( isset($general['badge_equipped_id']) && $general['badge_equipped_id'] == $badge_id ) {
			if ( isset($badge_list[$badge_id]['level']) && $badge_list[$badge_id]['level'] > 0 ) {
				$level = $badge_list[$badge_id]['level'];
				if ( isset($BADGES[$general['badge_equipped_id']]['levels'][$level]['effects']) ) {
					$vals = array_values($BADGES[$general['badge_equipped_id']]['levels'][$level]['effects']);
					if ( sizeof($vals) == 1 && isset($vals[0]['value']) ) {
						return $vals[0]['value'];
					}
				}
			}
		}
		return null;
	}

	private static function fetch_effect_value_from_skill($SKILLS, $general, $skill_id) {
		$skills = $general['skills'];

		if ( isset($skills[$skill_id]['level']) && $skills[$skill_id]['level'] > 0 ) {
			$level = $skills[$skill_id]['level'];
			if ( isset($SKILLS[$skill_id]['levels'][$level]['value']) )
				return $SKILLS[$skill_id]['levels'][$level]['value'];
		}
		return null;
	}

	private static function fetch_effect_value_from_building($BUILDINGS, $general, $building_id) {
		$building_list = $general['building_list'];

		if ( isset($building_list['hq_bid']) && $building_list['hq_bid'] == $building_id ) {
			$level = $building_list['hq_level'];

			if ( $level > 0 && isset($BUILDINGS[$building_id]['levels'][$level]['value']) )
				return $BUILDINGS[$building_id]['levels'][$level]['value'];
		}
		if ( isset($building_list['shq_bid']) && $building_list['shq_bid'] == $building_id ) {
			$level = $building_list['hq_level'];

			if ( $level > 0 && isset($BUILDINGS[$building_id]['levels'][$level]['value']) )
				return $BUILDINGS[$building_id]['levels'][$level]['value'];
		}
		if ( isset($building_list['non_hq']) ) {
			$touch = false;
			$val = 0.0;
			foreach ( $building_list['non_hq'] as $bid => $cid_levels ) {
				if ( $bid != $building_id )
					continue;

				foreach ( $cid_levels as $cid => $level ) {
					if ( $level > 0 ) {
						$val += $BUILDINGS[$bid]['levels'][$level]['value'];
						$touch = true;
					}
				}
			}
			if ( $touch )
				return $val;
		}
		return null;
	}

	/**
	 * check that unit of effect_id is persentage or not
	 * @param unknown $effect_id
	 * @return boolean true on persentage
	 */
	public static function unit_of_effect_is_persentage($effect_id) {
		if ( (114 <= $effect_id && $effect_id <= 119)
		|| (121 <= $effect_id && $effect_id <= 124)
		|| (131 <= $effect_id && $effect_id <= 132)
		|| (154 == $effect_id || 156 == $effect_id) ) {
			return false;
		}
		return true;
	}

	public static function calculate_static_effects($tb, $general = null, $effect_id = null) {
		$timer_bgn = microtime(true);

		$effect_values = [];
		$EFFECT_INFO = general::get_effect_info();

		$general = null; // always!
		if ( !$general )
			$general = general::select($tb, 'country, legion_joined_id, skills, building_list, badge_list, badge_equipped_id');

		$BADGES = general::get_badges();
		$SKILLS = general::get_skills();
		$BUILDINGS = construction::get_buildings();

		// 		elog("general info for effect calculation: " . pretty_json($general));

		foreach ( $EFFECT_INFO as $eid => $table) {
			$val = 0.0;
			$touch = false;

			if ( !empty($table['building_id']) ) {
				if ( $v = general::fetch_effect_value_from_building($BUILDINGS, $general, $table['building_id']) ) {
					$val += $v;
					elog("[eid: $eid] effect_value from building: $v, building_id: " . $table['building_id']);
					$touch = true;
				}
			}
			if ( !empty($table['skill_id']) ) {
				if ( $v = general::fetch_effect_value_from_skill($SKILLS, $general, $table['skill_id']) ){
					$val += $v;
					elog("[eid: $eid] effect_value from skill: $v, skill_id: " . $table['skill_id']);
					$touch = true;
				}
			}
			if ( !empty($table['badge_id']) ) {
				if ( $v = general::fetch_effect_value_from_badge($BADGES, $general, $table['badge_id']) ){
					$val += $v;
					elog("[eid: $eid] effect_value from badge: $v, badge_id: " . $table['badge_id']);
					$touch = true;
				}
			}

			if ( !general::unit_of_effect_is_persentage($eid) ) {

			}

			if ( $touch )
				$effect_values[$eid] = $val;
		}

		// apply occupy effects
		// have battlefield changed?
		$battlefield = tile::bf_select($tb);
		// assert_render($battlefield);

		// occupy by forces
		if ( $general['country'] == ALLIES && !empty($battlefield['effects_allies']) ) {
			foreach ($battlefield['effects_allies'] as $eid => $val) {
				if ( !isset($effect_values[$eid]) )
					$effect_values[$eid] = 0;
				$effect_values[$eid] += $val;
				elog("[eid: $eid] effect_value from occupy: $val");
			}
		}
		else if ( $general['country'] == EMPIRE && !empty($battlefield['effects_empire']) ) {
			foreach ($battlefield['effects_empire'] as $eid => $val) {
				if ( !isset($effect_values[$eid]) )
					$effect_values[$eid] = 0;
				$effect_values[$eid] += $val;
				elog("[eid: $eid] effect_value from occupy: $val");
			}
		}

		// occupy by legions
		if ( !empty($general['legion_joined_id']) ) {
			if ( !empty($battlefield['effects_legion'][$general['legion_joined_id']]) ) {
				// TODO: legions, implement me
				elog("effect_legions has defined, but not applied!");
			}
		}

		elog("effect_values: " . pretty_json($effect_values, JSON_FORCE_OBJECT));

		$timer_end = microtime(true);
		elog("time took for general::calculate_static_effects(): " . ($timer_end - $timer_bgn));

		if ( sizeof($effect_values) == 0 )
			return null;

		return $effect_values;
	}

	public static function calculate_and_update_static_effects($tb) {
		global $user_id, $general_id;

		$effects = general::calculate_static_effects($tb, null, null);
		if ( !$effects )
			$effects = [];

		$clause = "effects = NULL";
		if ( $effects ) {
			$ejs = $tb->escape(pretty_json($effects, JSON_FORCE_OBJECT));
			$clause = "effects = '$ejs'";
		}
		$query = "UPDATE general SET $clause WHERE general_id = $general_id";
		assert_render($tb->query($query));

		store_into_redis("users:user_id=$user_id:effects", $effects);
		sadd_into_redis("static_effects_updated_user_id_set", $user_id);

		return $effects;
	}

	public static function apply_effects($tb, $effect_id, $val) {
		global $user_id, $general_id;

		$EFFECTS = null;

		$ismember = sismember_of_redis("static_effects_updated_user_id_set", $user_id);
		if ( $ismember > 0 )
			$EFFECTS = fetch_from_redis("users:user_id=$user_id:effects");
		else
			elog("user_id: $user_id is not a member($ismember) of static_effects_updated_user_id_set, invalidating static effects...");

		if ( is_null($EFFECTS) ) { // try again on null
			general::calculate_and_update_static_effects($tb);
			$EFFECTS = fetch_from_redis("users:user_id=$user_id:effects");
			if ( !$EFFECTS )
				elog("[users:user_id=$user_id:effects] was not set");
		}
			
		if ( isset($EFFECTS[$effect_id]) ) {
			$persentage = general::unit_of_effect_is_persentage($effect_id);
			if ( $persentage )
				$new_val = intval((100.0 + floatval($EFFECTS[$effect_id])) / 100.0 * $val);
			else
				$new_val = $val + $EFFECTS[$effect_id];

			$pt = $persentage ? "YES" : "NO ";
			elog("effect[$effect_id] was applied[persentage: $pt], from [$val] to [$new_val]");

			return $new_val;
		}
		return $val;
	}
}

if ( strstr($_SERVER["REQUEST_URI"], basename(__FILE__)) ) {

	$ops = explode(',', $op = queryparam_fetch('op', 'get'));

	$user_id = login_check();
	$general_id = session_GET('general_id');

	$status = queryparam_fetch_int('status');

	if ( sizeof(array_intersect_key(['get', 'clear', 'edit', 'reset_bld_cooltime', 'collect_tax', 'extra_collect_tax']
			+ ['skills_levelup', 'skills_reset', 'badge_equip', 'badge_acquire', 'clear_constant_caches']
			+ ['tutorial_reset', 'tutorial_finish']
			, $ops)) ) {
		for ( $retried = 1 ; $retried <= $TXN_RETRY_MAX ; $retried++ ) {
			try {
				$tb = new TxnBlock();

				if ( in_array("clear", $ops) ) general::clear($tb);
				if ( dev && in_array("clear_constant_caches", $ops) ) general::clear_constant_caches($tb);

				general::update_columns_by_time($tb);
				general::check_general_levelup($tb);
				quest::resolve_quests($tb); // TODO: remove me in the end

				if ( in_array("edit", $ops) ) general::edit($tb);
				else if ( in_array("reset_bld_cooltime", $ops) ) general::reset_bld_cooltime($tb);
				else if ( in_array("collect_tax", $ops) ) general::collect_tax($tb);
				else if ( in_array("extra_collect_tax", $ops) ) general::extra_collect_tax($tb);
				else if ( in_array("skills_levelup", $ops) ) general::skills_levelup($tb);
				else if ( in_array("skills_reset", $ops) ) general::skills_reset($tb);
				else if ( in_array("badge_equip", $ops) ) general::badge_equip($tb);
				else if ( in_array("badge_acquire", $ops) ) general::badge_acquire($tb);

				else if ( in_array("tutorial_reset", $ops) ) general::tutorial_reset($tb);
				else if ( in_array("tutorial_finish", $ops) ) general::tutorial_finish($tb);

				general::get($tb); // embedes end_txn()
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
