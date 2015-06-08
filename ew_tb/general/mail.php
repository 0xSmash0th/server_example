<?php
require_once '../connect.php';
require_once '../general/general.php';
require_once '../general/chat.php';
require_once '../army/officer.php';
require_once '../auth/register.php';

class mail {

	public static function select_all($tb, $select_expr = null, $where_condition = null, $post_where = '') {
		global $user_id, $general_id;

		if ( $select_expr == null )
			$select_expr = "*";
		if ( $where_condition == null )
			$where_condition = "WHERE general_id = $general_id";
		else
			$where_condition = "WHERE general_id = $general_id AND ($where_condition)";

		$query = "SELECT $select_expr FROM mail $where_condition $post_where /*BY_HELPER*/";
		assert_render($rows = ms_fetch_all($tb->query($query)));

		$json_keys = array('gifts');

		for ( $i = 0 ; $i < count($rows) ; $i++ ) {
			$cols = $rows[$i];
			foreach ( $json_keys as $json_key ) {
				if ( array_key_exists($json_key, $cols) && $cols[$json_key] ) {
					$cols[$json_key] = @json_decode($cols[$json_key], true) ?: null;
				}
			}
			$rows[$i] = $cols;
		}

		return $rows;
	}

	public static function select($tb, $select_expr = null, $where_condition = null) {
		$rows = mail::select_all($tb, $select_expr, $where_condition);
		if ( $rows && count($rows) == 1 )
			return $rows[0];
		return null;
	}

	public static function clear($tb) {
		global $TAG, $ALLIES, $EMPIRE, $user_id, $general_id, $dev_type, $dev_uuid, $ops, $status, $country;

		if ( dev ) {
			$query = "DELETE FROM mail WHERE general_id = $general_id";
			assert_render($rs = $tb->query($query));

			$query = "UPDATE general SET mail_unchecked = 0 WHERE general_id = $general_id";
			assert_render($rs = $tb->query($query));
		}
	}

