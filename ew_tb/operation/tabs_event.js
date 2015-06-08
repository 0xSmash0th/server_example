function on_tab_event(tab) {
	if (!logged_in())
		return;

	console.log('on activate on_tab_event: ' + tab.index());

	if (tab.index() == 0)
		on_tabs_event_coupon();
	// if (tab.index() == 1)
	// on_tabs_event_badwords();
}

function event_coupon_update() {
	var coupon_list = JSON.parse(sessionStorage.coupon_list ? sessionStorage.coupon_list : '[]');

	var rows = coupon_list;
	for ( var i = 0; i < rows.length; i++) {
		// rows[i]['modify'] = '수정';
		rows[i]['remove'] = '삭제';
	}

	var cols = build_column_header(rows, 'coupon_id');

	set_column_formatter(cols, 'market_type', market_formatter);
	set_column_formatter(cols, 'recv_force', force_formatter);

	$.each([ 'send_force', 'recv_force', 'chat_id', 'general_id', 'user_id', 'modify' ], function(index, value) {
		var col = find_column(cols, value);
		if (col) {
			console.log('col: ' + sjs(col));
			col['maxWidth'] = col['minWidth'] = col['width'] = 80;
		}
	});

	var grid = build_slickgrid($('#tabs_event #coupon_list #grid'), rows, cols, $("#tabs_event #coupon_list #pager"),
			10);
	grid.onDblClick.subscribe(function(e, args) {
		var row = args.grid.dataView.getItem(args.row);

		if (args.grid.cols[args.cell].id === 'modify') {
			alert('not yet implemented');
		}
		if (args.grid.cols[args.cell].id === 'remove') {
			var msg = '쿠폰: ' + sjs(row) + ' 을 삭제합니까?';
			if (confirm(msg)) {
				var e = {
					coupon_id : row['coupon_id']
				};
				$.post('../operation/op_api.php?op=coupon_del', e, function(data) {
					if (d = response_check(data)) {
						sessionStorage.coupon_list = JSON.stringify(d.coupon_list);
						event_coupon_update();
					}
				});
			}
		}
	});
}

function on_tabs_event_coupon() {
	var coupon_search_map = {
		0 : '전체',
		1 : '사용된 것',
		2 : '사용되지 않은 것',
		3 : '사용자 이름',
		4 : '코드'
	};
	var select = $("#tabs_event #search_type")[0];
	select.options.length = 0;
	for ( var key in coupon_search_map)
		select.options[select.options.length] = new Option(coupon_search_map[key], key, false, false);

	var select = $("#tabs_event #recv_market")[0];
	select.options.length = 0;
	for ( var key in market_map)
		select.options[select.options.length] = new Option(market_map[key], key, false, false);

	var select = $("#tabs_event #recv_force")[0];
	select.options.length = 0;
	select.options[select.options.length] = new Option(force_map[3], 3, false, false);
	select.options[select.options.length] = new Option(force_map[1], 1, false, false);
	select.options[select.options.length] = new Option(force_map[2], 2, false, false);

	var dp_options = {
		changeMonth : true,
		changeYear : true,
		numberOfMonths : 1,
		showButtonPanel : true,
		dateFormat : 'yy-mm-dd',
		timeFormat : "HH:mm:00"
	};
	$("#tabs_event #coupon_send_at").datetimepicker(dp_options);

	$('#tabs_event #btn_refresh').unbind();
	$('#tabs_event #btn_refresh').click(function(event) {
		event_coupon_update_post();
	});
	$('#tabs_event #tabs_coupon #search_download').unbind();
	$('#tabs_event #tabs_coupon #search_download').click(function(event) {
		event_coupon_update_post(1);
	});

	$('#tabs_event #coupon_add').unbind();
	$('#tabs_event #coupon_add').click(function(event) {
		var send_at = $("#tabs_event #coupon_send_at").val();
		var recv_market = $("#tabs_event #recv_market").val();
		var recv_name = $("#tabs_event #recv_name").val();
		var recv_force = $("#tabs_event #recv_force").val();
		var title = $("#tabs_event #coupon_title").val();
		var body = $("#tabs_event #coupon_body").val();
		var coupon_qty = $("#tabs_event #coupon_qty").val();

		var coupon_attach_gold = $('#tabs_event #coupon_gift_gold').val();
		var coupon_attach_honor = $('#tabs_event #coupon_gift_honor').val();
		var coupon_attach_star = $('#tabs_event #coupon_gift_star').val();

		var coupon_attach_items = [];
		for ( var i = 1; i <= 4; i++) {
			var type = $('#tabs_event #coupon_gift_item_type_' + i).val();
			var qty = $('#tabs_event #coupon_gift_item_qty_' + i).val();
			if (type > 0) {
				if (!qty || (qty <= 0 || qty >= 100)) {
					alert('invalid qty(allowed: 1~99): ' + qty);
					return false;
				}

				var item = {
					type_minor : type,
					qty : qty
				};
				if (new String(type).charAt(0) === '3')
					item['type_major'] = 3; // consume item
				if (new String(type).charAt(0) === '2')
					item['type_major'] = 1; // combat item

				coupon_attach_items.push(item);
			}
		}

		var coupon_attach = {};
		if (coupon_attach_gold && coupon_attach_gold > 0)
			coupon_attach['gold'] = coupon_attach_gold;
		if (coupon_attach_honor && coupon_attach_honor > 0)
			coupon_attach['honor'] = coupon_attach_honor;
		if (coupon_attach_star && coupon_attach_star > 0)
			coupon_attach['star'] = coupon_attach_star;
		if (coupon_attach_items && coupon_attach_items.length > 0)
			coupon_attach['items'] = coupon_attach_items;

		if (!send_at || send_at == '')
			send_at = null;

		if (!body || body == '') {
			alert('empty body');
			return false;
		}

		var e = {
			coupon_qty : coupon_qty,
			recv_force : recv_force,
			send_at : send_at,
			recv_market_type : recv_market,
			recv_name : recv_name,
			title : title,
			body : body,
			gifts : coupon_attach
		};

		$.post('../operation/op_api.php?op=coupon_add', e, function(data) {
			if (d = response_check(data)) {
				sessionStorage.coupon_list = JSON.stringify(d.coupon_list);
				event_coupon_update();
			}
		});
	});

	function event_coupon_update_post(download) {
		var search_type = $("#tabs_event #search_type").val();
		var search_value = $("#tabs_event #search_value").val();

		var e = {
			download : download > 0 ? 1 : 0,
			search_value : search_value,
			search_type : search_type,
		};
		if (download > 0) {
			$.fileDownload('../operation/op_api.php?op=coupon_list', {
				// preparingMessageHtml : "We are preparing your report, please wait...",
				failMessageHtml : "There was a problem downloading data, please try again.",
				httpMethod : "POST",
				data : e,
			});
		} else {
			$.post('../operation/op_api.php?op=coupon_list', e, function(data) {
				if (d = response_check(data)) {
					sessionStorage.coupon_list = JSON.stringify(d.coupon_list);
					event_coupon_update();
				}
			});
		}
	}

	event_coupon_update();
}
