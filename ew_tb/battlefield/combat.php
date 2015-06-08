<?php
require_once '../connect.php';
require_once '../general/general.php';
require_once '../army/officer.php';
require_once '../army/troop.php';
require_once '../army/item.php';
require_once '../battlefield/tile.php';

class combat {

	public static function get_reward_groups() {
		if ( $val = fetch_from_cache('constants:reward_groups') )
			return $val;

		$timer_bgn = microtime(true);

		$reward_groupinfo = loadxml_as_dom('xml/reward_info.xml');
		if ( !$reward_groupinfo ) {
			elog("failed to loadxml: " . 'xml/reward_info.xml');
			return null;
		}

		$reward_groups = [];

		foreach ($reward_groupinfo->xpath("//reward_group") as $node ) {
			$attrs = (array)$node->attributes();
			$pattrs = $attrs['@attributes'];

			$group_id = $pattrs["reward_group_id"];

			$newattrs = array();
			foreach ( $pattrs as $key => $val ) {
				// skip client-side effective related keys
				if ( strpos($key, "cl_") === 0 || strpos($key, "desc_") === 0) continue;

				$newattrs[$key] = $val;
			}
			// 			$reward_groups[$group_id] = $newattrs;
			// 			$reward_groups[$group_id] = [];

			$rewards = [];
			foreach($node->xpath("reward") as $reward ) {
				$reward_attrs = (array)$reward->attributes();
				$reward_pattrs = $reward_attrs['@attributes'];

				// 				$reward_id = $reward_pattrs['reward_id'];
				if ( $reward_pattrs['req_rank'] == 'A' ) $reward_pattrs['req_rank'] = 'S';
				if ( $reward_pattrs['req_rank'] == 'B' ) $reward_pattrs['req_rank'] = 'A';
				if ( $reward_pattrs['req_rank'] == 'C' ) $reward_pattrs['req_rank'] = 'B';
				if ( $reward_pattrs['req_rank'] == 'D' ) $reward_pattrs['req_rank'] = 'C';
				if ( $reward_pattrs['req_rank'] == 'E' ) $reward_pattrs['req_rank'] = 'E';
				if ( $reward_pattrs['req_rank'] == 'F' ) $reward_pattrs['req_rank'] = 'F';

				$req_rank = $reward_pattrs['req_rank'];
				$rewards[$req_rank] = $reward_pattrs;
			}
			// 			elog(sprintf("setting [%2d] rewards for group_id [%s]", count($rewards), $group_id));

			$reward_groups[$group_id] = $rewards;
		}

		$bnis = array_keys($reward_groups);

		$timer_end = microtime(true);
		elog("time took for reward_group::get_reward_groups(): " . ($timer_end - $timer_bgn));

		store_into_cache('constants:reward_groups', $reward_groups);

		elog("reward_group reward_groups keys: " . json_encode($bnis));

		return $reward_groups;
	}

	public static function select_all($tb, $select_expr = null, $where_condition = null) {
		global $TAG, $ALLIES, $EMPIRE, $user_id, $general_id, $dev_type, $dev_uuid, $ops, $status, $country;
		global $COMBAT_RUNNING, $COMBAT_VERIFYING, $COMBAT_COMPLETED;

		if ( $select_expr == null )
			$select_expr = "*";
		if ( $where_condition == null ) {
			$where_condition = "WHERE general_id = $general_id";
		}
		else
			$where_condition = "WHERE general_id = $general_id AND ($where_condition)";

		$query = "SELECT $select_expr FROM combat $where_condition /*BY_HELPER*/";
		assert_render($rows = ms_fetch_all($tb->query($query)));

		$json_keys = array('brief', 'result', 'summary');

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
		$rows = combat::select_all($tb, $select_expr, $where_condition);
		if ( $rows && count($rows) == 1 )
			return $rows[0];
		return null;
	}

	public static function clear($tb) {
		global $TAG, $ALLIES, $EMPIRE, $user_id, $general_id, $dev_type, $dev_uuid, $ops, $status, $country;

		if ( dev && in_array("clear", $ops) ) {
			$query = "DELETE FROM combat WHERE general_id = $general_id;";
			assert_render($tb->query($query));
			$query = "UPDATE general SET running_combat_id = NULL WHERE general_id = $general_id;";
			assert_render($tb->query($query));

			tile::default_tiles($tb);
		}
	}

	public static function get($tb) {
		global $TAG, $ALLIES, $EMPIRE, $user_id, $general_id, $dev_type, $dev_uuid, $ops, $status, $country;
		global $COMBAT_RUNNING, $COMBAT_VERIFYING, $COMBAT_COMPLETED;

		$combat_id = queryparam_fetch_int('combat_id');

		$where = '';
		if ( $combat_id > 0 )
			$where .= " combat_id = $combat_id";
		if ( $status > 0 ) {
			if ( $combat_id > 0 )
				$where .= ' AND ';
			$where .= " status = $status";
		}

		$map['combats'] = combat::select_all($tb, null, $where);

		assert_render($tb->end_txn());

		render_ok('success', $map);
	}

