<?php
require_once '../connect.php';
require_once '../general/general.php';
require_once '../army/troop.php';
require_once '../army/item.php';

class officer {
	public static function  fllc_mux($force, $legion, $level, $command) {
		$force_legion_level_command = 100000 * $level + $command;
		$force_legion_level_command += 100000000 * $force;
		$force_legion_level_command += 10000000 * ($legion > 0 ? 1 : 0);
		return $force_legion_level_command;
	}
	public static function  fllc_demux($force_legion_level_command) {
		$fllc = $force_legion_level_command;

		$force = intval($fllc / 100000000);
		$fllc -= $force;

		$legion = intval($fllc / 10000000);
		$fllc -= $legion;

		$level = intval($fllc / 100000);
		$fllc -= $level;

		$command = $fllc;

		return ['force' => $force, 'legion' => $legion, 'level' => $level, 'command' => $command];
	}

	public static function get_grades() {
		if ( $val = fetch_from_cache('constants:grades') )
			return $val;

		$timer_bgn = microtime(true);

		$officerinfo = loadxml_as_dom('xml/officerinfo.xml');
		if ( !$officerinfo ) {
			elog("failed to loadxml: " . 'xml/officerinfo.xml');
			return null;
		}

		$grades = array();

		foreach ($officerinfo->xpath("//grade_info/grades/grade") as $node ) {
			$attrs = (array)$node->attributes();
			$pattrs = $attrs['@attributes'];

			$ukey = $pattrs["grade"];

			$newattrs = array();
			foreach ( $pattrs as $key => $val ) {
				if ( strpos($key, "cl_") === 0 )
					continue; // skip client-side effective related keys
					
				$newattrs[$key] = $val;
			}
			$grades[$ukey] = $newattrs;
		}

		$bnis = array_keys($grades);

		$timer_end = microtime(true);
		elog("time took for officer::get_grades(): " . ($timer_end - $timer_bgn));

		store_into_cache('constants:grades', $grades);

		elog("officer grades keys: " . json_encode($bnis));

		return $grades;
	}
	public static function get_buffs() {
		if ( $val = fetch_from_cache('constants:buffs') )
			return $val;

		$timer_bgn = microtime(true);

		$buffinfo = loadxml_as_dom('xml/buffinfo.xml');
		if ( !$buffinfo ) {
			elog("failed to loadxml: " . 'xml/buffinfo.xml');
			return null;
		}

		$buffs = array();

		foreach ($buffinfo->xpath("//buff") as $node ) {
			$attrs = (array)$node->attributes();
			$pattrs = $attrs['@attributes'];

			$ukey = $pattrs["id"];

			$newattrs = array();
			foreach ( $pattrs as $key => $val ) {
				if ( strpos($key, "cl_") === 0 ) continue; // skip client-side effective related keys
				$newattrs[$key] = $val;
			}
			$buffs[$ukey] = $newattrs;
		}

		$bnis = array_keys($buffs);

		$timer_end = microtime(true);
		elog("time took for officer::get_buffs(): " . ($timer_end - $timer_bgn));

		store_into_cache('constants:buffs', $buffs);

		elog("officer buffs keys: " . json_encode($bnis));

		return $buffs;
	}

	public static function get_officers() {
		if ( $val = fetch_from_cache('constants:officers') )
			return $val;

		$timer_bgn = microtime(true);

		$officerinfo = loadxml_as_dom('xml/officerinfo.xml');
		if ( !$officerinfo ) {
			elog("failed to loadxml: " . 'xml/officerinfo.xml');
			return null;
		}

		$officers = array();
		$officers_allies_ids = array();
		$officers_empire_ids = array();
		$officers_common_ids = array();

		foreach ($officerinfo->xpath("//officers/officer") as $node ) {
			$attrs = (array)$node->attributes();
			$pattrs = $attrs['@attributes'];

			$ukey = $pattrs["id"];

			$newattrs = array();
			foreach ( $pattrs as $key => $val ) {
				if ( strpos($key, "cl_") === 0 )
					continue; // skip client-side effective related keys
					
				$newattrs[$key] = $val;
			}
			$officers[$ukey] = $newattrs;

			$grades = array();
			$max_grade = 0;
			foreach($node->xpath("grades/grade") as $grade ) {
				$grade_attrs = (array)$grade->attributes();
				$grade_pattrs = $grade_attrs['@attributes'];

				$grades[$grade_pattrs['grade']] = $grade_pattrs;

				$max_grade = max($max_grade, $grade_pattrs['grade']);
			}

			elog(sprintf("setting [%2d] grades (max: %2d) for officer:id [%s]", count($grades), $max_grade, $ukey));

			$officers[$ukey]['max_grade'] = $max_grade;
			$officers[$ukey]['grades'] = $grades;

			if ( $pattrs['force'] == 1 )
				$officers_allies_ids[] = $ukey;
			else if ( $pattrs['force'] == 2 )
				$officers_empire_ids[] = $ukey;
			else if ( $pattrs['force'] == 3 )
				$officers_common_ids[] = $ukey;
		}

		$bnis = array_keys($officers);
		$officers['officers_empire_ids'] = $officers_empire_ids;
		$officers['officers_allies_ids'] = $officers_allies_ids;
		$officers['officers_common_ids'] = $officers_common_ids;

		$timer_end = microtime(true);
		elog("time took for officer::get_officers(): " . ($timer_end - $timer_bgn));

		store_into_cache('constants:officers', $officers);

		elog("officer officers keys: " . json_encode($bnis));
		elog("officer officers_empire_ids: " . json_encode($officers_empire_ids));
		elog("officer officers_allies_ids: " . json_encode($officers_allies_ids));
		elog("officer officers_common_ids: " . json_encode($officers_common_ids));

		return $officers;
	}

	public static function get_specialitys() {
		if ( $val = fetch_from_cache('constants:specialitys') )
			return $val;

		$timer_bgn = microtime(true);

		$specialityinfo = loadxml_as_dom('xml/specialityinfo.xml');
		if ( !$specialityinfo ) {
			elog("failed to loadxml: " . 'xml/specialityinfo.xml');
			return null;
		}

		$specialitys = array();

		foreach ($specialityinfo->xpath("//speciality") as $node ) {
			$attrs = (array)$node->attributes();
			$pattrs = $attrs['@attributes'];

			$ukey = $pattrs["id"];

			$newattrs = array();
			foreach ( $pattrs as $key => $val ) {
				if ( strpos($key, "cl_") === 0 ) continue; // skip client-side effective related keys
				$newattrs[$key] = $val;
			}
			$specialitys[$ukey] = $newattrs;

			$grades = array();
			$max_grade = 0;
			foreach($node->xpath("//grade") as $grade ) {
				$grade_attrs = (array)$grade->attributes();
				$grade_pattrs = $grade_attrs['@attributes'];

				$grades[$grade_pattrs['grade']] = $grade_pattrs;

				$max_grade = max($max_grade, $grade_pattrs['grade']);
			}

			elog(sprintf("setting [%2d] grades (max: %2d) for speciality:id [%s]", count($grades), $max_grade, $ukey));

			$specialitys[$ukey]['max_grade'] = $max_grade;
			$specialitys[$ukey]['grades'] = $grades;
		}

		$bnis = array_keys($specialitys);

		$timer_end = microtime(true);
		elog("time took for speciality::get_specialitys(): " . ($timer_end - $timer_bgn));

		store_into_cache('constants:specialitys', $specialitys);

		elog("speciality specialitys keys: " . json_encode($bnis));

		return $specialitys;
	}