	public static function pull_mails($tb) {
		global $TAG, $ALLIES, $EMPIRE, $NEUTRAL, $user_id, $general_id, $dev_type, $dev_uuid, $ops, $status, $country;
		global $MAIL_RECV_TYPE_USER, $MAIL_RECV_TYPE_LEGION, $MAIL_RECV_TYPE_PUBLIC;
		global $FORCE_MAP, $MARKET_TYPE_MAP;

		$general = general::select($tb, 'mail_pulled_public_at, mail_pulled_legion_at');
		$mail_pulled_legion_at = $general['mail_pulled_legion_at'] ?: '1970-01-01 00:00:00';
		$mail_pulled_public_at = $general['mail_pulled_public_at'] ?: '1970-01-01 00:00:00';

		// pull from user
		$pull_count_user = 0;
		$query = "SELECT * FROM mail_repo_user ";
		$query .= "USE INDEX (idx__user_id__pull_after_at) ";
		$query .= "WHERE ";
		$query .= "user_id = $user_id AND ";
		$query .= "pull_after_at <= NOW() ";
		$query .= "ORDER BY pull_after_at";
		assert_render($rows = ms_fetch_all($tb->query($query)));

		elog("will pull from user repo: " . sizeof($rows));
		$querys = [];
		foreach($rows as $row) {
			$repo_id = $row['repo_id'];

			// check we already pulled repo_id
			if ( $row['pull_count'] > 0 ) {
				elog("repo_id: $repo_id was already pulled, skips");
				continue;
			}

			$terms = [];
			$terms['general_id'] = $general_id;
			$terms['sender_id'] = null;
			$terms['type'] = 2; // always system mail
			$terms['created_at'] = ms_quote($row['pull_after_at']);
			$terms['expire_at'] = "TIMESTAMPADD(DAY, 3, NOW())";
			$terms['title'] = ms_quote($row['title']);
			$terms['body'] = ms_quote($row['body']);
			if ( $row['gifts'] ) {
				$terms['expire_at'] = "TIMESTAMPADD(DAY, 7, NOW())";
				$terms['gifts'] = ms_quote($row['gifts']);
			}

			$keys = $vals = [];
			join_terms($terms, $keys, $vals);

			$query = "INSERT INTO mail ($keys) VALUES ($vals)";
			$querys[] = $query;

			$query = "UPDATE mail_repo_user SET pull_count = pull_count + 1 WHERE repo_id = " . $row['repo_id'];
			$querys[] = $query;

			$pull_count_user++;
		}

		if ( sizeof($querys) > 0 ) {
			$terms = [];
			$terms['mail_unchecked'] = "mail_unchecked + " . sizeof($rows);

			$pairs = join_terms($terms);
			$query = "UPDATE general SET $pairs WHERE general_id = $general_id";
			$querys[] = $query;

			assert_render($tb->multi_query($querys));
		}

		// pull from legion
		$pull_count_legion = 0;

		// pull from public (market or force)
		$pull_count_public = 0;
		$my_force = session_GET('country');
		$my_market_type = 0;

		// below conditions are by, SARAGABILITY
		$where_recv_force = 'recv_force >= 2'; // empire, neutral
		if ( $my_force == ALLIES )
			$where_recv_force = 'recv_force <= 2'; // allies, neutral

		$query = "SELECT * FROM mail_repo_public ";
		$query .= "USE INDEX (idx__market_type__recv_force__pull_after_at) ";
		$query .= "WHERE ";
		$query .= "market_type = $my_market_type AND ";
		$query .= "$where_recv_force AND ";
		$query .= "pull_after_at > '$mail_pulled_public_at' AND ";
		$query .= "pull_after_at <= NOW() ";
		$query .= "ORDER BY pull_after_at";
		assert_render($rows = ms_fetch_all($tb->query($query)));

		elog("will pull from public repo: " . sizeof($rows));
		$querys = [];
		foreach($rows as $row) {
			$repo_id = $row['repo_id'];

			// check we already pulled repo_id
			$query = "SELECT COUNT(*) FROM mail_repo_public_pulled WHERE repo_id = $repo_id AND user_id = $user_id";
			$count = ms_fetch_single_cell($tb->query($query));
			if ( $count > 0 ) {
				elog("repo_id: $repo_id was already pulled, skips");
				continue;
			}

			$terms = [];
			$terms['general_id'] = $general_id;
			$terms['sender_id'] = null;
			$terms['type'] = 2; // always system mail
			$terms['created_at'] = ms_quote($row['pull_after_at']);
			$terms['expire_at'] = "TIMESTAMPADD(DAY, 3, NOW())";
			$terms['title'] = ms_quote($row['title']);
			$terms['body'] = ms_quote($row['body']);
			if ( $row['gifts'] ) {
				$terms['expire_at'] = "TIMESTAMPADD(DAY, 7, NOW())";
				$terms['gifts'] = ms_quote($row['gifts']);
			}

			$keys = $vals = [];
			join_terms($terms, $keys, $vals);

			$query = "INSERT INTO mail ($keys) VALUES ($vals)";
			$querys[] = $query;

			$query = "INSERT INTO mail_repo_public_pulled (repo_id, user_id) VALUES ($repo_id, $user_id)";
			$querys[] = $query;

			$query = "UPDATE mail_repo_public SET pull_count = pull_count + 1 WHERE repo_id = " . $row['repo_id'];
			$querys[] = $query;

			$pull_count_public++;
		}

		if ( sizeof($querys) > 0 ) {
			$terms = [];
			$terms['mail_unchecked'] = "mail_unchecked + " . sizeof($rows);
			$terms['mail_pulled_public_at'] = 'NOW()';

			$pairs = join_terms($terms);
			$query = "UPDATE general SET $pairs WHERE general_id = $general_id";
			$querys[] = $query;

			assert_render($tb->multi_query($querys));
		}

		if ( $pull_count_user > 0 || $pull_count_legion > 0 || $pull_count_public > 0 ) {
			$detail = [];
			$detail['pull_count_user'] = $pull_count_user;
			$detail['pull_count_legion'] = $pull_count_legion;
			$detail['pull_count_public'] = $pull_count_public;

			gamelog(__METHOD__, $detail);
		}
	}

