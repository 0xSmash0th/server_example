<?php
require_once '../connect.php';

class tile {

	public static function get_npc_groups() {
		if ( $val = fetch_from_cache('constants:npc_groups') )
			return $val;

		$timer_bgn = microtime(true);

		$npc_groupinfo = loadxml_as_dom('xml/battlefield_npc_info.xml');
		if ( !$npc_groupinfo ) {
			elog("failed to loadxml: " . 'xml/battlefield_npc_info.xml');
			return null;
		}

		$npc_groups = array();

		foreach ($npc_groupinfo->xpath("//npc_group") as $node ) {
			$attrs = (array)$node->attributes();
			$pattrs = $attrs['@attributes'];

			$group_id = $pattrs["group_id"];

			$newattrs = array();
			foreach ( $pattrs as $key => $val ) {
				if ( strpos($key, "cl_") === 0 ) continue; // skip client-side effective related keys

				$newattrs[$key] = $val;
			}
			$npc_groups[$group_id] = $newattrs;

			$npcs = array();
			foreach($node->xpath("npc") as $npc ) {
				$npc_attrs = (array)$npc->attributes();
				$npc_pattrs = $npc_attrs['@attributes'];

				$npc_id = $npc_pattrs['npc_id'];
				$npcs[$npc_id] = $npc_pattrs;

				$slots = array();
				foreach($npc->xpath("slots/slot") as $slot ) {
					$slot_attrs = (array)$slot->attributes();
					$slot_pattrs = $slot_attrs['@attributes'];

					$slots[$slot_pattrs['slot_idx']] = $slot_pattrs;
				}
				// 				elog(sprintf("setting [%2d] slots for npc_id: [%s]", count($slots), $npc_id));

				$npcs[$npc_id]['slots'] = $slots;
			}
			// 			elog(sprintf("setting [%2d] npcs for group_id [%s]", count($npcs), $group_id));

			$npc_groups[$group_id]['npcs'] = $npcs;
		}

		$bnis = array_keys($npc_groups);

		$timer_end = microtime(true);
		elog("time took for npc_group::get_npc_groups(): " . ($timer_end - $timer_bgn));

		store_into_cache('constants:npc_groups', $npc_groups);

		elog("npc_group npc_groups keys: " . json_encode($bnis));

		return $npc_groups;
	}

	public static function get_tiles() {
		if ( $val = fetch_from_cache('constants:tiles') )
			return $val;

		$timer_bgn = microtime(true);

		$tileinfo = loadxml_as_dom('xml/battlefield_info.xml');
		if ( !$tileinfo ) {
			elog("failed to loadxml: " . 'xml/battlefield_info.xml');
			return null;
		}

		$tiles = [];
		$tiles_hq_allies = null;
		$tiles_hq_empire = null;
		$tiles_safezone_allies = [];
		$tiles_safezone_empire = [];
		$tiles_forts = [];
		$tiles_normal = [];
		$tiles_allies = [];
		$tiles_empire = [];

		foreach ($tileinfo->xpath("//tile") as $node ) {
			$attrs = (array)$node->attributes();
			$pattrs = $attrs['@attributes'];

			$ukey = $pattrs["tile_name"];

			$newattrs = array();
			foreach ( $pattrs as $key => $val ) {
				// skip client-side effective related keys
				if ( strpos($key, "cl_") === 0 || strpos($key, "ef") === 0 || strpos($key, "spr") === 0 || strpos($key, "sound") === 0)
					continue;
				if ( strpos($key, "desc_") === 0 )
					continue;

				$ignores = ['combat_status', 'supply_status', 'occupy_legion_id', 'occupy_point_allies', 'occupy_point_empire'];
				if ( in_array($key, $ignores) )
					continue;

				$newattrs[$key] = $val;
			}
			$tiles[$ukey] = $newattrs;

			if ( $pattrs['tile_attr'] == '1' && ($pattrs['tile_init_occupy'] == 1 || $pattrs['tile_init_occupy'] == 3) ) $tiles_hq_allies = $ukey;
			if ( $pattrs['tile_attr'] == '1' && ($pattrs['tile_init_occupy'] == 2 || $pattrs['tile_init_occupy'] == 4) ) $tiles_hq_empire = $ukey;
			if ( $pattrs['tile_attr'] == '2' && ($pattrs['tile_init_occupy'] == 3) ) $tiles_safezone_allies[] = $ukey;
			if ( $pattrs['tile_attr'] == '2' && ($pattrs['tile_init_occupy'] == 4) ) $tiles_safezone_empire[] = $ukey;
			if ( $pattrs['tile_attr'] == '3' ) $tiles_forts[] = $ukey;
			if ( $pattrs['tile_attr'] == '4' ) $tiles_normal[] = $ukey;
			if ( $pattrs['tile_attr'] == '4' && ($pattrs['tile_init_occupy'] == 1 || $pattrs['tile_init_occupy'] == 3) ) $tiles_allies[] = $ukey;
			if ( $pattrs['tile_attr'] == '4' && ($pattrs['tile_init_occupy'] == 2 || $pattrs['tile_init_occupy'] == 4) ) $tiles_empire[] = $ukey;
		}

		$tiles['tiles_hq_allies'] = $tiles_hq_allies;
		$tiles['tiles_hq_empire'] = $tiles_hq_empire;
		$tiles['tiles_safezone_allies'] = $tiles_safezone_allies;
		$tiles['tiles_safezone_empire'] = $tiles_safezone_empire;
		$tiles['tiles_forts'] = $tiles_forts;
		$tiles['tiles_normal'] = $tiles_normal;
		$tiles['tiles_allies'] = $tiles_allies;
		$tiles['tiles_empire'] = $tiles_empire;

		$timer_end = microtime(true);
		elog("time took battlefield for battlefield::get_tiles(): " . ($timer_end - $timer_bgn));

		store_into_cache('constants:tiles', $tiles);

		$bnis = array_keys($tiles);
		elog("battlefield tilekeys: " . json_encode($bnis));
		elog("battlefield tiles_hq_allies: " . json_encode($tiles_hq_allies));
		elog("battlefield tiles_hq_empire: " . json_encode($tiles_hq_empire));
		elog("battlefield tiles_safezone_allies: " . json_encode($tiles_safezone_allies));
		elog("battlefield tiles_safezone_empire: " . json_encode($tiles_safezone_empire));
		elog("battlefield tiles_forts: " . json_encode($tiles_forts));
		elog("battlefield tiles_normal: " . json_encode($tiles_normal));
		elog("battlefield tiles_allies: " . json_encode($tiles_allies));
		elog("battlefield tiles_empire: " . json_encode($tiles_empire));

		return $tiles;
	}