	public static function get_levels() {
		if ( $val = fetch_from_cache('constants:officer_levels') )
			return $val;

		$timer_bgn = microtime(true);

		$levelinfo = loadxml_as_dom('xml/officer_level_info.xml');
		if ( !$levelinfo ) {
			elog("failed to loadxml: " . 'xml/officer_level_info.xml');
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
		elog("time took officer for officer::get_levels(): " . ($timer_end - $timer_bgn));

		store_into_cache('constants:officer_levels', $levels);

		$bnis = array_keys($levels);
		elog("officer levelkeys: " . json_encode($bnis));

		return $levels;
	}

	public static function check_unhired_officer_list_reset($general_id, $tb) {
		global $OFFICER_RESET_COOLTIME, $OFFICER_UNHIRED;

		$list_was_reset = false;

		$query = "SELECT officer_list_willbe_reset_at FROM general WHERE general_id = $general_id"
		." AND (officer_list_willbe_reset_at IS NULL OR officer_list_willbe_reset_at <= NOW())";
		$general = ms_fetch_one($tb->query($query));

		if ( $general ) {
			$reset_cooltime = general::apply_effects($tb, 114, $OFFICER_RESET_COOLTIME);

			elog("general_id($general_id) needs officer list reset, and again in $reset_cooltime secs");

			$query = "DELETE FROM officer WHERE general_id = $general_id AND status = $OFFICER_UNHIRED";
			assert_render($tb->query($query));

			$terms = [];
			$terms['officer_list_willbe_reset_at'] = "TIMESTAMPADD(SECOND, $reset_cooltime, NOW())";
			$pairs = join_terms($terms);
			$query = "UPDATE general SET $pairs WHERE general_id = $general_id";
			assert_render($tb->query($query));

			officer::generate_unhired($tb);


			$list_was_reset = true;
		}

		return $list_was_reset;
	}

	public static function check_officer_status_change($general_id, $tb, $target_officer_id = null) {
		global $OFFICER_TRAINING, $OFFICER_HEALING;

		$query = "SELECT * FROM officer WHERE general_id = $general_id AND status_changed_at IS NOT NULL AND "
		."status_changed_at <= NOW() AND (status = $OFFICER_TRAINING OR status = $OFFICER_HEALING)";
		if ( $target_officer_id != null )
			$query .= " AND officer_id = $target_officer_id";

		if ( !($rs = $tb->query($query)) )
			render_error();

		$events = ms_fetch_all($rs);
		if ( sizeof($events) > 0 )
			elog("we have events to process at check_officer_status_change: " . sizeof($events));

		foreach( $events as $event ) {
			elog("processing event: " . $event['status_change_context']);

			$ejs = json_decode($event['status_change_context'], true);
			if ( $ejs['change_type'] == 'train_unit' ) { // DEPRECATED at 10.01
				$exp_inc = 100 * $ejs['train_time']; // 경험치는 훈련시간 동안 점진적으로 증가한다. 레벨업도 훈련 중에 반영됨.
				if ( $ejs['train_type'] > 1 )
					$exp_inc *= 2;

				$terms = [];
				$terms['status'] = $ejs['return_status'];
				$terms['status_changing_at'] = null;
				$terms['status_changed_at'] = null;
				$terms['status_change_context'] = null;
				$terms['exp_cur'] = "exp_cur + $exp_inc";
				$pairs = join_terms($terms);

				$query = "UPDATE officer SET $pairs WHERE general_id = $general_id AND officer_id = " . $ejs['officer_id'];
				assert_render($tb->query_with_affected($query, 1));

				// check level-up, UPDATE officer.troop_cap, officer.ability_max

			} else if ( $ejs['change_type'] == 'train_ability' && false ) {
				// NR
			} else if ( $ejs['change_type'] == 'heal' ) {
				$terms = [];
				$terms['status'] = $ejs['return_status'];
				$terms['status_changing_at'] = null;
				$terms['status_changed_at'] = null;
				$terms['status_change_context'] = null;
				$pairs = join_terms($terms);

				$query = "UPDATE officer SET $pairs WHERE general_id = $general_id AND officer_id = " . $ejs['officer_id'];
				assert_render($tb->query_with_affected($query, 1));
			} else
				elog("invalid change_type: " . $event['change_type']);
		}
	}

	public static function check_officer_events($tb) {
		global $user_id, $general_id;

		officer::check_unhired_officer_list_reset($general_id, $tb);
		officer::check_officer_status_change($general_id, $tb);
	}

	public static function check_officer_levelup($tb, $officer_id, $officer = null) {
		global $TAG, $ALLIES, $EMPIRE, $user_id, $general_id, $dev_type, $dev_uuid, $ops, $status, $country;
		global $OFFICER_UNHIRED, $OFFICER_HIRED, $OFFICER_TRAINING, $OFFICER_HEALING, $OFFICER_DEAD;

		$OLEVELS = officer::get_levels();
		$OFFICER_LEVEL_MAX = $OLEVELS['max_level'];
		$GRADES = officer::get_grades();

		if ( $officer && !empty($officer['level']) && $officer['level'] >= $OFFICER_LEVEL_MAX ) {
			elog(sprintf("officer reached at max_level: %d, skipping levelup test", $OFFICER_LEVEL_MAX));
			return $officer['level'];
		}

		$new_level = 0;

		if ( !$officer )
			$officer = officer::select($tb, 'level, exp_cur, exp_max, grade', "officer_id = $officer_id AND level < $OFFICER_LEVEL_MAX AND exp_cur >= exp_max");

		if ( $officer ) {
			$old_level = $cur_level = $officer['level'];
			$next_level = $officer['level'] + 1;

			$grade = $officer['grade'];
			$GRADE = $GRADES[$grade];
			$grade_max_level = $GRADE['max_level'];

			$exp_cur = $officer['exp_cur'];
			$exp_max = $officer['exp_max'];

			$mods = [];
			$terms = [];
			$mod_terms = ['offense', 'defense', 'tactics', 'resists']; // columns
			foreach ($mod_terms as $mod_term)
				$mods[$mod_term] = 0;

			while (true) {
				elog("officer(grade: $grade, grade_max_level: $grade_max_level) WOULD level up from $cur_level to: $next_level");

				if ( $next_level > $OFFICER_LEVEL_MAX || $next_level > $grade_max_level ) {
					// render_error("officer level at max: $cur_level");
					elog("officer level at max(grade: $grade): $cur_level");
					if ( $exp_cur > $terms['exp_max'] )
						$terms['exp_cur'] = $terms['exp_max'];
											
					if ( sizeof($terms) > 0 ) { // needs update query
						$terms['level'] = $cur_level;
						break;
					}
					return false;
				}

				$mod_term = $mod_terms[ (($next_level + 2) % sizeof($mod_terms)) ];

				$terms['level'] = $cur_level;
				$terms['exp_max'] =  $OLEVELS[$next_level]['req_exp'];
				$terms['command_max'] =  $OLEVELS[$next_level]['officer_command'];
				$mods[$mod_term] += 1;

				$exp_max = $terms['exp_max'];
				elog(sprintf("at levels: $cur_level=>$next_level,[$exp_cur/$exp_max]"));

				if ( $exp_cur < $terms['exp_max'] ) {
					elog("stopping at cur_level: $cur_level, [$exp_cur/$exp_max]");
					break;
				}
				$cur_level = $next_level;
				$next_level++;
			}

			foreach ($mods as $k => $v)
				$terms[$k] = "$k + $v";
			$pairs = join_terms($terms);
			$query = "UPDATE officer SET $pairs WHERE officer_id = $officer_id";
			assert_render($tb->query_with_affected($query, 1));

			$new_level = $terms['level'];

			// update officer_hired_level_max
			$terms = [];
			$terms['officer_hired_level_max'] = "(SELECT MAX(level) FROM officer WHERE general_id = $general_id AND status > $OFFICER_UNHIRED)";
			$pairs = join_terms($terms);
			$query = "UPDATE general SET $pairs WHERE general_id = $general_id";
			assert_render($tb->query($query));

			quest::resolve_quests($tb, ['Officer_Level']);

			gamelog2('officer', 'levelup', ['officer_id'=>$officer_id, 'old_level'=>$old_level, 'new_level'=>$new_level]);
		}
		return $new_level;
	}

	public static function generate_officers($tb, $country, $qty = 1) {
		global $user_id, $general_id, $ops, $officer_id, $status;
		global $OFFICER_UNHIRED, $OFFICER_HIRED, $OFFICER_TRAINING, $OFFICER_HEALING, $OFFICER_DEAD;
		global $OFFICER_RESET_COOLTIME;

		assert_render(1 <= $country && $country <=3);

		$GRADES = officer::get_grades();

		$gradekeys = array_keys($GRADES);
		$weights = array();
		for ( $i = 0 ; $i < count($gradekeys) ; $i++ )
			$weights[] = $GRADES[$gradekeys[$i]]['weight'];
		$cprobs = null;

		$OFFICERS = officer::get_officers();
		$OLEVELS = officer::get_levels();

		if ( $country == 1 )
			$officer_candidate_ids = array_merge($OFFICERS['officers_allies_ids'], $OFFICERS['officers_common_ids']);
		else if ( $country == 2 )
			$officer_candidate_ids = array_merge($OFFICERS['officers_empire_ids'], $OFFICERS['officers_common_ids']);
		else if ( $country == 3 )
			$officer_candidate_ids = $OFFICERS['officers_common_ids'];

		assert_render(count($officer_candidate_ids) > 0, "count(officer_candidate_ids) > 0");

		$generated_officers = array();

		$OFFICER_INITIAL_LEVEL = 1;
		$next_level = min($OFFICER_INITIAL_LEVEL + 1, $OLEVELS['max_level']);

		for ( $i = 0 ; $i < $qty ; $i++ ) {
			// determine grade
			$grade = weighted_choice($gradekeys, $weights, $cpropbs);
			assert_render(in_array($grade, $gradekeys));

			$officer_type = $officer_candidate_ids[mt_rand(0, count($officer_candidate_ids)-1)];
			if ( dev && queryparam_fetch_int('officer_for_promote') > 0 ) {
				$officer_type = $officer_candidate_ids[0];
				$grade = 2;
			}

			$OFFICER = $OFFICERS[$officer_type];

			$new_officer = array();

			$new_officer['grade'] = $grade;
			$new_officer['type_id'] = $officer_type; // officer.ID

			$new_officer['speciality'] = $OFFICER['speciality'];
			$new_officer['level'] = $OFFICER_INITIAL_LEVEL;
			$new_officer['exp_cur'] = 0;
			$new_officer['exp_max'] = $OLEVELS[$next_level]['req_exp'];
			$new_officer['command_max'] = $OLEVELS[$OFFICER_INITIAL_LEVEL]['officer_command'];

			$new_officer['offense'] = $OFFICER['grades'][$grade]['offense'];
			$new_officer['defense'] = $OFFICER['grades'][$grade]['defense'];
			$new_officer['tactics'] = $OFFICER['grades'][$grade]['tactics'];
			$new_officer['resists'] = $OFFICER['grades'][$grade]['resists'];

			$generated_officers[] = $new_officer;

			elog("generated officer {grade: $grade, type: $officer_type}");
		}
		return $generated_officers;
	}

	public static function generate_unhired($tb) {
		global $user_id, $general_id, $ops, $officer_id, $status;
		global $OFFICER_UNHIRED, $OFFICER_HIRED, $OFFICER_TRAINING, $OFFICER_HEALING, $OFFICER_DEAD;
		global $OFFICER_RESET_COOLTIME, $OFFICER_UNHIRED_MIN;

		// 주점 건물로 부터 계산된 $OFFICER_UNHIRED_MIN을 이용해서 장교 목록수를 조정
		$general = general::select($tb, 'officer_unhired_max');
		$unhired_max = $general['officer_unhired_max'];

		if ( dev && !($unhired_max > 0) )
			$unhired_max = $OFFICER_UNHIRED_MIN;

		if ( !($unhired_max > 0) ) {
			elog("unhired_max: $unhired_max, skipping generate_unhireds");
			return ;
		}

		$query = "SELECT $unhired_max-COUNT(*) FROM officer"
		." WHERE general_id = $general_id AND status = $OFFICER_UNHIRED";
		assert_render($rs = $tb->query($query));

		$unhired_fill = ms_fetch_single_cell($rs);

		if ( $unhired_fill > 0 ) {
			elog("will generate [$unhired_fill / $unhired_max]  officers for general_id: $general_id");

			$country = session_GET('country');
			$generated_officers = officer::generate_officers($tb, $country, $unhired_fill);

			foreach ($generated_officers as $officer) {
				$term_keys = array();
				$term_vals = array();

				$officer['general_id'] = $general_id;
				$officer['status'] = $OFFICER_UNHIRED;

				$grade = $officer['grade'];
				$type_id = $officer['type_id'];

				$keys = ''; $vals = '';
				join_terms($officer, $keys, $vals);

				$query = "INSERT INTO officer ($keys) VALUES ($vals)";
				assert_render($rs = $tb->query_with_affected($query, 1), "officer was not inserted: grade: " . $officer['grade']);

				$new_officer_id = $tb->mc()->insert_id;
					
				elog("added officer {grade: $grade, type_id: $type_id, new_officer_id: $new_officer_id}");
			}
		}
	}

	public static function select_all($tb, $select_expr = null, $where_condition = null, $post_where = null) {
		global $user_id, $general_id, $ops, $officer_id, $status;
		global $OFFICER_UNHIRED, $OFFICER_HIRED, $OFFICER_TRAINING, $OFFICER_HEALING, $OFFICER_DEAD;
		global $OFFICER_RESET_COOLTIME;

		if ( $select_expr == null )
			$select_expr = "*";
		if ( $where_condition == null )
			$where_condition = "WHERE general_id = $general_id";
		else
			$where_condition = "WHERE general_id = $general_id AND ($where_condition)";

		if ( empty($post_where) )
			$post_where = " ORDER BY grade DESC, level DESC, type_id DESC";

		$query = "SELECT $select_expr FROM officer $where_condition $post_where /*BY_HELPER*/";
		assert_render($rows = ms_fetch_all($tb->query($query)));

		$json_keys = array('status_change_context', 'equipments');
		$rank_eff_keys = ['offense'=>121, 'defense'=>123, 'tactics'=>122, 'resists'=>124];
		$eff_keys = ['command_max'=>117];

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
				if ( !empty($cols[$eff_key]) )
					$cols[$eff_key . "_eff"] = general::apply_effects($tb, $effect_id, $cols[$eff_key]);
			}
			foreach ($rank_eff_keys as $eff_key => $effect_id) {
				if ( !empty($cols[$eff_key]) ) {
					$rank_term = "$eff_key" . "_rank";
					$rank_buf = 0;
					if ( !empty($cols[$rank_term]) ) {
						global $OFFICER_TRAIN_TABLE_NORMAL;

						if ( isset($OFFICER_TRAIN_TABLE_NORMAL[$cols[$rank_term]]['mod']) )
							$rank_buf = $OFFICER_TRAIN_TABLE_NORMAL[$cols[$rank_term]]['mod'];
					}

					$cols[$eff_key . "_eff"] = general::apply_effects($tb, $effect_id, $cols[$eff_key] + $rank_buf);
				}
			}

			$rows[$i] = $cols;
		}