	public static function coupon_redeem($tb) {
		global $TAG, $ALLIES, $EMPIRE, $NEUTRAL, $user_id, $general_id, $dev_type, $dev_uuid, $ops, $status, $country;
		global $FORCE_MAP, $MARKET_TYPE_MAP;

		$code = queryparam_fetch('code', '');
		assert_render(strlen($code) == (16+3), "invalid:code:$code", FCODE(26401));

		$query = "SELECT * FROM coupon_repo WHERE code = " . ms_quote($tb->escape($code));
		$coupon = ms_fetch_one($tb->query($query));

		assert_render($coupon, "invalid:code:$code", FCODE(26401));
		assert_render(empty($coupon['redeem_user_id']), "invalid:code:already redeemed", FCODE(26402));

		elog("redeeming coupon[$code] for user_id: $user_id");

		$terms = [];
		$terms['general_id'] = $general_id;
		$terms['sender_id'] = null;
		$terms['type'] = 2; // always system mail
		$terms['created_at'] = ms_quote($coupon['pull_after_at']);
		$terms['expire_at'] = "TIMESTAMPADD(DAY, 3, NOW())";
		$terms['title'] = ms_quote($coupon['title']);
		$terms['body'] = ms_quote($coupon['body']);
		if ( $coupon['gifts'] ) {
			$terms['expire_at'] = "TIMESTAMPADD(DAY, 7, NOW())";
			$terms['gifts'] = ms_quote($coupon['gifts']);
		}

		$keys = $vals = [];
		join_terms($terms, $keys, $vals);

		$querys[] = "INSERT INTO mail ($keys) VALUES ($vals)";
		$querys[] = "UPDATE coupon_repo SET redeem_user_id = $user_id WHERE code = " . ms_quote($tb->escape($code));
		$querys[] = "UPDATE general SET mail_unchecked = mail_unchecked + 1 WHERE general_id = $general_id";;

		assert_render($tb->multi_query($querys));

		$detail = [];
		$detail['coupon'] = $coupon;

		gamelog(__METHOD__, $detail);

		assert_render($tb->end_txn());

		render_ok('success');
	}

	public static function get($tb) {
		global $TAG, $ALLIES, $EMPIRE, $user_id, $general_id, $dev_type, $dev_uuid, $ops, $status, $country;

		mail::pull_mails($tb);

		$archived = queryparam_fetch_int('archived', 0);
		$type = queryparam_fetch_int('type');
		$with_detail = queryparam_fetch_int('with_detail');

		if ( $archived > 0 )
			$type = 0;
		else
			assert_render($type == 2 || $type == 3, "invalid:type:2 or 3 but $type");

		$where = sprintf("archived = %s AND %s AND (expire_at IS NULL OR NOW() <= expire_at)",
				$archived ? "TRUE" : "FALSE",
				$archived > 0 ? "type > 1" : "type = $type");
		$post_where = "ORDER BY mail_id DESC LIMIT 100";

		$mails = mail::select_all($tb, "*", $where, $post_where);

		$no_badwords_filter = queryparam_fetch_int('no_badwords_filter') > 0;
		$BADWORDS = $no_badwords_filter ? null : chat::get_badwords($tb);
		foreach ($mails as &$mail) {
			$mail['gifts_acquire_done'] = !empty($mail['gifts']['acquireds']['done']);

			if ( $with_detail > 0 ) {
				$mail['gifts_detail'] = $mail['gifts'];
			} else
				$mail['body'] = null;

			$mail['gifts'] = !empty($mail['gifts']);

			if ( !$no_badwords_filter ) {
				$mail['body'] = chat::chat_badword_filter($tb, $mail['body'], '*', $BADWORDS);
				$mail['title'] = chat::chat_badword_filter($tb, $mail['title'], '*', $BADWORDS);
			}
		}

		$map['mails'] = $mails;

		assert_render($tb->end_txn());

		render_ok('success', $map);
	}

