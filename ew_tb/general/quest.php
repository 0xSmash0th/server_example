<?php
require_once '../connect.php';
require_once '../general/general.php';
require_once '../army/officer.php';

class quest {

	public static function get_quests() {
		if ( $val = fetch_from_cache('constants:quests') )
			return $val;

		$timer_bgn = microtime(true);

		$quest_info = loadxml_as_dom('xml/quest_info.xml');
		if ( !$quest_info )
			return null;

		$quests = [];
		$quest_ids_basic = [];
		$quest_ids_daily = [];

		foreach ($quest_info->xpath("//quests/quest") as $node ) {
			$attrs = (array)$node->attributes();
			$pattrs = $attrs['@attributes'];

			if ( !empty($pattrs['weight']) )
				$pattrs['weight'] = trim(str_replace("%", "", $pattrs['weight']));

			$quests[$pattrs["quest_id"]] = $pattrs;

			if ( isset($pattrs['category']) && $pattrs['category'] == 2 ) {
				$quest_ids_daily[] = $pattrs["quest_id"];
			} else if ( isset($pattrs['req_quest_id']) && $pattrs['req_quest_id'] < 0 && $pattrs['category'] == 1 )
				$quest_ids_basic[] = $pattrs["quest_id"];

		}

		$quests['quest_ids_basic'] = $quest_ids_basic;
		$quests['quest_ids_daily'] = $quest_ids_daily;

		$timer_end = microtime(true);
		elog("time took quest for quest::get_quests(): " . ($timer_end - $timer_bgn));

		store_into_cache('constants:quests', $quests);

		$bnis = array_keys($quests);
		elog("quest_ids: " . json_encode($bnis));
		elog("quest_ids_basic: " . json_encode($quests['quest_ids_basic']));
		elog("quest_ids_daily: " . json_encode($quests['quest_ids_daily']));

		return $quests;
	}

	private static function refresh_daily_quests($tb, $quest_list, $force_reset = false) {
		$QUESTS = quest::get_quests();

		$now = time();

		// generate daily quests
		$daily_utc_max = 0;
		foreach (['accepted', 'completed', 'rewarded'] as $key) {
			foreach ( $quest_list[$key] as $qid => $utc ) {
				if ( in_array($qid, $QUESTS['quest_ids_daily']) ) {
					$daily_utc_max = max($daily_utc_max, $utc);
				}
			}
		}

		global $QUEST_DAILY_REFRESH_HOUR, $QUEST_DAILY_REFRESH_MINUTE, $QUEST_DAILY_COUNT;

		$daily_was_created_at = new DateTime();
		$daily_was_created_at->setTimestamp($daily_utc_max);
		$daily_was_created_at->setTime($QUEST_DAILY_REFRESH_HOUR, $QUEST_DAILY_REFRESH_MINUTE);
		$daily_was_created_at_utc = $daily_was_created_at->getTimestamp();

		if ( $now >= ($daily_was_created_at_utc + 24*60*60) || $force_reset ) {
			$dt = $daily_was_created_at->format(DateTime::ATOM);
			elog("generates $QUEST_DAILY_COUNT random daily quests [now: $now, daily_was_created_at_utc($dt): $daily_was_created_at_utc, force_reset:$force_reset]...");

			// drop previous daily quests
			foreach (['accepted', 'completed', 'rewarded'] as $key) {
				$quest_list["new_$key"]['_'] = '_';

				foreach ( $quest_list[$key] as $qid => $utc ) {
					if ( !in_array($qid, $QUESTS['quest_ids_daily']) )
						$quest_list["new_$key"][$qid] = $utc;
				}
			}

			// generate $QUEST_DAILY_COUNT random daily quests
			$daily_qids = [];
			for ( $i = 0 ; $i < $QUEST_DAILY_COUNT ; $i++ ) {
				$keys = [];
				$weights = [];
				foreach ($QUESTS['quest_ids_daily'] as $key) {
					if ( !in_array($key, $daily_qids)) {
						$keys[] = $key;
						$weights[] = $QUESTS[$key]['weight'];
					}
				}

				$choiced_pid = weighted_choice($keys, $weights);
				$keys = array_values(array_diff($keys, [$choiced_pid]));

				$daily_qids[] = $choiced_pid;
				elog("quest_id $choiced_pid was choiced, candidate keys: " . pretty_json($keys));
			}

			foreach (['accepted', 'completed', 'rewarded'] as $key) {
				$quest_list[$key] = $quest_list["new_$key"];
				unset($quest_list["new_$key"]);
			}

			// find nearest daily-quest refresh time
			$daily_willbe_created_at = new DateTime();
			$daily_willbe_created_at->setTimestamp($now);
			$daily_willbe_created_at->setTime($QUEST_DAILY_REFRESH_HOUR, $QUEST_DAILY_REFRESH_MINUTE);
			if ( $daily_willbe_created_at->getTimestamp() >= $now )
				$daily_willbe_created_at->sub(new DateInterval('P1D'));
			$daily_willbe_created_at_utc = $daily_willbe_created_at->getTimestamp();

			$dt = $daily_willbe_created_at->format(DateTime::ATOM);
			elog("daily_qids($dt, $daily_willbe_created_at_utc): " . pretty_json($daily_qids));

			foreach ($daily_qids as $qid)
				$quest_list['accepted'][$qid] = $daily_willbe_created_at_utc;

			$quest_list['refreshed'] = true;
		}
		return $quest_list;
	}