	private static function generate_opponent_pve($tb, $vforce, $TILE) {
		global $TROOP_BANDED;

		if ( $vforce == 1 ) $npc_group_id = $TILE['npc_group_id_empire'];
		if ( $vforce == 2 ) $npc_group_id = $TILE['npc_group_id_allies'];

		$NPCGROUPS = tile::get_npc_groups();
		assert_render($NPCGROUP = $NPCGROUPS[$npc_group_id], "invalid:npc_group_id:$npc_group_id");

		// choose a npc by weight
		$npc_ids = array_keys($NPCGROUP['npcs']);
		$npc_weights = array();
		foreach ($npc_ids as $npc_id)
			$npc_weights[] = $NPCGROUP['npcs'][$npc_id]['weight'];

		$npc_id = weighted_choice($npc_ids, $npc_weights);

		elog("PvE: npc_group_id:$npc_group_id, npc_id:$npc_id");

		$npc = $NPCGROUP['npcs'][$npc_id];

		$vgeneral = array();
		$vofficer = array();
		$vtroops = array();

		// generate an general
		$vgeneral['name'] = $npc['npc_name'];
		$vgeneral['level'] = $npc['level'];

		// generate an NPC officer
		$OFFICERS = officer::get_officers();
		$OFFICER = $OFFICERS[$npc['officer_id']];

		$vofficer['grade'] = $npc['officer_grade'];
		$vofficer['type_id'] = $npc['officer_id'];
		$vofficer['speciality'] = $OFFICER['speciality'];
		$vofficer['level'] = $npc['level'];
		foreach (['offense', 'defense', 'tactics', 'resists'] as $term )
			$vofficer[$term] = $OFFICER['grades'][$npc['officer_grade']][$term];

		// generate NPC troops(units)
		$UNITS = troop::get_units();
		foreach ($npc['slots'] as $slot_idx => $unit ) {
			$UNIT = $UNITS[$unit['unit_id']];

			$new_unit = array();

			$new_unit['type_major'] = 2;
			if ( $UNIT['type'] == '1' )
				$new_unit['type_major'] = 1;
			$new_unit['type_minor'] = $unit['unit_id'];
			$new_unit['status'] = $TROOP_BANDED;

			$new_unit['qty'] = $unit['unit_qty'];
			$new_unit['slot'] = $slot_idx;

			$vtroops[] = $new_unit;
		}

		$opponent = array();

		$opponent['general'] = $vgeneral;
		$opponent['officer'] = $vofficer;
		$opponent['troops'] = $vtroops;

		$opponent['pve_grade'] = $TILE['pve_grade'];
		$opponent['recomm_level'] = $TILE['recomm_level'];

		return $opponent;
	}

	private static function generate_opponent_pvpl($tb, $officer, $vforce, $for_legion = false) {
		global $TAG;

		$search_from_mysql = 0;

		$ranges = [];
		$ranges[] = ["level" => 0, "commands" => [0, 10, 30]];
		$ranges[] = ["level" => 1, "commands" => [0, 10, 30]];
		$ranges[] = ["level" => 2, "commands" => [0, 10, 30]];
		$ranges[] = ["level" => 3, "commands" => [0, 10, 30]];
		$ranges[] = ["level" => 5, "commands" => [0, 10, 30]];

		$timer_bgn = microtime(true);

		$army = null;
		foreach ($ranges as $range) {
			if ( $army ) break;

			$level = $range['level'];

			foreach ($range['commands'] as $command) {
				if ( $army ) break;

				if ( $search_from_mysql ) {
					$search_empire = $vforce == 2 ? "TRUE" : "FALSE";
					$search_legion = $for_legion ? "TRUE" : "FALSE";

					$level_command_min = 100000* (max(1, $officer['level'] - $level)) + max(1, $officer['command_cur'] - $command);
					$level_command_max = 100000* ($officer['level'] + $level) + $officer['command_cur'] + $command;

					// check me on performance, 1014: redis will work
					$query = "SELECT * FROM army WHERE empire = $search_empire AND legion = $search_legion "
					."AND officer_level_command BETWEEN $level_command_min AND $level_command_max ORDER BY RAND() LIMIT 1";

					$army = ms_fetch_one($tb->query($query));
				} else {
					$fllc_min = officer::fllc_mux($vforce, $for_legion?1:0, max(1, $officer['level'] - $level), max(1, $officer['command_cur'] - $command));
					$fllc_max = officer::fllc_mux($vforce, $for_legion?1:0, $officer['level'] + $level, $officer['command_cur'] + $command);

					$redis = conn_redis();
					$uid_fllcs = $redis->zRangeByScore("$TAG:armys:force_legion_level_command", $fllc_min, $fllc_max,
							array('withscores' => TRUE, 'limit' => array(0, 100)));
					$len = sizeof($uid_fllcs);
					elog("for pvpl, fllc_min: $fllc_min, fllc_max: $fllc_max, len: $len");

					if ( $len > 0 ) {
						$keys = array_keys($uid_fllcs);
						$opp_user_id = $keys[mt_rand(0, $len-1)];
						$army_str = $redis->get("$TAG:users:user_id=$opp_user_id:army");
						if ( $army_str ) {
							$army = @json_decode($army_str, true);
						}
					}
				}
			}
		}

		$search_time = microtime(true) - $timer_bgn;

		$opponent = null;
		if ( $army ) {
			if ( $search_from_mysql )
				$army['brief'] = @json_decode($army['brief'], true);
			$opponent = $army;

			elog("opponent was matched[$search_time,s]: general_id: " . $army['general']['general_id']);
		} else
			elog("opponent was not found[$search_time,s]");

		return $opponent;
	}