	public static function detail($tb) {
		global $TAG, $ALLIES, $EMPIRE, $user_id, $general_id, $dev_type, $dev_uuid, $ops, $status, $country;

		$mail_id = queryparam_fetch_int('mail_id');
		assert_render($mail_id > 0, "invalid:mail_id:$mail_id");

		// 		$query = "SELECT mail.*, user.username FROM mail, user ON mail.general_id = user.user_id WHERE general_id = $general_id AND mail_id = $mail_id";
		// 		$mail = ms_fetch_one($tb->query($query));
		// 		$mail = mail::select($tb, "*", "mail_id = $mail_id");
		$mail = mail::select($tb, "*, (SELECT username FROM user WHERE user_id = sender_id) AS sender_name", "mail_id = $mail_id");
		assert_render($mail, "invalid:mail_id:$mail_id");

		// make me checked
		if ( !$mail['checked'] ) {
			$querys = [];

			$query = "UPDATE mail SET checked = TRUE WHERE mail_id = $mail_id";
			$querys[] = $query;

			$query = "UPDATE general SET mail_unchecked = mail_unchecked - 1 WHERE general_id = $general_id";
			$querys[] = $query;

			assert_render($tb->multi_query($querys));

			$mail['checked'] = true;
		}

		if ( empty($mail['sender_id']) )
			$mail['sender_name'] = '운영자/관리자'; // TODO: get me from table

		// do filterings
		$no_badwords_filter = queryparam_fetch_int('no_badwords_filter') > 0;
		$BADWORDS = $no_badwords_filter ? null : chat::get_badwords($tb);
		if ( !$no_badwords_filter )
			$mail['body'] = chat::chat_badword_filter($tb, $mail['body'], '*', $BADWORDS);

		// get items detail
		$mail['gifts_detail'] = $mail['gifts'];
		$mail['gifts'] = !empty($mail['gifts']);
				
		$map['mails'] = [$mail];

		gamelog(__METHOD__, ['mail'=>$mail]);

		assert_render($tb->end_txn());

		render_ok('success', $map);
	}

	public static function delete($tb) {
		global $TAG, $ALLIES, $EMPIRE, $user_id, $general_id, $dev_type, $dev_uuid, $ops, $status, $country;

		$raw_mail_ids = queryparam_fetch('mail_ids');
		$mail_ids = @json_decode($raw_mail_ids) ?: [];
		assert_render(sizeof($mail_ids), "invalid:mail_ids:$raw_mail_ids");
		$ids = implode(',', $mail_ids);

		$query = "SELECT COUNT(*) FROM mail WHERE general_id = $general_id AND mail_id IN ($ids) AND checked = FALSE";
		assert_render($unchecked = ms_fetch_single_cell($tb->query($query)));

		if ( $unchecked > 0 ) {
			elog("deleting $unchecked unchecked mails ...");

			$query = "UPDATE general SET mail_unchecked = IF(mail_unchecked - $unchecked < 0, 0, mail_unchecked - $unchecked) WHERE general_id = $general_id";
			assert_render($tb->query($query));
		}

		$query = "UPDATE mail SET type = 1 WHERE mail_id IN ($ids)";
		assert_render($tb->query($query));

		gamelog(__METHOD__, ['mail_ids'=>$mail_ids]);
			
		assert_render($tb->end_txn());

		render_ok('success');
	}