		return $rows;
	}
	public static function select($tb, $select_expr = null, $where_condition = null) {
		$rows = officer::select_all($tb, $select_expr, $where_condition);
		if ( $rows && count($rows) == 1 )
			return $rows[0];
		return null;
	}

	public static function recover($tb, $redis) {
		global $TAG, $ALLIES, $EMPIRE, $user_id, $general_id, $dev_type, $dev_uuid, $ops, $status, $country;

		elog("RECOVER starts: " . __METHOD__);
			
		// armys
		elog("recovering armys ...");
		$query = "SELECT * FROM armys";
		$armys = ms_fetch_all($tb->query($query));

		$redis->del("$TAG:armys:force_legion_level_command");

		foreach($armys as $army) {
			$fllc_base = officer::fllc_mux($army['empire'] ? EMPIRE : ALLIES, $army['legion'] ? 1 : 0, 0, 0);
			$fllc = $fllc_base + $army['officer_level_command'];
			$fllc_map = officer::fllc_demux($fllc_base + $army['officer_level_command']);

			$gid = $army['general_id'];

			$redis->set("$TAG:users:user_id=$gid:army", $army['brief']);
			$redis->zAdd("$TAG:armys:force_legion_level_command", $fllc, $gid);
		}

		elog("recovered armys qty: " . sizeof($armys));

		elog("RECOVER finished: " . __METHOD__);
	}

	public static function clear($tb) {
		global $user_id, $general_id, $ops, $officer_id, $status;
		global $OFFICER_UNHIRED, $OFFICER_HIRED, $OFFICER_TRAINING, $OFFICER_HEALING, $OFFICER_DEAD;
		global $OFFICER_RESET_COOLTIME;

		if ( dev && in_array("clear", $ops) ) {
			$query = "DELETE FROM officer WHERE general_id = $general_id;";
			assert_render($tb->query($query));

			$query = "UPDATE general SET officer_hired_level_max = 0, officer_list_willbe_reset_at = NULL WHERE general_id = $general_id;";
			assert_render($tb->query($query));
		}
	}

	public static function default_officer_and_troops($tb, $general_id) {
		global $TAG, $ALLIES, $EMPIRE, $user_id, $general_id, $dev_type, $dev_uuid, $ops, $status, $country;
		global $OFFICER_UNHIRED, $OFFICER_HIRED, $OFFICER_TRAINING, $OFFICER_HEALING, $OFFICER_DEAD;
		global $TROOP_TRAINING, $TROOP_TRAINED, $TROOP_BANDED;

		$OFFICERS = officer::get_officers();
		$OLEVELS = officer::get_levels();
		$UNITS = troop::get_units();

		$country = session_GET('country');
		$officer_type = 2001;
		$units = [['id'=>1, 'qty'=>50], ['id'=>1, 'qty'=>50], ['id'=>1, 'qty'=>50], ['id'=>1, 'qty'=>50], ['id'=>1, 'qty'=>50], ['id'=>1, 'qty'=>50]];
		if ( $country == EMPIRE ) {
			$officer_type = 1001;
			$units = [['id'=>26, 'qty'=>50], ['id'=>26, 'qty'=>50], ['id'=>26, 'qty'=>50], ['id'=>26, 'qty'=>50], ['id'=>26, 'qty'=>50], ['id'=>26, 'qty'=>50]];
		}
		$OFFICER_INITIAL_LEVEL = 1;
		$grade = 1;
		$command_cur = 0;

		foreach($units as $unit) {
			$UNIT = $UNITS[$unit['id']];
			$command_cur += $UNIT['cost_command'] * $unit['qty'];
		}

		$OFFICER = $OFFICERS[$officer_type];

		$new_officer = [];

		$new_officer['grade'] = $grade;
		$new_officer['type_id'] = $officer_type; // officer.ID

		$new_officer['speciality'] = $OFFICER['speciality'];
		$new_officer['level'] = $OFFICER_INITIAL_LEVEL;
		$new_officer['exp_cur'] = 0;
		$new_officer['exp_max'] = $OLEVELS[$OFFICER_INITIAL_LEVEL+1]['req_exp'];
		$new_officer['command_max'] = $OLEVELS[$OFFICER_INITIAL_LEVEL]['officer_command'];

		$new_officer['offense'] = $OFFICER['grades'][$grade]['offense'];
		$new_officer['defense'] = $OFFICER['grades'][$grade]['defense'];
		$new_officer['tactics'] = $OFFICER['grades'][$grade]['tactics'];
		$new_officer['resists'] = $OFFICER['grades'][$grade]['resists'];

		$terms = $new_officer;
		$terms['general_id'] = $general_id;
		$terms['status'] = $OFFICER_HIRED;
		$terms['hired_at'] = 'NOW()';
		$terms['command_cur'] = $command_cur;

		$keys = $vals = [];
		join_terms($terms, $keys, $vals);
		$query = "INSERT INTO officer ($keys) VALUES ($vals)";
		assert_render($tb->query_with_affected($query, 1));

		$officer_id = $tb->mc()->insert_id;

		$terms = [];
		$slot = 1;
		foreach($units as $unit) {
			$UNIT = $UNITS[$unit['id']];
			$qty = $unit['qty'];

			$terms['general_id'] = $general_id;
			$terms['officer_id'] = $officer_id;
			$terms['type_major'] = $UNIT['type'];
			$terms['type_minor'] = $unit['id'];
			$terms['status'] = $TROOP_BANDED;
			$terms['qty'] = $qty;
			$terms['slot'] = $slot;
			$terms['training_at'] = 'NOW()';
			$terms['trained_at'] = 'NOW()';

			$keys = $vals = [];
			join_terms($terms, $keys, $vals);
			$query = "INSERT INTO troop ($keys) VALUES ($vals)";
			assert_render($tb->query_with_affected($query, 1));

			$slot++;
		}

		// make this officer to lead (pasted from officer::lead())
		$general = general::select($tb);
		assert_render($general['running_combat_id'] == null, 'you cannnot do this if you are running combat', FCODE(60301));

		$officer = officer::select($tb, null, "officer_id = $officer_id AND (status = $OFFICER_HIRED)");
		assert_render($officer, 'invalid:officer');

		$troops = troop::select_all($tb, null, "officer_id = $officer_id AND status = $TROOP_BANDED");
		assert_render($troops, "invalid:officer");

		// for pvp matching
		$ourforce = [];
		$ourforce['general'] = $general;
		$ourforce['officer'] = $officer;
		$ourforce['troops'] = $troops;

		$query = "UPDATE general SET leading_officer_id = $officer_id WHERE general_id = $general_id";
		assert_render($tb->query($query));
		$ourforce['general']['leading_officer_id'] = $officer_id;

		if ( $officer['command_cur'] > 0 ) {
			elog(sprintf("setting army of user_id($user_id) with level %s, command: %s", $officer['level'], $officer['command_cur']));
			global $TAG;

			$terms = [];
			$terms['general_id'] = $general_id;
			$terms['empire'] = $general['country'] == EMPIRE ? "TRUE" : "FALSE";
			$terms['legion'] = !empty($general['legion_id'])  ? "TRUE" : "FALSE";
			$terms['officer_level_command'] = officer::fllc_mux(0, 0, $officer['level'], $officer['command_cur']);
			$terms['brief'] = ms_quote($tb->escape(pretty_json($ourforce)));

			$keys = $vals = [];
			$pairs = join_terms($terms, $keys, $vals);
			$query = "INSERT INTO army ($keys) VALUES ($vals) ON DUPLICATE KEY UPDATE $pairs";
			assert_render($tb->query($query));

			$force_legion_level_command = officer::fllc_mux($general['country'],
					!empty($general['legion_joined_id']),
					$officer['level'], $officer['command_cur']);

			$redis = conn_redis();
			$redis->multi();
			$redis->set("$TAG:users:user_id=$user_id:army", pretty_json($ourforce));
			$redis->zAdd("$TAG:armys:force_legion_level_command", $force_legion_level_command, $user_id);
			$redis->exec();
		} else
			elog("will not set army of user_id($user_id) as officer's command is 0");
	}

	public static function get($tb) {
		global $user_id, $general_id, $ops, $officer_id, $status;
		global $OFFICER_UNHIRED, $OFFICER_HIRED, $OFFICER_TRAINING, $OFFICER_HEALING, $OFFICER_DEAD;
		global $OFFICER_RESET_COOLTIME;

		$terms = [];

		if ( $officer_id )
			$terms[] = "officer_id = $officer_id";
		if ( $status ) {
			assert_render($status == $OFFICER_UNHIRED || $status == $OFFICER_HIRED, "invalid:status: $status");
			$terms[] = "status = $status";
		}

		$where = implode(' AND ', $terms);

		$officers = officer::select_all($tb, null, $where);

		assert_render($tb->end_txn());

		if ( $officer_id && count($officers) == 0 )
			render_error("invalid:officer_id: $officer_id");

		$map['officers'] = $officers;

		render_ok('success', $map);
	}

	public static function reset($tb) {
		global $user_id, $general_id, $ops, $officer_id, $status;
		global $OFFICER_UNHIRED, $OFFICER_HIRED, $OFFICER_TRAINING, $OFFICER_HEALING, $OFFICER_DEAD;
		global $OFFICER_RESET_COOLTIME, $OFFICER_LIST_RESET_COST_STAR;

		// check star
		$general = general::select($tb, 'star');
		if ( $general['star'] < $OFFICER_LIST_RESET_COST_STAR )
			render_error("not enough star: ".$general['star']." < $OFFICER_LIST_RESET_COST_STAR", FCODE(60102));

		$terms = [];
		$terms['star'] = "star - $OFFICER_LIST_RESET_COST_STAR";
		$terms['officer_list_willbe_reset_at'] = null; // update timestamp to trigger reset
		$pairs = join_terms($terms);
		$query = "UPDATE general SET $pairs WHERE general_id = $general_id";

		gamelog(__METHOD__);

		assert_render($tb->query($query));
	}

	public static function hire($tb) {
		global $user_id, $general_id, $ops, $officer_id, $status;
		global $OFFICER_UNHIRED, $OFFICER_HIRED, $OFFICER_TRAINING, $OFFICER_HEALING, $OFFICER_DEAD;
		global $OFFICER_RESET_COOLTIME;

		$ignore_check = queryparam_fetch_int('ignore_check', 0);
		assert_render($officer_id, "invalid:officer_id: $officer_id");

		$officer = officer::select($tb, 'grade', "officer_id = $officer_id AND status = $OFFICER_UNHIRED");
		assert_render($officer, "invalid:officer_id: $officer_id");

		$general = general::select($tb, 'gold, honor, officer_hired_max');

		// check officer_hired_max
		$query = "SELECT COUNT(*) AS hired_count FROM officer WHERE general_id = $general_id AND status > $OFFICER_UNHIRED";
		$hired_count = ms_fetch_single_cell($tb->query($query));
		if ( $hired_count >= $general['officer_hired_max'] ) {
			render_error("hired_count at max: $hired_count", FCODE(60202));
		}

		// check hire costs
		$GRADES = officer::get_grades();
		$GRADE = $GRADES[$officer['grade']];

		$cost_gold = 0;
		$cost_honor = $GRADE['cost_honor'];

		$cost_gold = general::apply_effects($tb, 125, $cost_gold);
		$cost_honor = general::apply_effects($tb, 125, $cost_honor);

		$cur_gold = $general['gold'];
		$cur_honor = $general['honor'];
			
		if ( !($cost_gold <= $cur_gold && $cost_honor <= $cur_honor) ) {
			if ( !(dev && $ignore_check > 0) ) {
				$map['cost_gold'] = $cost_gold;
				$map['cost_honor'] = $cost_honor;
				$map['cur_gold'] = $cur_gold;
				$map['cur_honor'] = $cur_honor;
				$map['fcode'] = 60201;
				render_error("not enough: gold or honor: cost_gold($cost_gold) <= cur_gold($cur_gold) && cost_honor($cost_honor) <= cur_honor($cur_honor)", $map);
			}
		}

		// update officer states
		$query = "UPDATE officer SET status = $OFFICER_HIRED, hired_at = NOW() WHERE "
		."general_id = $general_id AND officer_id = $officer_id AND status = $OFFICER_UNHIRED";
		assert_render($tb->query_with_affected($query, 1), "invalid:officer_id: $officer_id not affected");

		// update hire cost
		$terms = [];
		$terms['gold'] = "gold - $cost_gold";
		$terms['honor'] = "honor - $cost_honor";
		$terms['officer_hired_level_max'] = "(SELECT MAX(level) FROM officer WHERE general_id = $general_id AND status > $OFFICER_UNHIRED)";
		$pairs = join_terms($terms);
		$query = "UPDATE general SET $pairs WHERE general_id = $general_id";
		assert_render($tb->query($query), "invalid:general_id: $general_id not affected");

		quest::resolve_quests($tb, ['Officer_Level']);

		gamelog(__METHOD__, ['officer'=>$officer]);

		// let officer::get to close txn
	}

	public static function fire($tb) {
		global $user_id, $general_id, $ops, $officer_id, $status;
		global $OFFICER_UNHIRED, $OFFICER_HIRED, $OFFICER_TRAINING, $OFFICER_HEALING, $OFFICER_DEAD;
		global $OFFICER_RESET_COOLTIME;
		global $TROOP_TRAINED;

		assert_render($officer_id, "invalid:officer_id: $officer_id");

		$officer = officer::select($tb, null, "officer_id = $officer_id AND status = $OFFICER_HIRED");
		assert_render($officer, "invalid:officer_id");

		// cannnot do this if you're running combat or on leading officer
		$general = general::select($tb, 'running_combat_id, leading_officer_id, item_storage_slot_cur, item_storage_slot_cap');
		assert_render($general['running_combat_id'] == null, 'you cannnot do this if you are running combat', FCODE(60301));
		assert_render($general['leading_officer_id'] != $officer_id, 'cannot fire leading officer', FCODE(60302));

		// CHECK, items belonged to officer can be held by storage
		$eids = $officer['equipments'] ?: [];
		if ( sizeof($eids) > 0 ) {
			$equips = [];

			$ids = implode(',', $eids);
			$items = item::select_all($tb, null, "item_id IN ($ids)") ?: [];
			foreach ($items as $item) {
				if ( $item['type_major'] == $ITEM_TYPE_MAJOR_EQUIPS )
					$equips[] = $item['item_id'];
			}

			// check storage capacity
			$storage_mod = sizeof($equips);
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

			foreach ($equips as $item) {
				elog("changing ownership of item[item_id=$item_id] from officer to general ...");
					
				// recover ownership to general
				$query = "UPDATE item SET status = $ITEM_GENERAL_OWNED, owner_id = $general_id WHERE item_id = $item_id";
				assert_render($tb->query($query));
			}

			if ( $storage_mod > 0 ) {
				// update general.item_storage_slot_cur
				$query = "UPDATE general SET item_storage_slot_cur = item_storage_slot_cur + $storage_mod WHERE general_id = $general_id";
				assert_render($tb->query($query));
			}
		}

		// disband all troops from firing officer
		$query = "UPDATE troop SET status = $TROOP_TRAINED, officer_id = NULL, slot = NULL"
		." WHERE general_id = $general_id AND officer_id = $officer_id";
		assert_render($tb->query($query));

		// merge disbanded troops
		troop::merge_all_troops_by_type_minor($tb);

		// fire officer
		$query = "DELETE FROM officer WHERE officer_id = $officer_id";
		assert_render($tb->query_with_affected($query, 1));

		// update officer_hired_level_max
		$terms = [];
		$terms['officer_hired_level_max'] = "(SELECT MAX(level) FROM officer WHERE general_id = $general_id AND status > $OFFICER_UNHIRED)";
		$pairs = join_terms($terms);
		$query = "UPDATE general SET $pairs WHERE general_id = $general_id";
		assert_render($tb->query($query));

		gamelog(__METHOD__, ['officer'=>$officer]);

		assert_render($tb->end_txn());

		render_ok("officer:fired: $officer_id");
	}

	public static function heal($tb) {
		global $user_id, $general_id, $ops, $officer_id, $status;
		global $OFFICER_UNHIRED, $OFFICER_HIRED, $OFFICER_TRAINING, $OFFICER_HEALING, $OFFICER_DEAD;
		global $OFFICER_RESET_COOLTIME;
		global $OFFICER_HEAL_COST_TIME, $OFFICER_HEAL_COST_GOLD_PER_LEVEL;
		global $BLD_HOSPITAL_ID;

		$heal_time = null;
		if ( dev )
			$heal_time = queryparam_fetch_int('heal_time'); // this for dev

		$officer = officer::select($tb, 'officer_id', "status = $OFFICER_HEALING");
		assert_render(!$officer, "another officer is healing", FCODE(60501));

		$general = general::select($tb, 'gold, building_list');

		// check building dependency (hospital)
		if ( construction::find_building($general['building_list'], $BLD_HOSPITAL_ID) == 0 ) {
			if ( !(dev && queryparam_fetch_int('ignore') > 0 ) )
				render_error("you need hospital to heal officer", FCODE(60502));
		}

		assert_render($officer_id, "invalid:officer_id: $officer_id");

		$where = "officer_id = $officer_id";
		if ( !(dev && queryparam_fetch_int('ignore') > 0 ) ) // do not check dead status on dev
			$where .= " AND status = $OFFICER_DEAD";

		$officer = officer::select($tb, null, $where);
		assert_render($officer, "invalid:officer_id");

		if ( $heal_time == null )
			$heal_time = $OFFICER_HEAL_COST_TIME;

		// check cost_gold for heal
		$cost_gold = $officer['level'] * $OFFICER_HEAL_COST_GOLD_PER_LEVEL;

		$cost_gold = general::apply_effects($tb, 120, $cost_gold);
		$heal_time = general::apply_effects($tb, 126, $heal_time);

		if ( !($cost_gold <= $general['gold']) ) {
			$map['cost_gold'] = $cost_gold;
			$map['cur_gold'] = $general['gold'];
			$map['fcode'] = 10104;

			render_error("not enough gold or honor", $map);
		}

		$context = [];
		$context['change_type'] = 'heal';
		$context['officer_id'] = $officer_id;
		$context['return_status'] = $OFFICER_HIRED;
		$context['heal_time'] = $heal_time;
		$context['changing_at_utc'] = time();
		$context['changed_at_utc'] = time() + $heal_time;
		$ejs = $tb->escape(pretty_json($context));

		$terms = [];
		$terms['status'] = $OFFICER_HEALING;
		$terms['status_changing_at'] = "NOW()";
		$terms['status_changed_at'] = "TIMESTAMPADD(SECOND, $heal_time, NOW())";
		$terms['status_change_context'] = ms_quote($ejs);

		$pairs = join_terms($terms);
		$query = "UPDATE officer SET $pairs WHERE general_id = $general_id AND officer_id = $officer_id";
		assert_render($tb->query_with_affected($query, 1), "invalid:officer_id: $officer_id not affected");

		$map['officers'] = $officers = officer::select_all($tb, null, "officer_id = $officer_id");

		gamelog(__METHOD__, ['officer'=>$officer, 'heal_time'=>$heal_time]);

		// post push
		$context = [];
		$context['user_id'] = $user_id;
		$context['dev_type'] = session_GET('dev_type');
		$context['dev_uuid'] = session_GET('dev_uuid');
		$context['src_id'] = "officer:heal:$officer_id";
		$context['send_at'] = $officers[0]['status_changed_at'];
		$context['body'] = "officer:heal:$officer_id done";
		event::push_post($tb, $context);

		assert_render($tb->end_txn(), 'officer heal');

		render_ok("officer:heal: $officer_id", $map);
	}

	public static function haste($tb) {
		global $user_id, $general_id, $ops, $officer_id, $status;
		global $OFFICER_UNHIRED, $OFFICER_HIRED, $OFFICER_TRAINING, $OFFICER_HEALING, $OFFICER_DEAD;
		global $OFFICER_RESET_COOLTIME;
		global $OFFICER_HASTE_HEAL_COST_STAR_PER_HOUR;
		global $OFFICER_LIST_RESET_COST_STAR;

		assert_render($officer_id, "invalid:officer_id: $officer_id");

		$officer = officer::select($tb,
				'status, TIMESTAMPDIFF(SECOND, NOW(), status_changed_at) AS remain',
				"officer_id = $officer_id AND status = $OFFICER_HEALING");
		assert_render($officer, "invalid:officer");

		// check star consumption for haste
		$general = general::select($tb, 'star');

		$cost_star = intval(floor($officer['remain']/(3600-1)) + 1);
		$cost_star *= $OFFICER_HASTE_HEAL_COST_STAR_PER_HOUR;

		$cur_star = $general['star'];

		if ( $officer['status'] == $OFFICER_TRAINING )
			$cost_star = $OFFICER_HASTE_HEAL_COST_STAR_PER_HOUR; // DEPRECATED (TRAINING) at 1014

		if ( !($cost_star <= $cur_star) ) {
			$map['cur_star'] = $cur_star;
			$map['cost_star'] = $cost_star;
			$map['fcode'] = 60102;
			render_error("not enough star for haste", $map);
		}

		$terms = [];
		$terms['star'] = "star - $cost_star";
		$pairs = join_terms($terms);
		$query = "UPDATE general SET $pairs WHERE general_id = $general_id";
		assert_render($tb->query_with_affected($query, 1));

		$query = "UPDATE officer SET status_changed_at = NOW() WHERE general_id = $general_id AND officer_id = $officer_id";
		assert_render($tb->query_with_affected($query, 1), "invalid:officer_id: $officer_id not affected");

		officer::check_officer_status_change($general_id, $tb, $officer_id);

		$map['officers'] = officer::select_all($tb, null, "officer_id = $officer_id");

		gamelog(__METHOD__, ['officer'=>$officer]);

		event::push_cancel($tb, "officer:$officer_id");

		assert_render($tb->end_txn());

		render_ok("officer:haste: $officer_id", $map);
	}

	public static function lead($tb) {
		global $user_id, $general_id, $ops, $officer_id, $status;
		global $OFFICER_UNHIRED, $OFFICER_HIRED, $OFFICER_TRAINING, $OFFICER_HEALING, $OFFICER_DEAD;
		global $OFFICER_RESET_COOLTIME;
		global $TROOP_BANDED;

		// cannnot do this if you're running combat
		$general = general::select($tb);
		assert_render($general['running_combat_id'] == null, 'you cannnot do this if you are running combat', FCODE(60301));

		assert_render($officer_id, "invalid:officer_id: $officer_id");

		$officer = officer::select($tb, null, "officer_id = $officer_id AND (status = $OFFICER_HIRED)");
		assert_render($officer, 'invalid:officer');

		$troops = troop::select_all($tb, null, "officer_id = $officer_id AND status = $TROOP_BANDED");
		assert_render($troops, "invalid:officer");

		// for pvp matching
		$ourforce = [];
		$ourforce['general'] = $general;
		$ourforce['officer'] = $officer;
		$ourforce['troops'] = $troops;

		$query = "UPDATE general SET leading_officer_id = $officer_id WHERE general_id = $general_id";
		assert_render($tb->query($query));
		$ourforce['general']['leading_officer_id'] = $officer_id;

		if ( $officer['command_cur'] > 0 ) {
			elog(sprintf("setting army of user_id($user_id) with level %s, command: %s", $officer['level'], $officer['command_cur']));
			global $TAG;

			$terms = [];
			$terms['general_id'] = $general_id;
			$terms['empire'] = $general['country'] == EMPIRE ? "TRUE" : "FALSE";
			$terms['legion'] = !empty($general['legion_id'])  ? "TRUE" : "FALSE";
			$terms['officer_level_command'] = officer::fllc_mux(0, 0, $officer['level'], $officer['command_cur']);
			$terms['brief'] = ms_quote($tb->escape(pretty_json($ourforce)));

			$keys = $vals = [];
			$pairs = join_terms($terms, $keys, $vals);
			$query = "INSERT INTO army ($keys) VALUES ($vals) ON DUPLICATE KEY UPDATE $pairs";
			assert_render($tb->query($query));

			$force_legion_level_command = officer::fllc_mux($general['country'],
					!empty($general['legion_joined_id']),
					$officer['level'], $officer['command_cur']);

			$redis = conn_redis();
			$redis->multi();
			$redis->set("$TAG:users:user_id=$user_id:army", pretty_json($ourforce));
			$redis->zAdd("$TAG:armys:force_legion_level_command", $force_legion_level_command, $user_id);
			$redis->exec();
		} else
			elog("will not set army of user_id($user_id) as officer's command is 0");

		$map['officers'] = officer::select_all($tb, null, "officer_id = $officer_id");

		gamelog(__METHOD__, ['officer'=>$officer]);

		assert_render($tb->end_txn(), 'officer lead');

		render_ok("officer:lead: $officer_id");
	}

	public static function army_dismiss($tb, $with_query = true) {
		global $user_id, $general_id, $ops, $officer_id, $status;

		if ( !$with_query ) {
			$query = "DELETE FROM army WHERE general_id = $general_id";
			assert_render($tb->query($query));
		}

		global $TAG;
		$redis = conn_redis();
		$redis->multi();
		$redis->zRem("$TAG:armys:force_legion_level_command", $user_id);
		$redis->del("$TAG:users:user_id=$user_id:army");
		$redis->exec();
	}

	public static function unlead($tb) {
		global $user_id, $general_id, $ops, $officer_id, $status;
		global $OFFICER_UNHIRED, $OFFICER_HIRED, $OFFICER_TRAINING, $OFFICER_HEALING, $OFFICER_DEAD;
		global $OFFICER_RESET_COOLTIME;

		// cannnot do this if you're running combat
		$general = general::select($tb, 'running_combat_id');
		assert_render($general['running_combat_id'] == null, 'you cannnot do this if you are running combat', FCODE(60301));

		$query = "UPDATE general SET leading_officer_id = NULL WHERE general_id = $general_id";
		assert_render($tb->query($query));

		officer::army_dismiss($tb);

		gamelog(__METHOD__);

		assert_render($tb->end_txn(), 'officer unlead');

		render_ok("officer:unlead");
	}

	public static function promote($tb) {
		global $user_id, $general_id, $ops, $officer_id, $status;
		global $OFFICER_UNHIRED, $OFFICER_HIRED, $OFFICER_TRAINING, $OFFICER_HEALING, $OFFICER_DEAD;
		global $OFFICER_RESET_COOLTIME, $OFFICER_PROMOTE_REQ_QTY;
		global $TROOP_TRAINED;

		assert_render($officer_id, "invalid:officer_id: $officer_id");

		$officer = officer::select($tb, 'type_id, grade', "officer_id = $officer_id");
		assert_render($officer, "invalid:officer_id: $officer_id");

		$type_id = $officer['type_id'];
		$grade = $officer['grade'];

		$GRADES = officer::get_grades();
		assert_render(isset($GRADES[$grade+1]), "max grade: $grade", FCODE(60401));

		$officers = officer::select_all($tb,
				null,
				"status > $OFFICER_UNHIRED AND type_id = $type_id AND grade = $grade",
				"ORDER BY level DESC LIMIT $OFFICER_PROMOTE_REQ_QTY");

		$cur_qty = sizeof($officers);
		assert_render($cur_qty == $OFFICER_PROMOTE_REQ_QTY, "not enough officers for promote: $cur_qty < $OFFICER_PROMOTE_REQ_QTY", FCODE(60402));

		$general = general::select($tb, 'leading_officer_id, item_storage_slot_cur, item_storage_slot_cap');
		$storage_cur = $general['item_storage_slot_cur'];
		$storage_cap = $general['item_storage_slot_cap'];

		// choose right base_officer (level => sum of 4 ranks mods => leading)
		$base_officer = $officers[0];
		$highest_level = $base_officer['level'];
		$highest_rankscore = 0;
		$rank_terms = ['offense_rank', 'defense_rank', 'tactics_rank', 'resists_rank'];

		// check promote req_level
		$promote_req_level = $GRADES[$grade]['max_level'];
		if ( !(dev && queryparam_fetch_int('ignore') > 0) )
			assert_render($base_officer['level'] >= $promote_req_level, "not enough max_level:$promote_req_level", FCODE(60403));

		global $OFFICER_TRAIN_TABLE_NORMAL;
		foreach($officers as $officer) {
			if ( $officer['level'] >= $highest_level ) {
				$rankscore = 0;

				foreach ($rank_terms as $rank) {
					if ( isset($officer[$rank]) && isset($OFFICER_TRAIN_TABLE_NORMAL[$officer[$rank]]['mod']) )
						$rankscore += $OFFICER_TRAIN_TABLE_NORMAL[$officer[$rank]]['mod'];
				}

				if ( $rankscore >= $highest_rankscore ) {
					if ( $rankscore > $highest_rankscore
					|| ($rankscore == $highest_rankscore
							&& $general['leading_officer_id'] == $officer['officer_id']) ) {
						elog("new base_officer selected: " . $officer['officer_id'] . " with rankscore: $rankscore");
						$base_officer = $officer;
					}
					$highest_rankscore = $rankscore;
				}
			}
		}

		$promoted_officer_id = $base_officer['officer_id'];
		$victims_ids = [];

		$querys = [];
		foreach ($officers as $officer) {
			if ( $promoted_officer_id != $officer['officer_id'] )
				$victims_ids[] = $officer['officer_id'];

			// cancel queued pushes (training, healing)
			if ( $officer['status'] == $OFFICER_TRAINING )
				event::push_cancel($tb, "officer:train:" . $officer['$officer_id']);
			else if ( $officer['status'] == $OFFICER_HEALING )
				event::push_cancel($tb, "officer:heal:" . $officer['$officer_id']);

			// disband all troops
			$terms = [];
			$terms['officer_id'] = "NULL";
			$terms['slot'] = "NULL";
			$terms['status'] = $TROOP_TRAINED;
			$pairs = join_terms($terms);

			$query = "UPDATE troop SET $pairs WHERE general_id = $general_id AND officer_id = " . $officer['officer_id'];
			$querys[] = $query;
			// 			assert_render($tb->query($query));

			// disarm all items
			// CHECK, items belonged to officer can be held by storage
			$eids = $officer['equipments'] ?: [];
			if ( sizeof($eids) > 0 ) {
				$equips = [];
					
				$ids = implode(',', $eids);
				$items = item::select_all($tb, null, "item_id IN ($ids)") ?: [];
				foreach ($items as $item) {
					if ( $item['type_major'] == $ITEM_TYPE_MAJOR_EQUIPS )
						$equips[] = $item['item_id'];
				}
					
				// check storage capacity
				$storage_mod = sizeof($equips);
				if ( $storage_cur + $storage_mod > $storage_cap ) {
					$map = [];
					$map['storage_cur'] = $storage_cur;
					$map['storage_cap'] = $storage_cap;
					$map['fcode'] = '26101';
					if ( !(dev && queryparam_fetch_int('ignore') > 0) )
						render_error('not enough storage', $map);
				}
					
				foreach ($equips as $item) {
					elog("changing ownership of item[item_id=$item_id] from officer to general ...");

					// recover ownership to general
					$query = "UPDATE item SET status = $ITEM_GENERAL_OWNED, owner_id = $general_id WHERE item_id = $item_id";
					$querys[] = $query;
				}
					
				if ( $storage_mod > 0 ) {
					// update general.item_storage_slot_cur
					$query = "UPDATE general SET item_storage_slot_cur = item_storage_slot_cur + $storage_mod WHERE general_id = $general_id";
					$querys[] = $query;
				}

				$storage_cur += $storage_mod;
			}
		}

		assert_render($tb->multi_query($querys));

		troop::merge_all_troops_by_type_minor($tb);

		$querys = [];

		$terms = [];
		$terms['status'] = $OFFICER_HIRED; // change status to hired (cancel training, healing, dead)
		$terms['grade'] = $base_officer['grade'] + 1;
		$terms['equipments'] = null; // disarm all
		$terms['command_cur'] = 0;
		$terms['status_changing_at'] = null;
		$terms['status_changed_at'] = null;
		$terms['status_change_context'] = null;

		// recalculate 4 stats
		$OFFICERS = officer::get_officers();
		$OFFICER = $OFFICERS[$type_id];
		$mod_terms = ['offense', 'defense', 'tactics', 'resists']; // columns
		$mods = [];

		foreach ($mod_terms as $mod_term) // discard trained abilities
			$mods[$mod_term] = $OFFICER['grades'][$grade+1][$mod_term];
		for ( $next_level = 2 ; $next_level <= $base_officer['level'] ; $next_level++ ) { // simulate level-up
			$mod_term = $mod_terms[ (($next_level + 2) % sizeof($mod_terms)) ];
			$mods[$mod_term] += 1;
		}
		foreach ($mods as $mod_term => $mod)
			$terms[$mod_term] = $mod;

		$pairs = join_terms($terms);
		$querys[] = "UPDATE officer SET $pairs WHERE general_id = $general_id AND officer_id = $promoted_officer_id";

		// unlead if concerned
		$ids = implode(',', [$promoted_officer_id] + $victims_ids);
		$querys[] = "UPDATE general SET leading_officer_id = NULL WHERE general_id = $general_id AND leading_officer_id IN ($ids)";

		$querys[] = "DELETE FROM army WHERE general_id = $general_id";
		officer::army_dismiss($tb, false);

		// update officer_hired_level_max
		$terms = [];
		$terms['officer_hired_level_max'] = "(SELECT MAX(level) FROM officer WHERE general_id = $general_id AND status > $OFFICER_UNHIRED)";
		$pairs = join_terms($terms);
		$query = "UPDATE general SET $pairs WHERE general_id = $general_id";
		assert_render($tb->query($query));

		if ( sizeof($victims_ids) > 0 ) {
			$pairs = implode(',', $victims_ids);
			$querys[] = "DELETE FROM officer WHERE officer_id IN ($pairs)";
		}
		assert_render($tb->multi_query($querys));

		$map['officers'] = officer::select_all($tb, null, "officer_id = $promoted_officer_id");

		gamelog(__METHOD__, ['source_officers'=>$officers, 'promoted_officer'=>$map['officers'][0]]);

		assert_render($tb->end_txn(), 'officer promote');

		render_ok("officer:promote", $map);
	}

	public static function train($tb) {
		global $user_id, $general_id, $ops, $officer_id, $status;
		global $OFFICER_UNHIRED, $OFFICER_HIRED, $OFFICER_TRAINING, $OFFICER_HEALING, $OFFICER_DEAD;
		global $OFFICER_OFFENSE, $OFFICER_DEFENSE, $OFFICER_TACTICS, $OFFICER_RESISTS;
		global $OFFICER_TRAIN_NORMAL_COST_GOLD, $OFFICER_TRAIN_SPECIAL_COST_STAR;
		global $OFFICER_TRAIN_TABLE_NORMAL, $OFFICER_TRAIN_TABLE_SPECIAL;

		$ability = queryparam_fetch_int('ability');
		assert_render($OFFICER_OFFENSE <= $ability && $ability <= $OFFICER_RESISTS, "invalid:ability:$OFFICER_OFFENSE <= $ability && $ability <= $OFFICER_RESISTS");

		$officer_id = queryparam_fetch_int('officer_id');
		assert_render($officer_id, "invalid:officer_id:$officer_id");
		$officer = officer::select($tb, 'offense_rank, defense_rank, tactics_rank, resists_rank', "officer_id = $officer_id AND status > $OFFICER_UNHIRED");
		assert_render($officer, 'invalid:officer:$officer_id');

		$special_train = queryparam_fetch_int('special_train', 0);

		$gterms = [];
		$general = general::select($tb, 'gold, star');
		if ( $special_train > 0 ) {
			if ( !($OFFICER_TRAIN_SPECIAL_COST_STAR <= $general['star']) ) {
				$map['cost_star'] = $OFFICER_TRAIN_SPECIAL_COST_STAR;
				$map['cur_star'] = $general['star'];
				$map['fcode'] = 60102;
					
				render_error("not enough star", $map);
			}
			$TTABLE = $OFFICER_TRAIN_TABLE_SPECIAL;
			$gterms['star'] = "star - $OFFICER_TRAIN_SPECIAL_COST_STAR";
		} else {
			if ( !($OFFICER_TRAIN_NORMAL_COST_GOLD <= $general['gold']) ) {
				$map['cost_gold'] = $OFFICER_TRAIN_NORMAL_COST_GOLD;
				$map['cur_gold'] = $general['gold'];
				$map['fcode'] = 10104;
					
				render_error("not enough gold or honor", $map);
			}
			$TTABLE = $OFFICER_TRAIN_TABLE_NORMAL;
			$gterms['gold'] = "gold - $OFFICER_TRAIN_NORMAL_COST_GOLD";
		}

		$keys = array_keys($TTABLE);
		$weights = [];
		foreach ($TTABLE as $rank => $val)
			$weights[] = $val['weight'];
			
		$rank = weighted_choice($keys, $weights);

		$rank_terms = ['offense_rank', 'defense_rank', 'tactics_rank', 'resists_rank'];
		$old_rank = null;
		$rank_term = $rank_terms[$ability-1];

		if ( array_key_exists($rank_term, $officer) ) {
			$old_rank = $officer[$rank_term];
			elog("rank_term: $rank_term, old_rank: $old_rank, new_rank: $rank");
			if ( empty($old_rank) || ($OFFICER_TRAIN_TABLE_NORMAL[$rank]['mod'] > $OFFICER_TRAIN_TABLE_NORMAL[$old_rank]['mod']) ) {
				elog("officer $rank_term will be upgrade from [$old_rank] to [$rank]");

				$query = "UPDATE officer SET $rank_term = '$rank' WHERE officer_id = $officer_id";
				assert_render($tb->query($query));
			}
		}

		$pairs = join_terms($gterms);
		$query = "UPDATE general SET $pairs WHERE general_id = $general_id";
		assert_render($tb->query($query));

		$map = [];
		$map['officers'] = officer::select_all($tb, null, "officer_id = $officer_id");
		$map['officers'][0]['new_rank'] = $rank;
		$map['officers'][0]['old_rank'] = $old_rank;

		gamelog(__METHOD__, ['officer'=>$map['officers'][0], 'special_train'=>$special_train, 'rank_term'=>$rank_term]);

		assert_render($tb->end_txn());

		render_ok("officer:train", $map);
	}

	public static function expand_hired_slot_max($tb) {
		global $user_id, $general_id, $ops, $officer_id, $status;
		global $OFFICER_HIRED_MIN, $OFFICER_HIRED_MAX;
		global $OFFICER_EXPAND_SLOT_COST_STAR;

		$general = general::select($tb, 'star, officer_hired_max, level');

		// check max
		$GLEVELS = general::get_levels();
		$officer_hired_max = $GLEVELS[$general['level']]['officer_hired_max'];
		if ( $general['officer_hired_max'] >= $officer_hired_max )
			render_error("officer_hired_max at max: $officer_hired_max", FCODE(60101));

		// check star
		$cur_star = $general['star'];
		if ( $cur_star < $OFFICER_EXPAND_SLOT_COST_STAR )
			render_error("not enough star: $cur_star < $OFFICER_EXPAND_SLOT_COST_STAR", FCODE(60102));

		$terms = [];
		$terms['star'] = "star - $OFFICER_EXPAND_SLOT_COST_STAR";
		$terms['officer_hired_max'] = "officer_hired_max + 1";
		$pairs = join_terms($terms);
		$query = "UPDATE general SET $pairs WHERE general_id = $general_id";

		$map = array();
		$map['star'] = $cur_star - $OFFICER_EXPAND_SLOT_COST_STAR;
		$map['officer_hired_max'] = $general['officer_hired_max'] + 1;

		assert_render($tb->query_with_affected($query, 1));

		gamelog(__METHOD__);

		assert_render($tb->end_txn());

		render_ok("success", $map);
	}

	/**
	 * requires officer_id, item_id
	 * @param unknown $tb
	 */
	public static function item_arm($tb) {
		global $user_id, $general_id, $ops, $officer_id, $status;
		global $OFFICER_UNHIRED, $OFFICER_HIRED, $OFFICER_TRAINING, $OFFICER_HEALING, $OFFICER_DEAD;
		global $OFFICER_RESET_COOLTIME;
		global $OFFICER_ITEM_EQUIP_SLOT_MAX;
		global $ITEM_TYPE_MAJOR_EQUIPS, $ITEM_TYPE_MAJOR_COMBATS, $ITEM_TYPE_MAJOR_CONSUMES;
		global $ITEM_OWNERLESS, $ITEM_MAKING, $ITEM_GENERAL_OWNED, $ITEM_MAIL_OWNED, $ITEM_OFFICER_OWNED;

		assert_render($officer_id, "invalid:officer_id: $officer_id");

		assert_render($item_id = queryparam_fetch_int('item_id'), "invalid:item_id");
		$disarm_item_id = queryparam_fetch_int('disarm_item_id');
		$disarm_item_id = null; // SHOULD not be active

		$officer = officer::select($tb, null, "officer_id = $officer_id");
		assert_render($officer, "invalid:officer_id: $officer_id");

		$equipments = $officer['equipments'] ?: [];

		// check arming item_id
		$item = item::select($tb, null, "item_id = $item_id");
		assert_render($item, "invalid:item_id:$item_id");
		assert_render(in_array($item['type_major'], [$ITEM_TYPE_MAJOR_EQUIPS, $ITEM_TYPE_MAJOR_COMBATS]), "invalid:type_major:equips or combats");

		// check same item
		if ( in_array($item_id, $equipments) ) {
			render_error("duplicated item arm: item_id: $item_id", FCODE(60601));
		}

		// check officer's equip slot capacity
		if ( sizeof($equipments) >= $OFFICER_ITEM_EQUIP_SLOT_MAX && !$disarm_item_id ) {
			render_error("officer slot is full at $OFFICER_ITEM_EQUIP_SLOT_MAX", FCODE(60602));
		}

		$querys = [];

		if ( $disarm_item_id > 0 ) {
			$disarm_item = item::select($tb, null, "item_id = $disarm_item_id");
			assert_render($disarm_item, "invalid:disarm_item_id:$disarm_item_id");
			assert_render(in_array($disarm_item['type_major'], [$ITEM_TYPE_MAJOR_EQUIPS, $ITEM_TYPE_MAJOR_COMBATS]), "invalid:type_major:equips or combats");

			// check storage if disarming is equip AND arming is combat (requires 1 storage slot)
			if ( $disarm_item['type_major'] == $ITEM_TYPE_MAJOR_EQUIPS && $item['type_major'] == $ITEM_TYPE_MAJOR_COMBATS ) {
				// check storage capacity
				$general = general::select($tb, 'item_storage_slot_cur, item_storage_slot_cap');
				$storage_cur = $general['item_storage_slot_cur'];
				$storage_cap = $general['item_storage_slot_cap'];
				$storage_mod = 1;

				if ( $storage_cur + $storage_mod > $storage_cap ) {
					$map = [];
					$map['storage_cur'] = $storage_cur;
					$map['storage_cap'] = $storage_cap;
					$map['fcode'] = '26101';
					if ( !(dev && queryparam_fetch_int('ignore') > 0) )
						render_error('not enough storage', $map);
				}
			}

			$querys[] = "UPDATE item SET status = $ITEM_GENERAL_OWNED, owner_id = $general_id WHERE item_id = $disarm_item_id";

			$new_equips = [];
			foreach ($equipments as $iid) {
				if ( $iid == $disarm_item_id )
					$new_equips[] = $item_id;
				else
					$new_equips[] = $iid;
			}
			$equipments = $new_equips;
		} else
			$equipments[] = $item_id;

		$terms = [];
		$terms['equipments'] = ms_quote(pretty_json($equipments));
		$pairs = join_terms($terms);

		$query = "UPDATE officer SET $pairs WHERE general_id = $general_id AND officer_id = $officer_id";
		assert_render($tb->query($query), "invalid:officer_id: $officer_id, item_id: $item_id");

		// for item-equips, change ownership
		if ( $item['type_major'] == $ITEM_TYPE_MAJOR_EQUIPS ) {
			elog("changing ownership of item[item_id=$item_id] from general to officer...");

			// update general.item_storage_slot_cur if no disarm was performed
			if ( !$disarm_item_id ) {
				elog("adjusting item_storage_slot_cur as we werent replaced");
				$querys[] = "UPDATE general SET item_storage_slot_cur = item_storage_slot_cur - 1 WHERE general_id = $general_id";
			}

			$querys[] = "UPDATE item SET status = $ITEM_OFFICER_OWNED, owner_id = $officer_id WHERE item_id = $item_id";

		}

		if ( sizeof($querys) > 0 )
			assert_render($tb->multi_query($querys));

		$officer['equipments'] = $equipments;

		$map = array();
		$map['officers'][] = $officer;

		gamelog(__METHOD__, ['officer'=>$officer, 'item'=>$item]);

		assert_render($tb->end_txn(), "end_txn: " . __METHOD__);

		render_ok("officer:item_arm: $officer_id, $item_id", $map);
	}

	/**
	 * requires officer_id, item_slot
	 * @param unknown $tb
	 */
	public static function item_disarm($tb) {
		global $user_id, $general_id, $ops, $officer_id, $status;
		global $OFFICER_UNHIRED, $OFFICER_HIRED, $OFFICER_TRAINING, $OFFICER_HEALING, $OFFICER_DEAD;
		global $OFFICER_RESET_COOLTIME;
		global $ITEM_TYPE_MAJOR_EQUIPS, $ITEM_TYPE_MAJOR_COMBATS, $ITEM_TYPE_MAJOR_CONSUMES;
		global $ITEM_OWNERLESS, $ITEM_MAKING, $ITEM_GENERAL_OWNED, $ITEM_MAIL_OWNED, $ITEM_OFFICER_OWNED;

		assert_render($officer_id, "invalid:officer_id: $officer_id");

		assert_render($item_id = queryparam_fetch_int('item_id'), "invalid:item_id");

		$officer = officer::select($tb, null, "officer_id = $officer_id");
		assert_render($officer, "invalid:officer_id: $officer_id");

		$equipments = $officer['equipments'] ?: [];

		assert_render(in_array($item_id, $equipments), "invalid:item_id:$item_id, not in slots");

		// CHECK, equip items belonged to officer can be held by storage
		$item = item::select($tb, null, "item_id = $item_id");
		assert_render($item, "invalid:item_id:$item_id");

		if ( $item['type_major'] == $ITEM_TYPE_MAJOR_EQUIPS ) {
			// check storage capacity
			$general = general::select($tb, 'item_storage_slot_cur, item_storage_slot_cap');
			$storage_cur = $general['item_storage_slot_cur'];
			$storage_cap = $general['item_storage_slot_cap'];
			$storage_mod = 1;

			if ( $storage_cur + $storage_mod > $storage_cap ) {
				$map = [];
				$map['storage_cur'] = $storage_cur;
				$map['storage_cap'] = $storage_cap;
				$map['fcode'] = '26101';
				if ( !(dev && queryparam_fetch_int('ignore') > 0) )
					render_error('not enough storage', $map);
			}

			elog("changing ownership of item[item_id=$item_id] from officer to general ...");

			// update general.item_storage_slot_cur
			$query = "UPDATE general SET item_storage_slot_cur = item_storage_slot_cur + 1 WHERE general_id = $general_id";
			assert_render($tb->query($query));

			// recover ownership to general
			$query = "UPDATE item SET status = $ITEM_GENERAL_OWNED, owner_id = $general_id WHERE item_id = $item_id";
			assert_render($tb->query($query));
		}

		$equipments = array_values(array_diff($equipments, [$item_id]));

		$terms = [];
		$terms['equipments'] = ms_quote(pretty_json($equipments));
		$pairs = join_terms($terms);

		$query = "UPDATE officer SET $pairs WHERE general_id = $general_id AND officer_id = $officer_id";
		assert_render($tb->query($query), "invalid:officer_id: $officer_id, item_id: $item_id");

		$officer['equipments'] = $equipments;

		$map = array();
		$map['officers'][] = $officer;

		gamelog(__METHOD__, ['officer'=>$officer, 'item'=>$item]);

		assert_render($tb->end_txn(), "end_txn: " . __METHOD__);

		render_ok("officer:item_disarm: $officer_id, $item_id", $map);
	}

}