	public static function begin($tb) {
		global $TAG, $ALLIES, $EMPIRE, $user_id, $general_id, $dev_type, $dev_uuid, $ops, $status, $country;
		global $COMBAT_RUNNING, $COMBAT_VERIFYING, $COMBAT_COMPLETED;
		global $OFFICER_UNHIRED, $OFFICER_HIRED, $OFFICER_TRAINING, $OFFICER_HEALING, $OFFICER_DEAD;
		global $TROOP_TRAINING, $TROOP_TRAINED, $TROOP_BANDED;

		$tile_position = queryparam_fetch('tile_position');
		$pvp = queryparam_fetch_int('pvp', 0);
		$pvl = queryparam_fetch_int('pvl', 0);

		$combats = combat::select_all($tb, null, "status = $COMBAT_RUNNING");
		if ( $combats && count($combats) > 0 ) {
			// fetch previous RUNNING combat
			assert_render(count($combats) == 1);
			assert_render($tb->end_txn());

			$combat_id = $combats[0]['combat_id'];
			$map['combats'] = $combats;
			$map['fcode'] = 40101;

			$msg = "previous running combat was found: combat_id: $combat_id";
			elog($msg);
			render_ok($msg, $map);
		}

		$TILES = tile::get_tiles();
		assert_render($tile_position, "invalid:tile_position:$tile_position");

		assert_render(isset($TILES[$tile_position]) && ($TILE = $TILES[$tile_position]),
		"invalid:tile_position:no such tile:$tile_position", FCODE(40103));
			
		$seed = mt_rand(1, 10000);

		$brief = array();
		$brief['seed'] = $seed;
		$brief['tile_position'] = $tile_position;
		$brief['pvp'] = $pvp;
		$brief['pvl'] = $pvl;

		// our force
		$general = general::select($tb);

		// check previous combat_id
		assert_render($general['running_combat_id'] == null);

		// check leading officer
		$leading_officer_id = $general['leading_officer_id'];
		assert_render($leading_officer_id > 0, "no leading officer was found", FCODE(40102));
		$officer = officer::select($tb, null, "officer_id = $leading_officer_id AND status = $OFFICER_HIRED");
		assert_render($officer, "no leading officer was found", FCODE(40102));

		// get item info
		$equipments = $officer['equipments'] ?: [];
		$officer['equipments_detail'] = [];
		foreach ($equipments as $item_id) {
			global $ITEM_OWNERLESS, $ITEM_MAKING, $ITEM_GENERAL_OWNED, $ITEM_MAIL_OWNED, $ITEM_OFFICER_OWNED;
			global $ITEM_TYPE_MAJOR_EQUIPS, $ITEM_TYPE_MAJOR_COMBATS, $ITEM_TYPE_MAJOR_CONSUMES;

			$item = item::select($tb, null, "item_id = $item_id");
			assert_render($item, "invalid:equipment:item_id:$item_id");

			if ( ($item['status'] == $ITEM_GENERAL_OWNED && $item['type_major'] == $ITEM_TYPE_MAJOR_COMBATS)
			|| ($item['status'] == $ITEM_OFFICER_OWNED && $item['type_major'] == $ITEM_TYPE_MAJOR_EQUIPS) ) {
				$officer['equipments_detail'][] = $item;
			} else {
				elog("not proper for combat, item_id:$item_id");
				$equipments_detail[] = []; // push empty array
			}
		}

		// check activity cost
		$cost_activity = $TILE['cost_activity_pve'];
		if ( $pvl || $pvp )
			$cost_activity = $TILE['cost_activity_pvp'];

		$cur_activity = $general['activity_cur'];
		if ( $cur_activity < $cost_activity ) {
			$map['cost_activity'] = $cost_activity;
			$map['cur_activity'] = $cur_activity;
			$map['fcode'] = 40105;

			render_error("not enough activity: cur_activity($cur_activity) < cost_activity($cost_activity)", $map);
		}

		// check tile_position is valid for combat
		$tile = tile::select($tb, null, "battlefield_id = (SELECT MAX(battlefield_id) FROM battlefield) AND position = '$tile_position'");
		assert_render($tile, null, FCODE(40103));

		if ( $pvl ) {
			assert_render(in_array($tile_position, $TILES['tiles_forts']),
			"invalid:tile_position:not allowed to combat::$tile_position", FCODE(40104));
		}
		else if ( $pvp ) {
			assert_render(!in_array($tile_position, $TILES['tiles_forts']) && $tile['dispute'] > 0,
			"invalid:tile_position:not allowed to combat::$tile_position", FCODE(40104));
		}
		else {
			assert_render($tile['occupy_force'] == $general['country'], "invalid:tile_position:not allowed to combat:$tile_position", FCODE(40104));
			assert_render((in_array($tile_position, $TILES['tiles_normal'])
			|| ($tile_position == $TILES['tiles_hq_allies'] || $tile_position == $TILES['tiles_hq_empire'])),
			"invalid:tile_position:not allowed to combat:$tile_position", FCODE(40104));
		}

		$brief['tile'] = $tile;

		$query = "SELECT * FROM troop WHERE general_id = $general_id AND officer_id = $leading_officer_id AND status = $TROOP_BANDED";
		assert_render($troops = ms_fetch_all($tb->query($query)));

		$brief['ourforce']['general'] = $general;
		$brief['ourforce']['officer'] = $officer;
		$brief['ourforce']['troops'] = $troops;

		// opponent force
		$vforce = ALLIES;
		if ( $general['country'] == ALLIES )
			$vforce = EMPIRE;

		elog("Combat on tile_id: $tile_position against force: $vforce");

		$opponent = null;
		if ( $pvp || $pvl ) $opponent = combat::generate_opponent_pvpl($tb, $officer, $vforce, $pvl);
		if ( !$opponent ) $opponent = combat::generate_opponent_pve($tb, $vforce, $TILE);

		$brief['opponent'] = $opponent;

		$jsbrief = pretty_json($brief);
		$ejsbrief = $tb->escape($jsbrief);

		$query = "INSERT INTO combat (general_id, status, seed, brief, created_at) VALUES ($general_id, $COMBAT_RUNNING, $seed, '$ejsbrief', NOW())";
		assert_render($tb->query_with_affected($query, 1));

		$new_combat_id = $tb->mc()->insert_id;

		// update running_combat_id, counts
		$terms = [];
		$terms['running_combat_id'] = $new_combat_id;
		$terms['activity_cur'] = "activity_cur - $cost_activity";

		$pairs = join_terms($terms);
		$query = "UPDATE general SET $pairs WHERE general_id = $general_id";
		assert_render($tb->query_with_affected($query, 1));

		$combats = combat::select_all($tb, null, "combat_id = $new_combat_id");

		// 		gamelog(__METHOD__, ['combat' => $combats[0]]);

		assert_render($tb->end_txn());

		global $TAG, $BATTLEFIELD_HOTSPOT_TIME_QUANTUM;
		$combat_at = new DateTime();
		$combat_at->setTimestamp(time());
		$combat_at_minute = intval($combat_at->format('i'));
		$hskey = sprintf("$TAG:battlefield:hotspot:combat_counts:%02d",
				intval($BATTLEFIELD_HOTSPOT_TIME_QUANTUM*floor($combat_at_minute / $BATTLEFIELD_HOTSPOT_TIME_QUANTUM)));

		$redis = conn_redis();
		// update tile, for hotspot
		// zset TAG:battlefield:combat_counts [position=>counts] with incr by 1
		$redis->zIncrBy($hskey, 1, $tile_position);

		$map['combats'] = $combats;

		render_ok('success', $map);
	}