	public static function archive($tb) {
		global $TAG, $ALLIES, $EMPIRE, $user_id, $general_id, $dev_type, $dev_uuid, $ops, $status, $country;
		global $MAIL_ARCHIVE_MAX;

		$raw_mail_ids = queryparam_fetch('mail_ids');
		$mail_ids = @json_decode($raw_mail_ids) ?: [];
		assert_render(sizeof($mail_ids), "invalid:mail_ids:$raw_mail_ids");
		$ids = implode(',', $mail_ids);

		// check before archive
		$query = "SELECT COUNT(*) FROM mail WHERE general_id = $general_id AND ";
		$query .= "archived = TRUE AND type > 1";
		$cur_archived = ms_fetch_single_cell($tb->query($query));

		if ( sizeof($mail_ids) + $cur_archived > $MAIL_ARCHIVE_MAX ) {
			$map['cur_archived'] = $cur_archived;
			$map['max_archived'] = $MAIL_ARCHIVE_MAX;
			$map['fcode'] = 26301;
			render_error('archive count will reach at max', $map);
		}

		$query = "UPDATE mail SET archived = TRUE, expire_at = NULL WHERE mail_id IN ($ids)";
		assert_render($tb->query($query));

		gamelog(__METHOD__, ['mail_ids'=>$mail_ids]);

		assert_render($tb->end_txn());

		render_ok('success');
	}

	public static function acquire($tb) {
		global $TAG, $ALLIES, $EMPIRE, $user_id, $general_id, $dev_type, $dev_uuid, $ops, $status, $country;
		global $GENERAL_RESOURCE_MAX;

		$mail_id = queryparam_fetch_int('mail_id');
		assert_render($mail_id > 0, "invalid:mail_id:$mail_id");

		$acquire_id_int = queryparam_fetch_int('acquire_id');
		$acquire_id_str = queryparam_fetch('acquire_id');

		$mail = mail::select($tb, "gifts", "mail_id = $mail_id");
		assert_render($mail, "invalid:mail_id:$mail_id");

		$acquire_items = isset($mail['gifts']['items']) ? $mail['gifts']['items'] : [];
		$acquireds = isset($mail['gifts']['acquireds']) ? $mail['gifts']['acquireds'] : [];
		$new_gifts = isset($mail['gifts']) ? $mail['gifts'] : [];
		$new_gifts['items'] = [];
		$new_gifts['acquireds'] = [];

		if ( !empty($acquireds['done']) )
			render_error("you acquired all gifts from this mail");

		$querys = [];
		if ( $acquire_id_str == 'all' ) {
			$terms = [];
			foreach ( ['gold', 'honor', 'star'] as $gt ) {
				if ( !empty($mail['gifts'][$gt]) ) {
					$mod = $mail['gifts'][$gt];
					$terms[$gt] = "IF($gt + $mod > $GENERAL_RESOURCE_MAX, $GENERAL_RESOURCE_MAX, $gt + $mod)";

					$acquireds[$gt] = $mod; // move to acquireds
					unset($new_gifts[$gt]);
				}
			}
			if ( sizeof($terms) > 0 ) {
				$pairs = join_terms($terms);
				$querys[] = "UPDATE general SET $pairs WHERE general_id = $general_id";
			}
		} else {
			$acquire_item = null;
			foreach ($acquire_items as $item) {
				if ( $acquire_id_int > 0 && $acquire_id_int == $item['item_id'] )
					$acquire_item = $item;
				else
					$new_gifts['items'][] = $item;
			}
			assert_render($acquire_item, "invalid:acquire_id:not found");
			$acquire_items = [$acquire_item];
		}

		// move to acquireds
		foreach ($acquire_items as $acquire_item) {
			if ( !isset($acquireds['items']) )
				$acquireds['items'] = [];
			$acquireds['items'][] = $acquire_item;
		}

		// completes new gifts and  update mail with this
		$new_gifts['acquireds'] = $acquireds;
		$new_gifts['acquireds']['done'] = 0;
		if ( sizeof($new_gifts) == 2 && sizeof($new_gifts['items']) == 0  )
			$new_gifts['acquireds']['done'] = 1;
		$ejs = ms_quote($tb->escape(pretty_json($new_gifts)));

		$query = "UPDATE mail SET gifts = $ejs WHERE mail_id = $mail_id";
		assert_render($tb->query($query));

		elog("updated mail_id($mail_id).gifts: $ejs");

		// lazy item row insertion here with implicit item_id,
		// copied from item::gifts at 2013.11.10
		global $ITEM_OWNERLESS, $ITEM_MAKING, $ITEM_GENERAL_OWNED, $ITEM_MAIL_OWNED, $ITEM_OFFICER_OWNED;
		global $ITEM_TYPE_MAJOR_EQUIPS, $ITEM_TYPE_MAJOR_COMBATS, $ITEM_TYPE_MAJOR_CONSUMES;

		foreach ($acquire_items as $acquire_item) {
			$qty = $acquire_item['qty'];
			$type_major = $acquire_item['type_major'];
			$type_minor = $acquire_item['type_minor'];

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
		}

		gamelog(__METHOD__, ['mail'=>$mail, 'acquire'=>$acquire_id_str]);

		assert_render($tb->end_txn());

		render_ok('success');
	}