	/**
	 * Check that do we need default quests
	 * @param unknown $tb
	 * @param unknown $general_id
	 * @param unknown $country
	 * @param string $count_before
	 */
	public static function default_quests($tb, $general_id) {

		$QUESTS = quest::get_quests();

		$general = general::select($tb, 'quest_list');

		$quest_list = $general['quest_list'];

		foreach (['accepted', 'completed', 'rewarded'] as $key) {
			if ( !isset($quest_list[$key]) )
				$quest_list[$key] = ['_'=>'_'];
		}

		$now = time();
		foreach ( $QUESTS['quest_ids_basic'] as $qid ) {
			if ( !isset($quest_list['completed'][$qid]) && !isset($quest_list['rewarded'][$qid])
			&& !isset($quest_list['accepted'][$qid]) )
				$quest_list['accepted'][$qid] = $now;
		}

		$quest_list = quest::refresh_daily_quests($tb, $quest_list);

		$js = pretty_json($quest_list, JSON_FORCE_OBJECT);
		$ejs = $tb->escape($js);

		$query = "UPDATE general SET quest_list = '$ejs' WHERE general_id = $general_id";
		assert_render($tb->query($query));
	}

	public static function select_all($tb, $select_expr = null, $where_condition = null) {
		global $user_id, $general_id;

		if ( $select_expr == null )
			$select_expr = "*";
		if ( $where_condition == null )
			$where_condition = "WHERE general_id = $general_id";
		else
			$where_condition = "WHERE general_id = $general_id AND ($where_condition)";

		$query = "SELECT $select_expr FROM quest $where_condition /*BY_HELPER*/";
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
		$rows = quest::select_all($tb, $select_expr, $where_condition);
		if ( $rows && count($rows) == 1 )
			return $rows[0];
		return null;
	}

