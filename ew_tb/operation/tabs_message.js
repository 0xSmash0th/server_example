function on_tab_message(tab) {
	if (!logged_in())
		return;

	console.log('on activate on_tab_message: ' + tab.index());

	if (tab.index() == 0)
		on_tabs_message_mailq();
	// if (tab.index() == 1)
	// on_tabs_message_badwords();
}

function message_mailq_update() {
	var mailq_list = JSON.parse(sessionStorage.mailq_list ? sessionStorage.mailq_list : '[]');

	var rows = mailq_list;
	for ( var i = 0; i < rows.length; i++) {
		rows[i]['modify'] = '수정';
		rows[i]['remove'] = '삭제';
	}

	var cols = build_column_header(rows, 'mailq_id');

	set_column_formatter(cols, 'market_type', market_formatter);
	set_column_formatter(cols, 'recv_force', force_formatter);
	set_column_formatter(cols, 'recv_type', recv_type_formatter);	

	$.each([ 'send_force', 'recv_force', 'chat_id', 'general_id', 'user_id', 'modify' ], function(index, value) {
		var col = find_column(cols, value);
		if (col) {
			console.log('col: ' + sjs(col));
			col['maxWidth'] = col['minWidth'] = col['width'] = 80;
		}
	});

	var grid = build_slickgrid($('#tabs_message #mailq_list #grid'), 
			rows, cols, $("#tabs_message #mailq_list #pager"), 10);
	grid.onDblClick.subscribe(function(e, args) {
		var row = args.grid.dataView.getItem(args.row);

		if (args.grid.cols[args.cell].id === 'modify') {
			alert('not yet implemented');
		}
		if (args.grid.cols[args.cell].id === 'remove') {
			var msg = '예약: ' + sjs(row) + ' 을 삭제합니까?';
			if (confirm(msg)) {
				var e = {
					recv_type: row['recv_type'],
					repo_id : row['repo_id']
				};
				$.post('../operation/op_api.php?op=mailq_del', e, function(data) {
					if (d = response_check(data)) {
						sessionStorage.mailq_list = JSON.stringify(d.mailq_list);
						message_mailq_update();
					}
				});
			}
		}
	});
}

function on_tabs_message_mailq() {

	var select = $("#tabs_message #recv_type")[0];
	select.options.length = 0;
	for ( var key in recv_type_map)
		select.options[select.options.length] = new Option(recv_type_map[key], key, false, false);

	var select = $("#tabs_message #recv_market")[0];
	select.options.length = 0;
	for ( var key in market_map)
		select.options[select.options.length] = new Option(market_map[key], key, false, false);

	var select = $("#tabs_message #recv_force")[0];
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
		timeFormat: "HH:mm:00"
	};
	$("#tabs_message #mailq_send_at").datetimepicker(dp_options);

	$('#tabs_message #btn_refresh').unbind();
	$('#tabs_message #btn_refresh').click(function(event) {
		message_mailq_update_post();
	});

	$('#tabs_message #mailq_add').unbind();
	$('#tabs_message #mailq_add').click(function(event) {
		var send_at = $("#tabs_message #mailq_send_at").val();
		var recv_market = $("#tabs_message #recv_market").val();
		var recv_name = $("#tabs_message #recv_name").val();
		var recv_type = $("#tabs_message #recv_type").val();
		var recv_force = $("#tabs_message #recv_force").val();
		var title = $("#tabs_message #mailq_title").val();
		var body = $("#tabs_message #mailq_body").val();

		var mailq_attach_gold = $('#tabs_message #mailq_gift_gold').val();
		var mailq_attach_honor = $('#tabs_message #mailq_gift_honor').val();
		var mailq_attach_star = $('#tabs_message #mailq_gift_star').val();

		var mailq_attach_items = [];
		for ( var i = 1; i <= 4; i++) {
			var type = $('#tabs_message #mailq_gift_item_type_' + i).val();
			var qty = $('#tabs_message #mailq_gift_item_qty_' + i).val();
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

				mailq_attach_items.push(item);
			}
		}

		var mailq_attach = {};
		if (mailq_attach_gold && mailq_attach_gold > 0)
			mailq_attach['gold'] = mailq_attach_gold;
		if (mailq_attach_honor && mailq_attach_honor > 0)
			mailq_attach['honor'] = mailq_attach_honor;
		if (mailq_attach_star && mailq_attach_star > 0)
			mailq_attach['star'] = mailq_attach_star;
		if (mailq_attach_items && mailq_attach_items.length > 0)
			mailq_attach['items'] = mailq_attach_items;

		if (!send_at || send_at == '')
			send_at = null;
				
		if (!body || body == '') {
			alert('empty body');
			return false;
		}
		if ( recv_type == 1 ) {			
			if (!recv_name || recv_name == '') {
				alert('empty recv_name');
				return false;
			}
		}

		var e = {
			recv_type: recv_type,
			recv_force: recv_force,
			send_at : send_at,
			recv_market_type : recv_market,
			recv_name : recv_name,
			title : title,
			body : body,
			gifts : mailq_attach
		};

		$.post('../operation/op_api.php?op=mailq_add', e, function(data) {
			if (d = response_check(data)) {
				sessionStorage.mailq_list = JSON.stringify(d.mailq_list);
				message_mailq_update();
			}
		});
	});

	function message_mailq_update_post() {
		$.post('../operation/op_api.php?op=mailq_list', {}, function(data) {
			if (d = response_check(data)) {
				sessionStorage.mailq_list = JSON.stringify(d.mailq_list);
				message_mailq_update();
			}
		});
	}

	message_mailq_update();
}