	public static function enqueue($tb) {
		global $TAG, $ALLIES, $EMPIRE, $user_id, $general_id, $dev_type, $dev_uuid, $ops, $status, $country;
		global $MAIL_RECV_TYPE_USER, $MAIL_RECV_TYPE_LEGION, $MAIL_RECV_TYPE_PUBLIC;
		global $FORCE_MAP, $MARKET_TYPE_MAP;

		assert_render(auth::acl_check(['operator']), "invalid:acl");

		$title = queryparam_fetch('title') ?: '';
		$body = queryparam_fetch('body') ?: '';

		$title = trim($title);
		$body = trim($body);

		assert_render(strlen($title) > 0, "invalid:title:length==0", FCODE(26202));
		assert_render(strlen($body) > 0, "invalid:body:length==0", FCODE(26203));

		global $MAIL_TITLE_LIMIT, $MAIL_BODY_LIMIT;
		assert_render(strlen($title) <= $MAIL_TITLE_LIMIT, "invalid:title:exceed limit:$MAIL_TITLE_LIMIT:" . strlen($title), FCODE(26205));
		assert_render(strlen($body) <= $MAIL_BODY_LIMIT, "invalid:body:exceed limit:$MAIL_BODY_LIMIT", FCODE(26206));

		// take gifts
		$gifts = queryparam_fetch('gifts');
		if ( !is_array($gifts) )
			$gifts = @json_decode(queryparam_fetch('gifts'), true) ?: null;

		elog("attaching gifts: " . pretty_json($gifts));

		if ( !empty($gifts['items']) ) {
			$item_id_seq = 1;
			foreach ($gifts['items'] as &$gift_item) {
				// put implicit item_id as we want lazy item.row insertion when we acquire
				$gift_item['item_id'] = $item_id_seq;
				$item_id_seq++;
			}
		}

		$recv_type = queryparam_fetch_int('recv_type');

		$terms = [];

		if ( $recv_type == $MAIL_RECV_TYPE_USER ) {
			$recv_name = queryparam_fetch('recv_name');
			assert_render(strlen($recv_name) > 0, "invalid:recv_name:$recv_name", FCODE(26204));

			$recv_name = queryparam_fetch('recv_name');

			// TODO: check recv_name is available
			// 			$terms['recv_legion_id'] = $recv_legion_id;

			$target_db = "mail_repo_user";

		} else if ( $recv_type == $MAIL_RECV_TYPE_LEGION ) {
			$recv_legion_id = queryparam_fetch_int('recv_legion_id');
			assert_render($recv_legion_id > 0, "invalid:recv_legion_id:$recv_legion_id", FCODE(26204));

			$recv_legion_id = queryparam_fetch_int('recv_legion_id');

			// TODO: check recv_legion_id is available
			$terms['recv_legion_id'] = $recv_legion_id;
			$target_db = "mail_repo_legion";

		} else if ( $recv_type == $MAIL_RECV_TYPE_PUBLIC ) {
			$recv_force = queryparam_fetch_int('recv_force');
			assert_render($recv_force > 0, "invalid:recv_force:$recv_force", FCODE(26204));

			$market_type = queryparam_fetch_int('market_type', 0);
			assert_render(array_key_exists($market_type, $MARKET_TYPE_MAP), "invalid:market_type:$market_type");
			assert_render($recv_force == ALLIES || $recv_force == EMPIRE, "invalid:recv_force:$recv_force");

			$terms['market_type'] = $market_type;
			$terms['recv_allies'] = $recv_force == ALLIES ? "TRUE" : "FALSE";
			$terms['recv_empire'] = $recv_force == EMPIRE ? "TRUE" : "FALSE";
			$target_db = "mail_repo_public";

		} else
			render_error("invalid:recv_type:$recv_type");

		$pull_after_at = queryparam_fetch('pull_after_at');

		$terms['pull_after_at'] = $pull_after_at ? ms_quote($tb->escape($pull_after_at)) : 'NOW()';
		$terms['title'] = ms_quote($tb->escape($title));
		$terms['body'] = ms_quote($tb->escape($body));
		$terms['gifts'] = $gifts ? ms_quote($tb->escape(pretty_json($gifts))) : null;

		$keys = $vals = [];
		join_terms($terms, $keys, $vals);

		$query = "INSERT INTO $target_db ($keys) VALUES ($vals)";
		assert_render($tb->query($query));

		$repo_id = $tb->mc()->insert_id;

		$query = "SELECT * FROM $target_db WHERE repo_id = $repo_id";
		$mail_queued = ms_fetch_one($tb->query($query));
		if ( !empty($mail_queued['gifts']) )
			$mail_queued['gifts'] = @json_decode($mail_queued['gifts'], true);

		gamelog(__METHOD__, ['mail_queued'=>$mail_queued]);

		assert_render($tb->end_txn());

		render_ok('queued');
	}