	public static function resolve_quests($tb, $check_types = null, $reset_daily_quests = false) {
		global $user_id, $general_id;

		$with_tb = $tb;

		if ( !$with_tb )
			$tb = new TxnBlock();

		$check_quest = true;
		$daily_refreshed = false;

		while ( $check_quest ) {
			$check_quest = false;
			$completed_quest_ids = null;
			$terms = [];

			$general = general::select($tb, 'country, level, gold, honor, officer_hired_level_max, '
					.'pvp_combat_count, pve_combat_count, pvl_combat_count, '
					.'pve_combat_win, pvp_combat_win, pve_combat_top_rank_count, pvp_combat_top_rank_count, '
					.'building_list, quest_list');
			if ( !isset($general['quest_list']) )
				break;

			$quest_list = $general['quest_list'];
			if ( isset($quest_list['refreshed']) )
				unset($quest_list['refreshed']);

			if ( isset($quest_list['accepted']) && sizeof($quest_list['accepted']) > 0 ) {
				$QUESTS = quest::get_quests();

				foreach ($quest_list['accepted'] as $quest_id => $utc ) {
					if ( !isset($QUESTS[$quest_id]) ) continue;

					$QUEST = $QUESTS[$quest_id];

					if ( $check_types && sizeof($check_types) > 0 && !in_array($QUEST['quest_type'], $check_types) )
						continue; // skip if current quest_type is not in check_types

					$qty = 0;
					if (0);
					else if ( $QUEST['quest_type'] == 'Building_HQ' ) {
						$qty = isset($general['building_list']['hq_level']) ? $general['building_list']['hq_level'] : 0;
					}
					else if ( $QUEST['quest_type'] == 'Building_Training' ) {
						global $BLD_TRAINING_ID_ALLIES, $BLD_TRAINING_ID_EMPIRE;

						$building_id = null;
						if ( $general['country'] == 1 ) $building_id = $BLD_TRAINING_ID_ALLIES;
						if ( $general['country'] == 2 ) $building_id = $BLD_TRAINING_ID_EMPIRE;

						if ( $building_id && isset($general['building_list']['non_hq'][$building_id]) ) {
							$training_list = $general['building_list']['non_hq'][$building_id];
							if ( !empty($training_list) ) {
								foreach ($training_list as $cid => $level) {
									$qty = max($qty, $level);
								}
							}
						}
					}
					else if ( $QUEST['quest_type'] == 'Building_MachineFactory' ) {
						global $BLD_MACHINE_FACTORY_ID_ALLIES, $BLD_MACHINE_FACTORY_ID_EMPIRE;

						$building_id = null;
						if ( $general['country'] == 1 ) $building_id = $BLD_MACHINE_FACTORY_ID_ALLIES;
						if ( $general['country'] == 2 ) $building_id = $BLD_MACHINE_FACTORY_ID_EMPIRE;

						if ( $building_id && isset($general['building_list']['non_hq'][$building_id]) ) {
							$training_list = $general['building_list']['non_hq'][$building_id];
							if ( !empty($training_list) ) {
								foreach ($training_list as $cid => $level) {
									$qty = max($qty, $level);
								}
							}
						}
					}
					else if ( $QUEST['quest_type'] == 'General_Level' ) {
						$qty = isset($general['level']) ? $general['level'] : 0;
					}
					else if ( $QUEST['quest_type'] == 'Officer_Level' ) {
						$qty = isset($general['officer_hired_level_max']) ? $general['officer_hired_level_max'] : 0;
					}
					else if ( $QUEST['quest_type'] == 'Gold_Have' ) {
						$qty = isset($general['gold']) ? $general['gold'] : 0;
					}
					else if ( $QUEST['quest_type'] == 'HonorPoint_Have' ) {
						$qty = isset($general['honor']) ? $general['honor'] : 0;
					}
					else if ( $QUEST['quest_type'] == 'AnnihilationWar_Play' ) {
						$qty = isset($general['pve_combat_count']) ? $general['pve_combat_count'] : 0;
					}
					else if ( $QUEST['quest_type'] == 'OccupyWar_Play' ) {
						$qty = isset($general['pvp_combat_count']) ? $general['pvp_combat_count'] : 0;
					}
					else if ( $QUEST['quest_type'] == 'CorpsWar_play' ) {
						$qty = isset($general['pvl_combat_count']) ? $general['pvl_combat_count'] : 0;
					}
					// daily quests
					else if ( $QUEST['quest_type'] == 'AnnihilationWar_Victory' ) {
						$qty = isset($general['pve_combat_win']) ? $general['pve_combat_win'] : 0;
					}
					else if ( $QUEST['quest_type'] == 'OccupyWar_Victory' ) {
						$qty = isset($general['pvp_combat_win']) ? $general['pvp_combat_win'] : 0;
					}
					else if ( $QUEST['quest_type'] == 'AnnihilationWar_Rank' ) {
						$qty = isset($general['pve_combat_top_rank_count']) ? $general['pve_combat_top_rank_count'] : 0;
					}
					else if ( $QUEST['quest_type'] == 'OccupyWar_Rank' ) {
						$qty = isset($general['pvp_combat_top_rank_count']) ? $general['pvp_combat_top_rank_count'] : 0;
					}

					// check completion
					$terms = [];
					if ( $qty >= $QUEST['complete_qty'] ) {
						elog(sprintf("quest[$quest_id, %s]: is about to complete, $qty >= %s", $QUEST['quest_type'], $QUEST['complete_qty']));

						$completed_quest_ids[] = $quest_id;
					}
				}
			}

			$quest_list_updated = false;

			// adjust quest_list for main/daily quest
			if ( $completed_quest_ids && sizeof($completed_quest_ids) > 0 ) {
				$new_accepted = [];
				$old_accepted = $quest_list['accepted'];

				foreach ( $old_accepted as $qid => $val ) {
					if ( !in_array($qid, $completed_quest_ids) )
						$new_accepted[$qid] = $val;
				}

				foreach ( $QUESTS as $qid => $QUEST ) { // unlock depending quests
					if ( isset($QUEST['req_quest_id']) && in_array($QUEST['req_quest_id'], $completed_quest_ids) ) {
						elog("completed_quest " . $QUEST['req_quest_id'] . " unlocked $qid");
						$new_accepted[$qid] = time();
					}
				}

				$quest_list['accepted'] = $new_accepted;

				foreach ( $completed_quest_ids as $qid )
					$quest_list['completed'][$qid] = $old_accepted[$qid];

				$quest_list_updated = true;
			} else {
				$quest_list = quest::refresh_daily_quests($tb, $quest_list, $reset_daily_quests);
				$quest_list_updated = !empty($quest_list['refreshed']);
				$daily_refreshed = true;

				if ( isset($quest_list['refreshed']) )
					unset($quest_list['refreshed']);

				if ( $quest_list_updated ) {
					$terms['pve_combat_win'] = 0;
					$terms['pvp_combat_win'] = 0;
					$terms['pve_combat_top_rank_count'] = 0;
					$terms['pvp_combat_top_rank_count'] = 0;
				}
			}

			if ( $quest_list_updated )
				$terms['quest_list'] =  ms_quote($tb->escape(pretty_json($quest_list)));

			if ( sizeof($terms) > 0 ) {
				$pairs = join_terms($terms);
				$query = "UPDATE general SET $pairs WHERE general_id = $general_id";
				assert_render($tb->query($query));

				if ( !($reset_daily_quests || $daily_refreshed) )
					$check_quest = true;
			}
		}

		if ( !$with_tb )
			assert_render($tb->end_txn());
	}