if ( strstr($_SERVER["REQUEST_URI"], basename(__FILE__)) ) {

	$ops = explode(',', $op = queryparam_fetch('op', 'get'));

	$user_id = login_check();
	$general_id = session_GET('general_id');

	$officer_id = queryparam_fetch_int('officer_id');
	$status = queryparam_fetch_int('status');

	if ( sizeof(array_intersect_key(['get', 'reset', 'hire', 'fire', 'clear', 'heal', 'haste', 'lead', 'unlead'] +
			['expand_hired_slot_max', 'promote', 'train']
			, $ops)) ) {
		for ( $retried = 1 ; $retried <= $TXN_RETRY_MAX ; $retried++ ) {
			try {
				$tb = new TxnBlock();

				if ( in_array("clear", $ops) ) officer::clear($tb);
				if ( in_array("reset", $ops) ) officer::reset($tb);

				officer::check_officer_events($tb);

				if ( in_array("hire", $ops) ) officer::hire($tb);
				else if ( in_array("fire", $ops) ) officer::fire($tb);
				else if ( in_array("heal", $ops) ) officer::heal($tb);
				else if ( in_array("haste", $ops) ) officer::haste($tb);
				else if ( in_array("lead", $ops) ) officer::lead($tb);
				else if ( in_array("unlead", $ops) ) officer::unlead($tb);
				else if ( in_array("promote", $ops) ) officer::promote($tb);
				else if ( in_array("train", $ops) ) officer::train($tb);
				else if ( in_array("expand_hired_slot_max", $ops) ) officer::expand_hired_slot_max($tb);
				else if ( in_array("item_arm", $ops) ) officer::item_arm($tb);
				else if ( in_array("item_disarm", $ops) ) officer::item_disarm($tb);

				officer::get($tb); // embedes end_txn()
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