	public static function send($tb) {
		global $TAG, $ALLIES, $EMPIRE, $user_id, $general_id, $dev_type, $dev_uuid, $ops, $status, $country;
		global $MAIL_RECV_TYPE_USER, $MAIL_RECV_TYPE_LEGION, $MAIL_RECV_TYPE_PUBLIC;
		global $FORCE_MAP, $MARKET_TYPE_MAP;

		$title = queryparam_fetch('title') ?: '';
		$body = queryparam_fetch('body') ?: '';

		$title = trim($title);
		$body = trim($body);

		assert_render(strlen($title) > 0, "invalid:title:length==0", FCODE(26202));
		assert_render(strlen($body) > 0, "invalid:body:length==0", FCODE(26203));

		global $MAIL_TITLE_LIMIT, $MAIL_BODY_LIMIT;
		assert_render(strlen($title) <= $MAIL_TITLE_LIMIT, "invalid:title:exceed limit:$MAIL_TITLE_LIMIT:" . strlen($title), FCODE(26205));
		assert_render(strlen($body) <= $MAIL_BODY_LIMIT, "invalid:body:exceed limit:$MAIL_BODY_LIMIT", FCODE(26206));

		// take gifts
		$gifts = null;
		if ( auth::acl_check(['operator']) ) {
			$gifts = queryparam_fetch('gifts');
			if ( !is_array($gifts) )
				$gifts = @json_decode(queryparam_fetch('gifts'), true) ?: null;

			elog("attaching gifts: " . pretty_json($gifts));

			if ( !empty($gifts['items']) ) {
				$item_id_seq = 1;
				foreach ($gifts['items'] as &$gift_item) {
					// put implicit item_id as we want lazy item.row insertion when we acquire
					$gift_item['item_id'] = $item_id_seq;
					$item_id_seq++;
				}
			}
		}

		// check receiver is available
		$recv_name = queryparam_fetch('recv_name');
		$es = $tb->escape($recv_name);
		$query = "SELECT * FROM user WHERE username = '$es'";
		$recver = ms_fetch_one($tb->query($query));
		assert_render($recver, "invalid:recv_name:$recv_name", FCODE(26201));
		$recv_id = $recver['user_id'];

		//		assert_render($recv_id == $user_id, "invalid:recv_id:cannot send to self", FCODE(26207));

		$terms = [];
		$terms['general_id'] = $recv_id;
		$terms['sender_id'] = $general_id;
		$terms['type'] = 3;
		$terms['created_at'] = 'NOW()';
		$terms['expire_at'] = "TIMESTAMPADD(DAY, 3, NOW())";
		$terms['title'] = ms_quote($tb->escape($title));
		$terms['body'] = ms_quote($tb->escape($body));

		if ( auth::acl_check(['operator']) ) {
			$terms['sender_id'] = null;
			$terms['type'] = 2;
			if ( $gifts ) {
				$terms['expire_at'] = "TIMESTAMPADD(DAY, 7, NOW())";
				// only operator can attach gifts to message
				$terms['gifts'] = ms_quote($tb->escape(pretty_json($gifts)));
			}
		}

		$keys = $vals = [];
		join_terms($terms, $keys, $vals);

		$query = "INSERT INTO mail ($keys) VALUES ($vals)";
		assert_render($tb->query($query));

		$mail_id = $tb->mc()->insert_id;

		$query = "UPDATE general SET mail_unchecked = mail_unchecked + 1 WHERE general_id = $recv_id";
		assert_render($tb->query($query));

		$mail = mail::select($tb, null, "mail_id = $mail_id");
		gamelog(__METHOD__, ['mail'=>$mail]);

		// post push on system message
		if ( auth::acl_check(['operator']) ) {
			$context = [];
			$context['user_id'] = $recv_id;
			$context['dev_type'] = $recver['dev_type'];
			$context['dev_uuid'] = $recver['dev_uuid'];
			$context['src_id'] = "mail:inbox:$mail_id";
			$context['send_at'] = "NOW()";
			$context['body'] = sprintf("mail:inbox:$mail_id from sender(%s) done", $terms['sender_id']);
			event::push_post($tb, $context);
		}

		assert_render($tb->end_txn());

		render_ok('sent');
	}

}