	public static function clear($tb) {
		global $TAG, $ALLIES, $EMPIRE, $user_id, $general_id, $dev_type, $dev_uuid, $ops, $status, $country;

		if ( dev ) {
			$query = "UPDATE general SET quest_list = NULL WHERE general_id = $general_id;";
			assert_render($rs = $tb->query($query));

			$query = "DELETE FROM quest WHERE general_id = $general_id;";
			assert_render($rs = $tb->query($query));

			quest::default_quests($tb, $general_id);
		}
	}

	public static function get($tb) {
		global $TAG, $ALLIES, $EMPIRE, $user_id, $general_id, $dev_type, $dev_uuid, $ops, $status, $country;

		$general = general::select($tb, 'quest_list');

		$map['quest_list'] = [];

		$general['quest_list']['non_rewarded'] = [];

		foreach ($general['quest_list']['completed'] as $qid => $utc) {
			if ( !array_key_exists($qid, $general['quest_list']['rewarded']) )
				$general['quest_list']['non_rewarded'][$qid] = $utc;
		}

		// sort by quest_id
		foreach (['completed', 'rewarded', 'accepted', 'non_rewarded'] as $qkey ) {
			$map['quest_list'][$qkey] = [];

			$keys = array_keys($general['quest_list'][$qkey]);
			sort($keys);

			foreach ($keys as $key) {
				if ( is_numeric($key) )
					$map['quest_list'][$qkey][] = ['quest_id' => $key, 'accpeted_at_utc' => $general['quest_list'][$qkey][$key]];
			}
		}

		assert_render($tb->end_txn());

		render_ok('success', $map);
	}