	/**
	 * Check that do we need default tiles
	 * @param unknown $tb
	 */
	public static function default_tiles($tb, $force_rebuild = false) {
		global $TAG;
		global $TILE_INIT_DISCONNECTED_ALLIES, $TILE_INIT_DISCONNECTED_EMPIRE;
		global $BATTLEFIELD_REBUILD_PERIOD_BY_DAY, $BATTLEFIELD_RANKING_QTY;
		global $TILE_SAFEZONE_ALLIES, $TILE_SAFEZONE_EMPIRE;
		
		$query = "SELECT MAX(battlefield_id) AS mbid FROM battlefield";
		assert_render($rs = $tb->query($query));
		$max_bid = ms_fetch_single_cell($rs);
		if ( !$force_rebuild && $max_bid && $max_bid > 0 ) {
			elog("we already have battlefield: $max_bid");
			return;
		}

		elog("re-creating battlefield ...");

		$terms = [];
		$terms['created_at'] = "NOW()";
		$terms['willbe_rebuilt_at'] = "TIMESTAMPADD(DAY, $BATTLEFIELD_REBUILD_PERIOD_BY_DAY, NOW())";

		$keys = $vals = [];
		join_terms($terms, $keys, $vals);

		$query = "INSERT INTO battlefield ($keys) VALUES ($vals)";
		assert_render($tb->query_with_affected($query, 1));

		$battlefield_id = $tb->insert_id();

		$TILES = tile::get_tiles();

		$effects = [];
		$effects['allies'] = []; // ['_'=>'_'];
		$effects['empire'] = []; // ['_'=>'_'];
		$effects['legion'] = []; // ['_'=>'_'];

		$querys = [];
		foreach ($TILES as $tile_name => $tile) {
			if ( strstr($tile_name, 'tiles_') ) continue;

			if ( $tile['tile_attr'] == 0 ) continue;

			$pos = $tile_name;
			
			$terms = [];
			$terms['battlefield_id'] = $battlefield_id;
			// 			$terms['tile_id'] = $tile['tile_id'];
			$terms['position'] = ms_quote($tile_name);
			if ( $tile['tile_init_occupy'] == 1 || $tile['tile_init_occupy'] == 3 )
				$terms['connected'] = $terms['occupy_force'] = ALLIES;
			else if ( $tile['tile_init_occupy'] == 2 || $tile['tile_init_occupy'] == 4 )
				$terms['connected'] = $terms['occupy_force'] = EMPIRE;
			else
				$terms['occupy_force'] = 0;
			
			if ( in_array($tile_name, $TILE_INIT_DISCONNECTED_ALLIES) || in_array($tile_name, $TILE_INIT_DISCONNECTED_EMPIRE) )
				$terms['connected'] = 0;

			if ( $tile['tile_init_occupy'] == 1 )
				$terms['dispute'] = 1;
			if ( $tile['tile_init_occupy'] == 2 )
				$terms['dispute'] = 2;
			if ( $tile['tile_init_occupy'] == 5 ) // neutral
				$terms['dispute'] = 3;

			$keys = $vals = [];
			join_terms($terms, $keys, $vals);
			$querys[] = "INSERT INTO tile ($keys) VALUES ($vals)";

			// calculate effects
			if ( ($terms['occupy_force'] == ALLIES && $terms['connected'] == ALLIES) || ($terms['occupy_force'] == EMPIRE && $terms['connected'] == EMPIRE) ) {
				$ekey = null;
				if ( $tile['tile_attr'] == 3 && $tile['legion_id'] > 0 ) {
					// never reached, as we're on initialization
					
					// TODO: legion

					// 					if ( !isset($effects_legion[$tile['legion_id']]) )
					// 						$effects_legion[$tile['legion_id']] = [];
					// 					$effects_base = &$effects_legion[$tile['legion_id']];
				} else {
					if ( $terms['occupy_force'] == ALLIES )
						$ekey = 'allies';
					else if ( $terms['occupy_force'] == EMPIRE )
						$ekey = 'empire';
				}
					
				if ( $ekey == null )
					; // do nothing
				else if ( $tile['occupy_effect_type'] == 1 ) {
					// tax collection
					if ( !isset($effects[$ekey]['102']) ) $effects[$ekey]['102'] = 0;
					if ( !(in_array($pos, $TILE_SAFEZONE_ALLIES) || in_array($pos, $TILE_SAFEZONE_EMPIRE)) ) {
						$effects[$ekey]['102'] += intval($tile['occupy_effect_value']);
						// 						elog("102 pos: $pos");
					}
				}
				else if ( $tile['occupy_effect_type'] == 2 ) {
					if ( !isset($effects[$ekey]['106']) ) $effects[$ekey]['106'] = 0;
					$effects[$ekey]['106'] -= intval($tile['occupy_effect_value']);
				} else if ( $tile['occupy_effect_type'] == 3 ) {
					if ( !isset($effects[$ekey]['155']) ) $effects[$ekey]['155'] = 0;
					$effects[$ekey]['155'] += intval($tile['occupy_effect_value']);
				} else if ( $tile['occupy_effect_type'] == 4 ) {
					if ( !isset($effects[$ekey]['105']) ) $effects[$ekey]['105'] = 0;
					$effects[$ekey]['105'] += intval($tile['occupy_effect_value']);
				}
					
// 				elog("At position: $pos, ekey: $ekey : " . pretty_json($effects));
			}
		}
		elog("effects: " . pretty_json($effects));

		$terms = [];
		$terms['effects_allies'] = ms_quote($tb->escape(pretty_json($effects['allies'], JSON_FORCE_OBJECT)));
		$terms['effects_empire'] = ms_quote($tb->escape(pretty_json($effects['empire'], JSON_FORCE_OBJECT)));
		$terms['effects_legion'] = ms_quote($tb->escape(pretty_json($effects['legion'], JSON_FORCE_OBJECT)));

		$pairs = join_terms($terms);
		$querys[] = "UPDATE battlefield SET $pairs WHERE battlefield_id = $battlefield_id";

		assert_render($tb->multi_query($querys));

		$redis = conn_redis();
		$rkeys = $redis->keys("$TAG:battlefield:*");
		if ( sizeof($rkeys) > 0 ) {
			elog("clearing redis battlefield keys by tag [$TAG]...");
			$redis->multi();
			foreach ($rkeys as $key)
				$redis->del($key);
			$redis->exec();
		}
		$redis->set("$TAG:battlefield:current_bid", $battlefield_id);
		
		// invalidate effects of all users
		elog("invalidate effects of all users ...");
		delete_at_redis('static_effects_updated_user_id_set');
	}