if ( strstr($_SERVER["REQUEST_URI"], basename(__FILE__)) ) {

	$ops = explode(',', $op = queryparam_fetch('op', 'get'));

	$user_id = login_check();
	$general_id = session_GET('general_id');

	$status = queryparam_fetch_int('status');

	$reset_daily_mail = false;

	if ( sizeof(array_intersect_key(['get', 'clear', 'detail', 'delete', 'archive', 'acquire', 'send', 'enqueue', 'coupon_redeem'], $ops)) ) {
		for ( $retried = 1 ; $retried <= $TXN_RETRY_MAX ; $retried++ ) {
			try {
				$tb = new TxnBlock();

				if ( in_array("clear", $ops) ) mail::clear($tb);

				if ( in_array("detail", $ops) ) mail::detail($tb);
				else if ( in_array("delete", $ops) ) mail::delete($tb);
				else if ( in_array("archive", $ops) ) mail::archive($tb);
				else if ( in_array("acquire", $ops) ) mail::acquire($tb);
				else if ( in_array("send", $ops) ) mail::send($tb);
				else if ( in_array("enqueue", $ops) ) mail::enqueue($tb);
				else if ( in_array("coupon_redeem", $ops) ) mail::coupon_redeem($tb);				
				
				mail::get($tb); // embedes end_txn()
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