	public static function reward($tb) {
		global $TAG, $ALLIES, $EMPIRE, $user_id, $general_id, $dev_type, $dev_uuid, $ops, $status, $country;

		$quest_id = queryparam_fetch_int('quest_id');
		assert_render($quest_id >= 0, "invalid:quest_id:$quest_id");

		$QUESTS = quest::get_quests();
		assert_render(isset($QUESTS[$quest_id]), "invalid:quest_id:$quest_id");
		$QUEST = $QUESTS[$quest_id];

		$general = general::select($tb, 'gold, gold_max, quest_list, badge_list');
		if ( !isset($general['quest_list']['completed'][$quest_id]) )
			render_error("no such quest_id:$quest_id is completed", FCODE(21101));
		if ( isset($general['quest_list']['rewarded'][$quest_id]) )
			render_error("already rewarded quest_id:$quest_id", FCODE(21102));

		$terms = [];

		if ( $QUEST['reward_type'] == 'Gold' ) {
			$mod_gold = $QUEST['reward_qty'];
			$terms['gold'] = "gold + $mod_gold";

			elog("gold was rewarded for quest: $quest_id: qty: $mod_gold");
		}
		else if ( $QUEST['reward_type'] == 'honor' ) {
			$mod = $QUEST['reward_qty'];
			$terms['honor'] = "honor + $mod";

			elog("honor was rewarded for quest: $quest_id: qty: $mod");
		}
		else if ( $QUEST['reward_type'] == 'Badge' ) {
			$badge_id = $QUEST['reward_id'];
			$BADGES = general::get_badges();
			if ( isset($BADGES[$badge_id]) && !isset($general['badge_list'][$badge_id]) ) {
				$level = $QUEST['reward_qty'];
				$general['badge_list'][$badge_id] = ['level' => $level];
				$terms['badge_list'] = ms_quote($tb->escape(pretty_json($general['badge_list'])));

				elog("badge was rewarded for quest: $quest_id: badge_id: $badge_id, qty(level): $level");
			}
		} else
			elog("no reward is defined for this reward_type: " . $QUEST['reward_type']);

		$quest_list = $general['quest_list'];
		if ( !isset($quest_list['rewarded']) )
			$quest_list['rewarded'] = [];
		$quest_list['rewarded'][$quest_id] = $quest_id;

		$terms['quest_list'] =  ms_quote($tb->escape(pretty_json($quest_list)));

		if ( sizeof($terms) > 0 ) {
			$pairs = join_terms($terms);
			$query = "UPDATE general SET $pairs WHERE general_id = $general_id";
			assert_render($tb->query($query));
		}

		$general = general::select($tb);

		gamelog(__METHOD__, ['quest_id'=>$quest_id]);
		
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

	$reset_daily_quest = false;

	if ( sizeof(array_intersect_key(['get', 'clear', 'reward', 'reset_daily_quest'], $ops)) ) {
		for ( $retried = 1 ; $retried <= $TXN_RETRY_MAX ; $retried++ ) {
			try {
				$tb = new TxnBlock();

				if ( in_array("clear", $ops) ) quest::clear($tb);

				quest::default_quests($tb, $general_id);

				$reset_daily_quest = dev && in_array('reset_daily_quest', $ops);

				quest::resolve_quests($tb, null, $reset_daily_quest);

				if ( in_array("reward", $ops) ) quest::reward($tb);

				quest::get($tb); // embedes end_txn()
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