	public static function select_all($tb, $select_expr = null, $where_condition = null) {
		global $user_id, $general_id;

		if ( $select_expr == null )
			$select_expr = "*";
		if ( $where_condition == null )
			$where_condition = "WHERE battlefield_id = (SELECT MAX(battlefield_id) FROM battlefield)";
		else
			$where_condition = "WHERE ($where_condition)";

		$query = "SELECT $select_expr FROM tile $where_condition /*BY_HELPER*/";
		assert_render($rows = ms_fetch_all($tb->query($query)));

		$json_keys = array();

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
		$rows = tile::select_all($tb, $select_expr, $where_condition);
		if ( $rows && count($rows) == 1 )
			return $rows[0];
		return null;
	}

	public static function bf_select_all($tb, $select_expr = null, $where_condition = null) {
		global $user_id, $general_id;

		if ( $select_expr == null )
			$select_expr = "*";
		if ( $where_condition == null )
			$where_condition = "WHERE battlefield_id = (SELECT MAX(battlefield_id) FROM battlefield)";
		else
			$where_condition = "WHERE ($where_condition)";

		$query = "SELECT $select_expr FROM battlefield $where_condition /*BY_HELPER*/";
		assert_render($rows = ms_fetch_all($tb->query($query)));

		$json_keys = ['effects_allies', 'effects_empire', 'effects_legion', 'hotspots'];

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

	public static function bf_select($tb, $select_expr = null, $where_condition = null) {
		$rows = tile::bf_select_all($tb, $select_expr, $where_condition);
		if ( $rows && count($rows) == 1 )
			return $rows[0];
		return null;
	}

	public static function recover($tb, $redis) {
		global $TAG, $ALLIES, $EMPIRE, $user_id, $general_id, $dev_type, $dev_uuid, $ops, $status, $country;

		elog("RECOVER starts: " . __METHOD__);
			


		elog("RECOVER finished: " . __METHOD__);
	}

	public static function clear($tb) {
		global $TAG, $ALLIES, $EMPIRE, $user_id, $general_id, $dev_type, $dev_uuid, $ops, $status, $country;

		if ( dev ) {
			tile::default_tiles($tb, true);

			// 			$query = "DELETE FROM tile;";
			// 			assert_render($rs = $tb->query($query));
		}
	}

	public static function get($tb) {
		global $TAG, $ALLIES, $EMPIRE, $user_id, $general_id, $dev_type, $dev_uuid, $ops, $status, $country;
		global $tile_id;

		$battlefield = tile::bf_select($tb);
		assert_render($battlefield);

		$where = "battlefield_id = " . $battlefield['battlefield_id'];
		$position = queryparam_fetch('position');
		if ( $position )
			$where .= " AND position = " . ms_quote($tb->escape($position));

		$tiles = tile::select_all($tb, null, $where);
		if ( $position )
			assert_render(sizeof($tiles) == 1, "invalid:position:$position");

		$redis = conn_redis();
		foreach ($tiles as &$tile) {
			$tile['ranks'] = [];
			if ( $top_rank_info_raw = $redis->hGet("$TAG:battlefield:tile:top_ranks", $tile['position']) ) {
				if ( $rank_info = @json_decode($top_rank_info_raw) )
					$tile['ranks'][] = $rank_info;
			}
		}

		assert_render($tb->end_txn());

		$map['tiles'] = $tiles;
		$map['battlefield'] = $battlefield;

		render_ok('success', $map);
	}

	public static function ranks($tb) {
		global $TAG, $ALLIES, $EMPIRE, $user_id, $general_id, $dev_type, $dev_uuid, $ops, $status, $country;
		global $tile_id;
		global $BATTLEFIELD_RANKING_QTY;

		$battlefield = tile::bf_select($tb);
		assert_render($battlefield);

		$where = "battlefield_id = " . $battlefield['battlefield_id'];
		$position = queryparam_fetch('position');
		if ( $position )
			$where .= " AND position = " . ms_quote($tb->escape($position));
		// 		if ( !$where ) $where = "dispute > 0";

		$tiles = tile::select_all($tb, null, $where);
		if ( $position )
			assert_render(sizeof($tiles) == 1, "invalid:position:$position");

		assert_render($tb->end_txn());

		// get rankings from redis
		$redis = conn_redis();
		foreach ($tiles as &$tile) { 			// fetch rankings
			$rkey = "$TAG:battlefield:tile:ranking:" . $tile['position'];
			$okey = "$TAG:battlefield:tile:occupy_ranking:" . $tile['position'];

			$tile['ranks'] = [];

			$rank = 1;
			$force_ranks = $redis->zRevRangeByScore($rkey, '+inf', '(0', array('withscores' => TRUE, 'limit' => array(0, $BATTLEFIELD_RANKING_QTY)));
			if ( !$force_ranks )
				continue;

			// 			elog("force_ranks: " . pretty_json($force_ranks));
			foreach ($force_ranks as $rank_user_id => $rank_score) {
				$rank_info = [];
				$force_info = $redis->hGet("$TAG:battlefield:tile:info", $rank_user_id);
				if ( $force_info ) {
					// 					elog("force_info: " . pretty_json($force_info));

					$rank_info = @json_decode($force_info, true);

					$occupy_rank = is_numeric($v = $redis->zRank($okey, $rank_user_id)) ? $v + 1 : 0;
					$occupy_score = is_numeric($v = $redis->zScore($okey, $rank_user_id)) ? $v : 0;

					$rank_info['rank'] = $rank;
					$rank_info['score'] = $rank_score;
					$rank_info['occupy_rank'] = $occupy_rank;
					$rank_info['occupy_score'] = $occupy_score;

					if ( $rank == 1 ) {
						$redis->hSet("$TAG:battlefield:tile:top_ranks", $tile['position'], pretty_json($rank_info));
					}
				}
				$tile['ranks'][] = $rank_info;

				$rank++;
			}
		}

		$map['tiles'] = $tiles;

		render_ok('success', $map);
	}

	public static function rebuild_hotspots($tb) {
		global $TAG, $ALLIES, $EMPIRE, $user_id, $general_id, $dev_type, $dev_uuid, $ops, $status, $country;
		global $BATTLEFIELD_HOTSPOT_TIME_QUANTUM, $BATTLEFIELD_HOTSPOT_QTY;
		global $TAG;

		$battlefield = tile::bf_select($tb);

		$now = new DateTime();
		$now->setTimestamp(time());
		$now_at_minute = intval($now->format('i'));

		$cur_quantum = intval($BATTLEFIELD_HOTSPOT_TIME_QUANTUM*floor($now_at_minute / $BATTLEFIELD_HOTSPOT_TIME_QUANTUM));
		$next_quantum = $cur_quantum + $BATTLEFIELD_HOTSPOT_TIME_QUANTUM;
		$next_quantum = $next_quantum >= 60 ? 0 : $next_quantum;

		$hskey = sprintf("$TAG:battlefield:hotspot:combat_counts:%02d", $next_quantum);
		$hsout = "$TAG:battlefield:hotspot:combat_counts";

		$hskeys = [];
		for ( $i = 0 ; $i < 60 ; $i += $BATTLEFIELD_HOTSPOT_TIME_QUANTUM )
			$hskeys[] = sprintf("$TAG:battlefield:hotspot:combat_counts:%02d", $i);

		$redis = conn_redis();
		$zus_res = $redis->zunionstore($hsout, $hskeys);
		$redis->del($hskey); // remove next time quantum

		// use ZREVRANGE key start stop [WITHSCORES] to get hotspots
		$hotspots = $redis->zRevRangeByScore($hsout, '+inf', '-inf', array('withscores' => TRUE, 'limit' => array(0, $BATTLEFIELD_HOTSPOT_QTY)));
		elog("zunionstore_res: $zus_res, hotspots: " . pretty_json($hotspots));

		$terms = [];
		$terms['hotspots'] = ms_quote($tb->escape(pretty_json($hotspots)));
		$terms['hotspots_willbe_rebuilt_at'] = "TIMESTAMPADD(MINUTE, $BATTLEFIELD_HOTSPOT_TIME_QUANTUM, NOW())";
		$pairs = join_terms($terms);

		$query = "UPDATE battlefield SET $pairs WHERE battlefield_id = " . $battlefield['battlefield_id'];
		assert_render($tb->query($query));

		$detail = [];
		$detail['hotspots'] = $hotspots;
		gamelog(__METHOD__, $terms);
		
		if ( queryparam_fetch_int('stop_on_success') > 0 ) {
			assert_render($tb->end_txn());
			render_ok();
		}
		
		// let tile::get end transaction
	}

	public static function rebuild_battlefield($tb) {
		global $TAG, $ALLIES, $EMPIRE, $user_id, $general_id, $dev_type, $dev_uuid, $ops, $status, $country;
		global $BATTLEFIELD_REBUILD_PERIOD_BY_DAY, $BATTLEFIELD_RANKING_QTY;
		global $TILE_SAFEZONE_ALLIES, $TILE_SAFEZONE_EMPIRE;

		$TILES = tile::get_tiles();
		$TILE_BASE_POS_ALLIES = $TILES['tiles_hq_allies'];
		$TILE_BASE_POS_EMPIRE = $TILES['tiles_hq_empire'];

		$coord_to_pos = [];
		$ptiles = [];
		$tiles = tile::select_all($tb);
		assert_render(sizeof($tiles) > 0, "invalid:tiles:not initialized");

		$old_force_dispute_connected_set = [];

		foreach ($tiles as $tile) {
			$TILE = $TILES[$tile['position']];

			$coord = intval($TILE['tile_x']) . "," . intval($TILE['tile_y']);
			$coord_to_pos[$coord] = $tile['position'];

			if ( $TILE['tile_attr'] == 2 || $TILE['tile_attr'] == 3 || $TILE['tile_attr'] == 4 ) {
				// 				$tile['occupy_force'] = 0;

				// below is not USED (for test only)
				if ( dev && empty($tile['occupy_score_allies']) && empty($tile['occupy_score_empire']) && 0 ) {
					// get this from user's scores
					$tile['occupy_score_allies'] = mt_rand(1000, 10000);
					$tile['occupy_score_empire'] = mt_rand(1000, 10000);
					if ( $TILE['tile_x'] <= 7 && $TILE['tile_y'] >= 7 )
						$tile['occupy_score_allies'] += mt_rand(4000, 7000);
					else
						$tile['occupy_score_empire'] += mt_rand(4000, 7000);
				}
			}

			// merge tile's info from TILES
			$ptiles[$tile['position']] = $tile;
			foreach($TILE as $k => $v)
				$ptiles[$tile['position']][$k] = $v;

			$ofds = sprintf("%d,%d,%d",
					$ptiles[$tile['position']]['occupy_force'] > 0 ?: 0,
					$ptiles[$tile['position']]['dispute'] > 0 ?: 0,
					$ptiles[$tile['position']]['connected'] > 0 ?: 0);

			$old_force_dispute_connected_set[$tile['position']] = $ofds;
		}

		// 		elog("coord_to_pos: " . pretty_json($coord_to_pos));

		// determine occupy
		foreach($ptiles as $pos => $tile) {
			$ptiles[$pos]['dispute'] = 0;
			$ptiles[$pos]['connected'] = 0;

			if ( $tile['tile_attr'] == 3 || $tile['tile_attr'] == 4 ) {
				if ( $tile['occupy_score_allies'] == $tile['occupy_score_empire'] ) {
					// 					$ptiles[$pos]['occupy_force'] = NEUTRAL; // what if we have same score? do not change
				}
				else if ( $tile['occupy_score_allies'] > $tile['occupy_score_empire'] )
					$ptiles[$pos]['occupy_force'] = ALLIES;
				else
					$ptiles[$pos]['occupy_force'] = EMPIRE;
			}

			if ( $tile['tile_attr'] == 2 && $tile['tile_init_occupy'] == 3 )
				$ptiles[$pos]['occupy_force'] = ALLIES;
			if ( $tile['tile_attr'] == 2 && $tile['tile_init_occupy'] == 4 )
				$ptiles[$pos]['occupy_force'] = EMPIRE;

			if ( in_array($pos, $TILE_SAFEZONE_ALLIES) )
				$ptiles[$pos]['occupy_force'] = ALLIES;
			if ( in_array($pos, $TILE_SAFEZONE_EMPIRE) )
				$ptiles[$pos]['occupy_force'] = EMPIRE;

		}
		// 		elog(pretty_json($ptiles));

		// determine connectivity and dispute
		$neighbours_even = [[-1, 0], [+1, 0], [0, -1], [0, +1], [+1, -1], [+1, +1]];
		$neighbours_odd = [[-1, 0], [+1, 0], [0, -1], [0, +1], [-1, +1], [-1, -1]];
		$all_visited = [];
		$visiteds = [];

		$params = [
		['name'=> 'allies', 'base'=>$TILE_BASE_POS_ALLIES, 'force'=>ALLIES, 'on_dispute'=>ALLIES, 'on_connect'=>ALLIES],
		['name'=> 'empire', 'base'=>$TILE_BASE_POS_EMPIRE, 'force'=>EMPIRE, 'on_dispute'=>EMPIRE, 'on_connect'=>EMPIRE],
		];

		foreach ($params as $param) {
			$visited = [];
			$will_visit = [$param['base']];

			// O(N^2)
			while ( sizeof($will_visit) > 0 ) {
				$cur_pos = array_pop($will_visit);
				$cur_tile = $ptiles[$cur_pos];
					
				$visited[] = $cur_pos;
				if ( !in_array($cur_pos, $all_visited) )
					$all_visited[] = $cur_pos;

				if ( $cur_tile['occupy_force'] == $param['force'] )
					$ptiles[$cur_pos]['connected'] = $cur_tile['connected'] = $param['on_connect'];

				// 				elog("testing: $cur_pos ... connected: " . $cur_tile['connected'] . ", of:" . $cur_tile['occupy_force']);

				$neighbours = $neighbours_even;
				if ( (intval($cur_tile['tile_y']) % 2) > 0 )
					$neighbours = $neighbours_odd;

				foreach ($neighbours as $neighbour) { // we have 6 directions on hexagonal coordnation system
					$nx = intval($ptiles[$cur_pos]['tile_x']);
					$ny = intval($ptiles[$cur_pos]['tile_y']);

					$okey = "$nx,$ny"; // candidate key

					$nx += $neighbour[0];
					$ny += $neighbour[1];

					$ckey = "$nx,$ny"; // candidate key

					// 					elog(" >> neighbour: from $okey to $ckey ...");
					if ( empty($coord_to_pos[$ckey]) ) // invalid coord
						continue;

					$npos = $coord_to_pos[$ckey];
					if ( in_array($npos, $visited) ) // already visited
						continue;
					if ( in_array($npos, $will_visit) ) // already in queue
						continue;

					$next_tile = $ptiles[$npos];
					if ( !($next_tile['tile_attr'] == 2 || $next_tile['tile_attr'] == 3 || $next_tile['tile_attr'] == 4) )
						continue; // not visitable

					// mark dispute
					if ( $next_tile['occupy_force'] > 0 && $next_tile['occupy_force'] != $param['force'] )
						$ptiles[$cur_pos]['dispute'] = $param['on_dispute'];

					if ( $next_tile['occupy_force'] != $param['force'] )
						continue;

					$will_visit[] = $npos;
					// 					elog("i will visit: $npos, $ckey");
				}
					
				// 				elog("will_visit: " . pretty_json($will_visit));
				// 				elog("visited: " . pretty_json($visited));
			}

			$visiteds[$param['name']] = $visited;
		}

		// for all other tiles not visited are NOT connected to base
		foreach($ptiles as $pos => $tile) {
			if ( $ptiles[$pos]['occupy_force'] == ALLIES && !in_array($pos, $visiteds['allies']) ) {
				$ptiles[$pos]['connected'] = 0;
				$ptiles[$pos]['dispute'] = 0; // isolated tiles should not be disputed
			} else if ( $ptiles[$pos]['occupy_force'] == EMPIRE && !in_array($pos, $visiteds['empire']) ) {
				$ptiles[$pos]['connected'] = 0;
				$ptiles[$pos]['dispute'] = 0; // isolated tiles should not be disputed
			}

			if ( !in_array($pos, $all_visited) ) // tile is not connected to both bases
				$ptiles[$pos]['connected'] = 0;
		}

		$effects = [];
		$effects['allies'] = []; // ['_'=>'_'];
		$effects['empire'] = []; // ['_'=>'_'];
		$effects['legion'] = []; // ['_'=>'_'];

		// isolated tiles should not make neighbours diputed
		foreach($ptiles as $pos => $tile) {
			if ( $ptiles[$pos]['connected'] == 0 )
				$ptiles[$pos]['dispute'] = 0;

			// check neighbours again (at least one neighbour tile is occupyed by opponent and connected
			$cur_pos = $pos;
			$cur_tile = $tile;

			$found_opponent = 0;

			$neighbours = $neighbours_even;
			if ( (intval($cur_tile['tile_y']) % 2) > 0 )
				$neighbours = $neighbours_odd;

			foreach ($neighbours as $neighbour) { // we have 6 directions on hexagonal coordnation system
				$nx = intval($ptiles[$cur_pos]['tile_x']);
				$ny = intval($ptiles[$cur_pos]['tile_y']);
					
				$okey = "$nx,$ny"; // original key
					
				$nx += $neighbour[0];
				$ny += $neighbour[1];
					
				$ckey = "$nx,$ny"; // candidate key
					
				// 					elog(" >> neighbour: from $okey to $ckey ...");
				if ( empty($coord_to_pos[$ckey]) ) // invalid coord
					continue;
					
				$npos = $coord_to_pos[$ckey];
					
				$next_tile = $ptiles[$npos];
				if ( !($next_tile['tile_attr'] == 2 || $next_tile['tile_attr'] == 3 || $next_tile['tile_attr'] == 4) )
					continue; // not visitable
					
				// dis-mark dispute
				if ( $next_tile['occupy_force'] != $cur_tile['occupy_force'] && $next_tile['connected'] > 0 )
					$found_opponent = 1;
			}

			// BUT, isolated tiles could be diputed by itself at 1015
			if ( $ptiles[$cur_pos]['connected'] == 0 && $ptiles[$cur_pos]['dispute'] == 0 && $found_opponent )
				$ptiles[$cur_pos]['dispute'] = $ptiles[$cur_pos]['occupy_force'];

			// 			if ( !$found_opponent )
			// 				$ptiles[$cur_pos]['dispute'] = 0;

			// forts should be disputing always
			if (  $ptiles[$cur_pos]['tile_attr'] == 3 ) {
				elog("forts should be disputing always: at $cur_pos, occupy_force: " .  $ptiles[$cur_pos]['occupy_force']);
				if ( $ptiles[$cur_pos]['occupy_force'] == 1 ) $ptiles[$cur_pos]['dispute'] = 1;
				if ( $ptiles[$cur_pos]['occupy_force'] == 2 ) $ptiles[$cur_pos]['dispute'] = 2;
			}

			// calculate effects
			if ( ($tile['occupy_force'] == ALLIES && $tile['connected'] == 1) || ($tile['occupy_force'] == EMPIRE && $tile['connected'] == 2) ) {
				$ekey = null;
				if ( $tile['tile_attr'] == 3 && $tile['legion_id'] > 0 ) {
					// TODO: legion

					// 					if ( !isset($effects_legion[$tile['legion_id']]) )
					// 						$effects_legion[$tile['legion_id']] = [];
					// 					$effects_base = &$effects_legion[$tile['legion_id']];
				} else {
					if ( $tile['occupy_force'] == ALLIES )
						$ekey = 'allies';
					else if ( $tile['occupy_force'] == EMPIRE )
						$ekey = 'empire';
				}

				if ( $ekey == null )
					; // do nothing
				else if ( $tile['occupy_effect_type'] == 1 ) {
					// tax collection
					if ( !isset($effects[$ekey]['102']) ) $effects[$ekey]['102'] = 0;
					if ( !(in_array($pos, $TILE_SAFEZONE_ALLIES) || in_array($pos, $TILE_SAFEZONE_EMPIRE)) ) {
						$effects[$ekey]['102'] += intval($tile['occupy_effect_value']);
						// 						elog("102 pos: $pos");
					}
				}
				else if ( $tile['occupy_effect_type'] == 2 ) {
					if ( !isset($effects[$ekey]['106']) ) $effects[$ekey]['106'] = 0;
					$effects[$ekey]['106'] -= intval($tile['occupy_effect_value']);
				} else if ( $tile['occupy_effect_type'] == 3 ) {
					if ( !isset($effects[$ekey]['155']) ) $effects[$ekey]['155'] = 0;
					$effects[$ekey]['155'] += intval($tile['occupy_effect_value']);
				} else if ( $tile['occupy_effect_type'] == 4 ) {
					if ( !isset($effects[$ekey]['105']) ) $effects[$ekey]['105'] = 0;
					$effects[$ekey]['105'] += intval($tile['occupy_effect_value']);
				}

				// 				elog("At position: $pos, ekey: $ekey : " . pretty_json($effects));
			}
		}

		elog("effects: " . pretty_json($effects));
		elog("all_visited: " . pretty_json($all_visited));
		elog("visiteds: " . pretty_json($visiteds));

		// rebuild rankings (force, legion)
		elog("rebuilding force/legion rankings ...");
		$query = "SELECT * FROM tile_scores_general WHERE battlefield_id = (SELECT MAX(battlefield_id) FROM battlefield)";
		$general_scores = ms_fetch_all($tb->query($query));
		$query = "SELECT * FROM tile_scores_legion WHERE battlefield_id = (SELECT MAX(battlefield_id) FROM battlefield)";
		$legion_scores = ms_fetch_all($tb->query($query));

		// get new battlefield_id
		$terms = [];
		$terms['effects_allies'] = ms_quote($tb->escape(pretty_json($effects['allies'], JSON_FORCE_OBJECT)));
		$terms['effects_empire'] = ms_quote($tb->escape(pretty_json($effects['empire'], JSON_FORCE_OBJECT)));
		$terms['effects_legion'] = ms_quote($tb->escape(pretty_json($effects['legion'], JSON_FORCE_OBJECT)));
		$terms['created_at'] = "NOW()";
		$terms['willbe_rebuilt_at'] = "TIMESTAMPADD(DAY, $BATTLEFIELD_REBUILD_PERIOD_BY_DAY, NOW())";

		$keys = $vals = [];
		$pairs = join_terms($terms, $keys, $vals);
		$query = "INSERT INTO battlefield ($keys) VALUES ($vals)";
		assert_render($tb->query($query));

		$battlefield_id = $tb->insert_id();

		// build querys for tile_scores (general, legion)
		$querys = [];
		foreach ($general_scores as $score) {
			$score['battlefield_id'] = $battlefield_id;
			$score['occupy_score'] = $score['score'];
			$score['score'] = 0;

			if ( $score['occupy_score'] > 0 ) {
				join_terms($terms, $keys, $vals);
				$querys[] = "INSERT INTO tile_scores_general ($keys) VALUES ($vals)";
			}
		}
		foreach ($legion_scores as $score) {
			$score['battlefield_id'] = $battlefield_id;
			$score['occupy_score'] = $score['score'];
			$score['score'] = 0;

			if ( $score['occupy_score'] > 0 ) {
				join_terms($terms, $keys, $vals);
				$querys[] = "INSERT INTO tile_scores_legion ($keys) VALUES ($vals)";
			}
		}

		// build querys for tiles
		foreach($ptiles as $pos => $tile) {
			$ofds = sprintf("%d,%d,%d",
					$tile['occupy_force'] > 0 ?: 0,
					$tile['dispute'] > 0 ?: 0,
					$tile['connected'] > 0 ?: 0);

			if ( @$old_force_dispute_connected_set[$pos] == $ofds ) {
				// DEPRECATED, since we keep every battlefield/tiles/scores for historying as of 2013.11.18
				
// 				elog("force_dispute_connected_set was not modified for position: [$pos]");
				// 				continue;
			}

			$terms = [];
			$terms['battlefield_id'] = $battlefield_id;
			$terms['position'] = ms_quote($pos);
			$terms['occupy_force'] = $tile['occupy_force'];
			$terms['dispute'] = $tile['dispute'];
			$terms['connected'] = $tile['connected'];

			join_terms($terms, $keys, $vals);
			$querys[] = "INSERT INTO tile ($keys) VALUES ($vals)";
		}

		if ( sizeof($querys) > 0 ) {
			$lquerys = [];
			for ($i = 0 ; $i < sizeof($querys) ; $i++ ) {
				if ( sizeof($lquerys) > 20 ) {
					assert_render($tb->multi_query($lquerys));
					$lquerys = [];
				}
				$lquerys[] = $querys[$i];
			}
			if ( sizeof($lquerys) > 0 )
				assert_render($tb->multi_query($lquerys));
		}

		$redis = conn_redis();

		$gi_key = "$TAG:battlefield:tile:info";
		$li_key = "$TAG:battlefield:tile:legion:info";

		$gi_del_keys = $li_del_keys = [];
		if ( $v = $redis->hKeys($gi_key) && is_array($v) )
			$gi_del_keys = $v;
		if ( $v = $redis->hKeys($li_key) && is_array($v) )
			$li_del_keys = $v;

		elog(sprintf("BEFORE genera_linfo_del_keys: %d, legion_info_del_keys: %d", sizeof($gi_del_keys), sizeof($li_del_keys)));

		$redis->multi();
		foreach($ptiles as $pos => $tile) {
			$old_rkey = "$TAG:battlefield:tile:occupy_ranking:$pos";
			$new_rkey = "$TAG:battlefield:tile:ranking:$pos";

			$redis->del($old_rkey);
			$redis->zunionstore($old_rkey, [$new_rkey]);
			$redis->del($new_rkey);

			$old_rkey = "$TAG:battlefield:tile:legion:occupy_ranking:$pos";
			$new_rkey = "$TAG:battlefield:tile:legion:ranking:$pos";

			$redis->del($old_rkey);
			$redis->zunionstore($old_rkey, [$new_rkey]);
			$redis->del($new_rkey);
		}
		$redis->exec();

		elog("shifted tile rankings from current to occupy on redis");
		$timer_bgn = microtime(true);

		$redis->multi();
		foreach($ptiles as $pos => $tile) {
			$old_rkey = "$TAG:battlefield:tile:occupy_ranking:$pos";

			$valid_keys = is_array($v = $redis->zRange($old_rkey, 0, $BATTLEFIELD_RANKING_QTY)) ? $v : [];
			$gi_del_keys = array_values(array_diff($gi_del_keys, $valid_keys));

			$old_rkey = "$TAG:battlefield:tile:legion:occupy_ranking:$pos";

			$valid_keys = is_array($v = $redis->zRange($old_rkey, 0, $BATTLEFIELD_RANKING_QTY)) ? $v : [];
			$li_del_keys = array_values(array_diff($li_del_keys, $valid_keys));
		}
		$redis->exec();

		$timer_end = microtime(true);
		$timer_elapsed = $timer_end - $timer_bgn;
		
		elog(sprintf("AFTER [time elspased: $timer_elapsed] genera_linfo_del_keys: %d, legion_info_del_keys: %d", sizeof($gi_del_keys), sizeof($li_del_keys)));

		$redis->multi();
		if ( sizeof($gi_del_keys) > 0 || sizeof($li_del_keys) > 0 ) {
			foreach ($gi_del_keys as $key)
				$redis->hDel($gi_key, $key);
			foreach ($li_del_keys as $key)
				$redis->hDel($li_key, $key);
		}

		$redis->del("$TAG:battlefield:tile:top_ranks");
		$redis->del("$TAG:battlefield:tile:legion:top_ranks");

		$redis->set("$TAG:battlefield:current_bid", $battlefield_id);
		$redis->exec();

		// invalidate effects of all users
		elog("invalidate effects of all users ...");
		delete_at_redis('static_effects_updated_user_id_set');

		gamelog(__METHOD__);

		tile::rebuild_hotspots($tb);

		assert_render($tb->end_txn());

		render_ok();
	}
}

if ( strstr($_SERVER["REQUEST_URI"], basename(__FILE__)) ) {

	$ops = explode(',', $op = queryparam_fetch('op', 'get'));

	$user_id = login_check();
	$general_id = session_GET('general_id');

	$tile_id = queryparam_fetch_int('tile_id');

	if ( sizeof(array_intersect_key(['get', 'clear', 'rebuild_hotspots', 'rebuild_battlefield', 'ranks'], $ops)) ) {
		for ( $retried = 1 ; $retried <= $TXN_RETRY_MAX ; $retried++ ) {
			try {
				$tb = new TxnBlock();

				if ( in_array("clear", $ops) ) tile::clear($tb);

				tile::default_tiles($tb, false);

				if  ( in_array('rebuild_battlefield', $ops) ) tile::rebuild_battlefield($tb);
				else if  ( in_array('rebuild_hotspots', $ops) ) tile::rebuild_hotspots($tb);
				else if  ( in_array('ranks', $ops) ) tile::ranks($tb);

				tile::get($tb); // embedes end_txn()
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