	public static function submit($tb) {
		global $TAG, $ALLIES, $EMPIRE, $user_id, $general_id, $dev_type, $dev_uuid, $ops, $status, $country;
		global $COMBAT_RUNNING, $COMBAT_VERIFYING, $COMBAT_COMPLETED;
		global $OFFICER_UNHIRED, $OFFICER_HIRED, $OFFICER_TRAINING, $OFFICER_HEALING, $OFFICER_DEAD;
		global $TROOP_TRAINING, $TROOP_TRAINED, $TROOP_BANDED;
		global $COMBAT_TOP_RANK;
		global $ITEM_TYPE_MAJOR_EQUIPS, $ITEM_TYPE_MAJOR_COMBATS, $ITEM_TYPE_MAJOR_CONSUMES;

		$combat_id = queryparam_fetch_int('combat_id');
		$result_str = queryparam_fetch('result');
		$result = @json_decode($result_str, true);

		elog($result_str);

		assert_render($combat_id > 0, "$combat_id");
		assert_render($result);

		$combats = combat::select_all($tb, "*, TIMESTAMPDIFF(SECOND, created_at, NOW()) AS combat_seconds", "combat_id = $combat_id");

		assert_render(count($combats) == 1 && ($combat = $combats[0]));
		assert_render($combat['status'] == $COMBAT_RUNNING);

		// TODO: POSTBETA do verification here

		// apply result
		$REWARDS = combat::get_reward_groups();
		$UNITS = troop::get_units();

		// 		if ( !isset($result['opponent']['officer']) ) render_error("result.opponent.officer not found", FCODE(40201));
		if ( !isset($result['opponent']['troops']) ) render_error("result.opponent.troops not found", FCODE(40201));

		$opponent_old_total_command = 0;
		$opponent_new_total_command = 0;

		// clear reward (get on victory)
		$clear_reward_gold = 0;
		$clear_reward_exp = 0;
		$clear_reward_honor = 0;

		$kill_reward_gold = 0; // for pve
		$kill_reward_exp = 0; // for pve
		$kill_reward_honor = 0; // for pvp, pvl

		foreach ($result['opponent']['troops'] as $troop) {
			if ( !isset($troop['new_qty']) ) render_error("result.opponent.troops.troop.new_qty not found", FCODE(40201));

			assert_render($troop['new_qty'] >= 0 && $troop['new_qty'] <= $troop['qty'], "MUST: new_qty >= 0 AND new_qty <= qty", FCODE(40202));

			$UNIT = $UNITS[$troop['type_minor']];

			$killed_troop = ($troop['qty'] - $troop['new_qty']);
			$kill_reward_gold += $UNIT['obtain_gold'] * $killed_troop;
			$kill_reward_exp += $UNIT['obtain_EXP'] * $killed_troop;
			$kill_reward_honor += $UNIT['obtain_honor'] * $killed_troop;

			$opponent_old_total_command += $troop['qty'] * $UNIT['cost_command'];
			$opponent_new_total_command += $troop['new_qty'] * $UNIT['cost_command'];
		}

		$old_total_command = 0;
		$new_total_command = 0;
		if ( !isset($result['ourforce']['officer']) ) render_error("result.ourforce.officer not found", FCODE(40201));
		if ( !isset($result['ourforce']['troops']) ) render_error("result.ourforce.troops not found", FCODE(40201));

		$total_troops = 0;
		$lost_troops = 0;
		$querys = [];
		$slots = [];
		foreach ($result['ourforce']['troops'] as $troop) {
			$troop_id = $troop['troop_id'];

			if ( !isset($troop['new_qty']) ) render_error("result.ourforce.troops.troop.new_qty not found", FCODE(40201));

			assert_render($troop['new_qty'] >= 0 && $troop['new_qty'] <= $troop['qty'], "MUST: new_qty >= 0 AND new_qty <= qty", FCODE(40202));

			$total_troops += $troop['qty'];
			$lost_troops += ($troop['qty'] - $troop['new_qty']);
			$UNIT = $UNITS[$troop['type_minor']];

			// update all qty, slot for every troop
			$terms = [];
			$terms['qty'] = $troop['new_qty'];

			if ( $troop['new_qty'] == 0 )
				$terms['officer_id'] = "NULL"; // qty = 0 should be disbanded, troop::merge_all_troops_by_type_minor DOES

			// check slot conflictions
			$slot = $troop['slot'];
			if ( isset($troop['new_slot']) ) {
				$terms['slot'] = $troop['new_slot'];
				$slot = $troop['slot'];
			}
			assert_render(1 <= $slot && $slot <= 6, "MUST: 1 <= slot <= 6", FCODE(40203)); // valid slot number
			if ( in_array($slot, $slots) ) // confliction
				render_error("slot conflicts: $slot", FCODE(40203));
			$slots[] = $slot;

			$pair = join_terms($terms);
			$querys[] = "UPDATE troop SET $pair WHERE troop_id = $troop_id";

			$old_total_command += $troop['qty'] * $UNIT['cost_command']; // for rank calculation
			$new_total_command += $troop['new_qty'] * $UNIT['cost_command']; // This also checks officer dead
		}

		$equipments_detail = @$result['ourforce']['officer']['equipments_detail'] ?: [];
		$COMBATITEMS = item::get_combats();
		foreach ($equipments_detail as $detail) {
			if ( !isset($detail['new_qty']) ) {
				elog("no item.detail.new_qty was found, skips");
				continue;
			}
			$item_id = $detail['item_id'];

			if ( $detail['type_major'] != $ITEM_TYPE_MAJOR_COMBATS ) {
				elog("not a combat item, skips");
				continue;
			}

			assert_render($detail['new_qty'] >= 0 && $detail['new_qty'] <= $detail['qty'], "MUST: new_qty >= 0 AND new_qty <= qty", FCODE(40202));

			if ( !($COMBATITEM = @$COMBATITEMS[$detail['type_minor']]) ) {
				render_error("invalid:combat_item:type_minor: " . pretty_json($detail));
			}

			// update all qty
			$terms = [];
			$terms['qty'] = $detail['new_qty'];
			$pairs = join_terms($terms);
			$querys[] = "UPDATE item SET $pairs WHERE item_id = $item_id";
		}

		assert_render($tb->multi_query($querys));

		$win = $new_total_command > 0 && $opponent_new_total_command == 0;
		// draw
		if ( $new_total_command > 0 && $opponent_new_total_command > 0 ) {
			$win = $new_total_command >= $opponent_new_total_command;
			elog("we dont allow draw, breaks tie and, am I win: " . strval($win));
		}

		// draw or lose
		global $COMBAT_REWARD_RATIO_WHEN_LOST;
		if ( !$win ) {
			$kill_reward_gold = intval(floor($COMBAT_REWARD_RATIO_WHEN_LOST*$kill_reward_gold));
			$kill_reward_exp = intval(floor($COMBAT_REWARD_RATIO_WHEN_LOST*$kill_reward_exp));
			$kill_reward_honor = intval(floor($COMBAT_REWARD_RATIO_WHEN_LOST*$kill_reward_honor));
		}

		$pvp_score = 0;
		$pve_score = 0;
		$pve_rank = 'F';

		// as of 11.19 (factor-T was dropped from equation)
		$pvp_score += floatval($old_total_command) * 0.5;
		$pvp_score += (floatval($new_total_command)/floatval($old_total_command)) * 70.0;
		// 		$pvp_score += (1.0-($combat['combat_seconds']/(600.0+$combat['combat_seconds']))) * 30.0; // as of 11.19 (factor-T was dropped from equation)
		$pvp_score = intval(floor($win ? $pvp_score : $pvp_score/2.0));

		if ( $win ) {
			$pve_score = (floatval($new_total_command)/floatval($old_total_command)) * 100.0;
			// as of 11.19 (factor-T was dropped from equation)
			// 			$pve_score += (1.0-($combat['combat_seconds']/(600.0+$combat['combat_seconds']))) * 50.0;

			if ( $pve_score > 90 ) $pve_rank = 'S';
			else if ( $pve_score > 80 ) $pve_rank = 'A';
			else if ( $pve_score > 70 ) $pve_rank = 'B';
			else if ( $pve_score > 60 ) $pve_rank = 'C';
			else if ( $pve_score > 50 ) $pve_rank = 'D';
			else $pve_rank = 'F';

			// for pve
			if ( !($combat['brief']['pvl'] > 0 || $combat['brief']['pvp'] > 0) ) {
				$TILES = tile::get_tiles();
				$TILE = $TILES[$combat['brief']['tile_position']];

				if ( isset($REWARDS[$TILE['pve_gold_reward_group_id']][$pve_rank]) ) {
					$REWARD = $REWARDS[$TILE['pve_gold_reward_group_id']][$pve_rank];
					$clear_reward_gold = mt_rand($REWARD['value_min'], $REWARD['value_max']);
				}
				if ( isset($REWARDS[$TILE['pve_exp_reward_group_id']][$pve_rank]) ) {
					$REWARD = $REWARDS[$TILE['pve_exp_reward_group_id']][$pve_rank];
					$clear_reward_exp = mt_rand($REWARD['value_min'], $REWARD['value_max']);
				}
				if ( isset($REWARDS[$TILE['pve_honor_reward_group_id']][$pve_rank]) ) {
					$REWARD = $REWARDS[$TILE['pve_honor_reward_group_id']][$pve_rank];
					$clear_reward_honor = mt_rand($REWARD['value_min'], $REWARD['value_max']);
				}
			}
		}

		// pvl, pvp will always get rewarded
		if ( $combat['brief']['pvl'] > 0 || $combat['brief']['pvp'] > 0 ) {
			$pve_rank = 'None'; // pvpl has no rank
			$TILES = tile::get_tiles();
			$TILE = $TILES[$combat['brief']['tile_position']];

			if ( isset($REWARDS[$TILE['pvp_gold_reward_group_id']][$pve_rank]) ) {
				$REWARD = $REWARDS[$TILE['pvp_gold_reward_group_id']][$pve_rank];
				$clear_reward_gold = mt_rand($REWARD['value_min'], $REWARD['value_max']);
			}
			if ( isset($REWARDS[$TILE['pvp_exp_reward_group_id']][$pve_rank]) ) {
				$REWARD = $REWARDS[$TILE['pvp_exp_reward_group_id']][$pve_rank];
				$clear_reward_exp = mt_rand($REWARD['value_min'], $REWARD['value_max']);
			}
			if ( isset($REWARDS[$TILE['pvp_honor_reward_group_id']][$pve_rank]) ) {
				$REWARD = $REWARDS[$TILE['pvp_honor_reward_group_id']][$pve_rank];
				$clear_reward_honor = mt_rand($REWARD['value_min'], $REWARD['value_max']);
			}
		}

		elog("rank [$pve_rank](score: $pve_score, pvp_score: $pvp_score) with clear_rewards {gold: $clear_reward_gold, exp: $clear_reward_exp, honor: $clear_reward_honor}");

		$exp_mod = $kill_reward_exp + $clear_reward_exp;

		global $COMBAT_REWARD_EXP_OFFICER_RATIO_TO_GENERAL;
		$officer_exp_mod = intval(floor($COMBAT_REWARD_EXP_OFFICER_RATIO_TO_GENERAL*$exp_mod));
		$officer_exp_mod = general::apply_effects($tb, 105, $officer_exp_mod);

		$gold_mod = general::apply_effects($tb, 103, $kill_reward_gold + $clear_reward_gold);
		$honor_mod = general::apply_effects($tb, 103, $kill_reward_honor + $clear_reward_honor);
		$honor_mod = general::apply_effects($tb, 155, $honor_mod); // legion occupy effect

		elog("rewards[kill, clear, mod]: gold: [$kill_reward_gold, $clear_reward_gold, $gold_mod], honor: [$kill_reward_honor, $clear_reward_honor, $honor_mod]");
		elog("rewards[kill, clear, mod]: exp: [$kill_reward_exp, $clear_reward_exp, $exp_mod], officer_exp_mod: $officer_exp_mod");

		// check that levelup is possible by calculations
		$general_will_levelup = false;
		$officer_will_levelup = false;
		if ($combat['brief']['ourforce']['general']['exp_cur']+$exp_mod >= $combat['brief']['ourforce']['general']['exp_max'])
			$general_will_levelup = true;

		$officer_id = $result['ourforce']['officer']['officer_id'];

		// UPDATE officer - command_cur, exp_cur, status_changed_at(for DEAD), status(for DEAD)
		$terms = [];
		$terms['command_cur'] = $new_total_command;
		$terms['exp_cur'] = "exp_cur + $officer_exp_mod";

		if ( $new_total_command == 0 ) { // officer dead!
			$terms['status'] = $OFFICER_DEAD;
		}

		if ($combat['brief']['ourforce']['officer']['exp_cur']+$officer_exp_mod >= $combat['brief']['ourforce']['officer']['exp_max']) {
			$GRADES = officer::get_grades();
			$GRADE = $GRADES[$combat['brief']['ourforce']['officer']['grade']];
			if ( $combat['brief']['ourforce']['officer']['level'] < $GRADE['max_level'] )
				$officer_will_levelup = true;
			else {
				elog(sprintf("officer reached at max level[%d] of grade [%d]", $GRADE['max_level'], $GRADE['grade']));
			}
		}

		$pairs = join_terms($terms);
		$query = "UPDATE officer SET $pairs WHERE officer_id = $officer_id";
		assert_render($tb->query_with_affected($query, 1));

		troop::merge_all_troops_by_type_minor($tb);

		elog("lost_troops($lost_troops)/total_troops($total_troops), exp_mod:$exp_mod, officer_exp_mod:$officer_exp_mod");
		// done

		// build (reward) summary
		$summary = [];
		$summary['opponent_old_total_command'] = $opponent_old_total_command;
		$summary['opponent_new_total_command'] = $opponent_new_total_command;
		$summary['old_total_command'] = $old_total_command;
		$summary['new_total_command'] = $new_total_command;
		$summary['clear_reward_gold'] = $clear_reward_gold;
		$summary['clear_reward_exp'] = $clear_reward_exp;
		$summary['clear_reward_honor'] = $clear_reward_honor;
		$summary['kill_reward_gold'] = $kill_reward_gold;
		$summary['kill_reward_exp'] = $kill_reward_exp;
		$summary['kill_reward_honor'] = $kill_reward_honor;
		$summary['lost_troops'] = $lost_troops;
		$summary['total_troops'] = $total_troops;
		$summary['exp_mod'] = $exp_mod;
		$summary['officer_exp_mod'] = $officer_exp_mod;
		$summary['gold_mod'] = $gold_mod;
		$summary['honor_mod'] = $honor_mod;
		$summary['pvp_score'] = $pvp_score;
		$summary['pve_score'] = $pve_score;
		$summary['pve_rank'] = $pve_rank;
		$summary['combat_seconds'] = $combat['combat_seconds'];

		// UPDATE tile
		$position = $combat['brief']['tile_position'];

		$score_mod_force = $pvp_score;
		$score_mod_legion = 1;

		$new_occupy_score_allies = $old_occupy_score_allies = $combat['brief']['tile']['occupy_score_allies'];
		$new_occupy_score_empire = $old_occupy_score_empire = $combat['brief']['tile']['occupy_score_empire'];

		$terms = [];
		if ( ($combat['brief']['pvl'] > 0 || $combat['brief']['pvp'] > 0) ) {
			if ( $combat['brief']['ourforce']['general']['country'] == ALLIES ) {
				if ( $win ) {
					$terms['occupy_win_allies'] = 'occupy_win_allies + 1';
					$terms['occupy_score_allies'] = "occupy_score_allies + $score_mod_force";
					$new_occupy_score_allies += $score_mod_force;
				}
				$terms['occupy_count_allies'] = 'occupy_count_allies + 1';
			} else {
				if ( $win ) {
					$terms['occupy_win_empire'] = 'occupy_win_empire + 1';
					$terms['occupy_score_empire'] = "occupy_score_empire + $score_mod_force";
					$new_occupy_score_empire += $score_mod_force;
				}
				$terms['occupy_count_empire'] = 'occupy_count_empire + 1';
			}
		}

		if ( sizeof($terms) > 0 ) {
			$pairs = join_terms($terms);
			$query = "UPDATE tile SET $pairs WHERE position = '$position'";
			assert_render($tb->query_with_affected($query, 1));
		}

		$summary['old_occupy_score_allies'] = $old_occupy_score_allies;
		$summary['new_occupy_score_allies'] = $new_occupy_score_allies;
		$summary['old_occupy_score_empire'] = $old_occupy_score_empire;
		$summary['new_occupy_score_empire'] = $new_occupy_score_empire;

		// UPDATE general
		$terms = [];

		// for daliy quests
		if ( $combat['brief']['pvp'] > 0 || $combat['brief']['pvl'] > 0 ) {
			if ( $win )
				$terms['pvp_combat_win'] = "pvp_combat_win + 1";
			if ( $pve_rank == $COMBAT_TOP_RANK )
				$terms['pvp_combat_top_rank_count'] = "pvp_combat_top_rank_count + 1";

			if ( $combat['brief']['pvl'] > 0 )
				$terms['pvl_combat_count'] = "pvl_combat_count + 1";
			else
				$terms['pvp_combat_count'] = "pvp_combat_count + 1";
		} else {
			if ( $win )
				$terms['pve_combat_win'] = "pve_combat_win + 1";
			if ( $pve_rank == $COMBAT_TOP_RANK )
				$terms['pve_combat_top_rank_count'] = "pve_combat_top_rank_count + 1";

			$terms['pve_combat_count'] = "pve_combat_count + 1";
		}

		$terms['exp_cur'] = "exp_cur + $exp_mod";
		$terms['gold'] = "gold + $gold_mod";
		$terms['honor'] = "honor + $honor_mod";
		$terms['running_combat_id'] = 'NULL';
		$terms['pop_cur'] = "(SELECT SUM(qty) FROM troop WHERE general_id = $general_id)"; // recalculate population
		if ( $new_total_command == 0 )
			$terms['leading_officer_id'] = "NULL"; // unlead on dead
			
		$pairs = join_terms($terms);
		$query = "UPDATE general SET $pairs WHERE general_id = $general_id";
		assert_render($tb->query_with_affected($query, 1));

		if ( $new_total_command == 0 ) {
			officer::army_dismiss($tb);
		}

		$new_general_level = $old_general_level = $combat['brief']['ourforce']['general']['level'];
		$old_general_exp_cur = $combat['brief']['ourforce']['general']['exp_cur'];
		$new_general_exp_max = $old_general_exp_max = $combat['brief']['ourforce']['general']['exp_max'];

		$new_officer_level = $old_officer_level = $combat['brief']['ourforce']['officer']['level'];
		$old_officer_exp_cur = $combat['brief']['ourforce']['officer']['exp_cur'];
		$new_officer_exp_max = $old_officer_exp_max = $combat['brief']['ourforce']['officer']['exp_max'];

		elog("total_command: $old_total_command => $new_total_command, general_will_levelup: [$general_will_levelup], officer_will_levelup: [$officer_will_levelup]");
		if ( $general_will_levelup )
			$new_general_level = max($old_general_level, general::check_general_levelup($tb, $old_general_level));
		if ( $officer_will_levelup )
			$new_officer_level = max($old_officer_level, officer::check_officer_levelup($tb, $officer_id));

		$summary['old_general_level'] = $old_general_level;
		$summary['new_general_level'] = $new_general_level;
		$summary['old_general_exp_cur'] = $old_general_exp_cur;

		$summary['old_officer_level'] = $old_officer_level;
		$summary['new_officer_level'] = $new_officer_level;
		$summary['old_officer_exp_cur'] = $old_officer_exp_cur;

		// UPDATE combat
		$terms = [];
		$terms['summary'] = ms_quote($tb->escape(pretty_json($summary)));
		$terms['result'] = ms_quote($tb->escape(pretty_json($result)));
		$terms['completed_at'] = "NOW()";
		$terms['status'] = $COMBAT_COMPLETED;

		$pairs = join_terms($terms);
		$query = "UPDATE combat SET $pairs WHERE combat_id = $combat_id";
		assert_render($tb->query_with_affected($query, 1));

		// report scores for rankings
		if ( $score_mod_force > 0 || $score_mod_legion > 0 ) {
			elog("report scores for rankings: {force: $score_mod_force, legion: $score_mod_legion}");

			$redis = conn_redis();

			$force_rank_key = "$TAG:battlefield:tile:ranking:$position";
			$force_info_key = "$TAG:battlefield:tile:info";
			$legion_rank_key = "$TAG:battlefield:tile:legion:ranking:$position";
			$legion_info_key = "$TAG:battlefield:tile:legion:info"; // TODO: legion, care me

			$force_info = [];
			$force_info['user_id'] = $user_id;
			$force_info['username'] = session_GET('username');
			$force_info['level'] = $new_general_level;
			// 			$force_info['legion_id'] = session_GET('username');
			// 			$force_info['legion_name'] = session_GET('username');

			$redis->zIncrBy($force_rank_key, $score_mod_force, $user_id); // score for user
			$redis->hSet($force_info_key, $user_id, pretty_json($force_info));

			if ( $combat['brief']['pvl'] > 0 ) { // score for legion
				$redis->zIncrBy($legion_rank_key, $score_mod_legion, $combat['brief']['ourforce']['general']['legion_joined_id']);
			}

			// update top ranking cache for tile
			$force_top_ranks = $redis->zRevRangeByScore($force_rank_key, '+inf', '(0', array('withscores' => TRUE, 'limit' => array(0, 1)));
			if ( $force_top_ranks && sizeof($force_top_ranks) == 1 ) {
				foreach ($force_top_ranks as $rank_user_id => $rank_score) {
					$force_info = $redis->hGet("$TAG:battlefield:tile:info", $rank_user_id);
					elog("force_info " . pretty_json($force_info));
					if ( $force_info && ($rank_info = @json_decode($force_info, true)) ) {
						$rank_info['rank'] = 1;
						$rank_info['score'] = $rank_score;
						$redis->hSet("$TAG:battlefield:tile:top_ranks", $position, pretty_json($rank_info));
					}
					break;
				}
			}

			// get force, legion rank
			$legion_id = 0;
			// 			$force_rank = 0;
			// 			$legion_rank = 0;
			// 			if ( ($force_rank = $redis->zRevRank($force_rank_key, $user_id)) !== null )
			// 				$force_rank += 1;
			// 			if ( ($legion_rank = $redis->zRevRank($legion_rank_key, $legion_id)) !== null )
			// 				$legion_rank += 1;
				
			$battlefield_id = $combat['brief']['tile']['battlefield_id'];
				
			$querys = [];
			$querys[] = "INSERT INTO tile_scores_general (battlefield_id, general_id, tile_name, score) "
					."VALUES ($battlefield_id, $general_id, '$position', $score_mod_force) "
					."ON DUPLICATE KEY UPDATE score = score + $score_mod_force";

			if (0) {  // TODO: legion will cover this
				$querys[] = "INSERT INTO tile_scores_legion (battlefield_id, legion_id, tile_name, score) "
						."VALUES ($battlefield_id, $legion_id, '$position', $score_mod_legion) "
						."ON DUPLICATE KEY UPDATE score = score + $score_mod_legion";
			}

			assert_render($tb->multi_query($querys));
		}

		quest::resolve_quests($tb); // resolve all

		$combats = combat::select_all($tb, null, "combat_id = $combat_id");

		gamelog(__METHOD__, ['combat' => $combats[0]]);

		assert_render($tb->end_txn());

		$map['combats'] = $combats;

		render_ok('success', $map);
	}
}

if ( strstr($_SERVER["REQUEST_URI"], basename(__FILE__)) ) {

	$ops = explode(',', $op = queryparam_fetch('op', 'get', 'begin', 'submit'));

	$user_id = login_check();
	$general_id = session_GET('general_id');
	$status = queryparam_fetch_int('status');

	if ( sizeof(array_intersect_key(['get', 'clear', 'begin', 'submit'], $ops)) ) {
		for ( $retried = 1 ; $retried <= $TXN_RETRY_MAX ; $retried++ ) {
			try {
				$tb = new TxnBlock();

				if ( in_array("clear", $ops) ) combat::clear($tb);

				if ( in_array("begin", $ops) ) combat::begin($tb);
				else if ( in_array("submit", $ops) ) combat::submit($tb);

				combat::get($tb); // embedes end_txn()
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
