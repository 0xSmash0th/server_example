
function on_tab_notices(tab) {
	if (!logged_in())
		return;

	console.log('on activate on_tab_notices: ' + tab.index());

	if (tab.index() == 0)
		on_tabs_notices_notice();
	// if (tab.index() == 1)
	// on_tabs_notices_banner();
}

function notices_notice_update() {
	var notice_list = JSON.parse(sessionStorage.notice_list ? sessionStorage.notice_list : '[]');

	var rows = notice_list;
	for ( var i = 0; i < rows.length; i++) {
//		rows[i]['modify'] = '수정';
		rows[i]['remove'] = '삭제';
	}

	var cols = build_column_header(rows, 'notice_id');

	set_column_formatter(cols, 'market_type', market_formatter);
	set_column_formatter(cols, 'recv_force', force_formatter);
	set_column_formatter(cols, 'notice_status', notice_status_formatter);	

	$.each(['notice_id', 'recv_force', 'general_id', 'user_id', 'remove', 'modify' ], function(index, value) {
		var col = find_column(cols, value);
		if (col) {
			console.log('col: ' + sjs(col));
			col['maxWidth'] = col['minWidth'] = col['width'] = 80;
		}
	});

	var grid = build_slickgrid($('#tabs_notices #notice_list #grid'), 
			rows, cols, $("#tabs_notices #notice_list #pager"), 10);
	grid.onDblClick.subscribe(function(e, args) {
		var row = args.grid.dataView.getItem(args.row);

		if (args.grid.cols[args.cell].id === 'modify') {
			alert('not yet implemented');
		}
		if (args.grid.cols[args.cell].id === 'remove') {
			var msg = '예약: ' + sjs(row) + ' 을 삭제합니까?';
			if (confirm(msg)) {
				var e = {
					notice_id : row['notice_id']
				};
				$.post('../operation/op_api.php?op=notice_del', e, function(data) {
					if (d = response_check(data)) {
						sessionStorage.notice_list = JSON.stringify(d.notice_list);
						notices_notice_update();
					}
				});
			}
		}
	});
}

function on_tabs_notices_notice() {

	var select = $("#tabs_notices #recv_market")[0];
	select.options.length = 0;
	for ( var key in market_map)
		select.options[select.options.length] = new Option(market_map[key], key, false, false);

	var select = $("#tabs_notices #recv_force")[0];
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
	$("#tabs_notices #available_after_at").datetimepicker(dp_options);
	$("#tabs_notices #available_before_at").datetimepicker(dp_options);

	$('#tabs_notices #btn_refresh').unbind();
	$('#tabs_notices #btn_refresh').click(function(event) {
		notices_notice_update_post();
	});

	$('#tabs_notices #notice_add').unbind();
	$('#tabs_notices #notice_add').click(function(event) {
		var available_after_at = $("#tabs_notices #available_after_at").val();
		var available_before_at = $("#tabs_notices #available_before_at").val();
		
		var recv_market = $("#tabs_notices #recv_market").val();
		var recv_force = $("#tabs_notices #recv_force").val();
		var body = $("#tabs_notices #notice_body").val();

		if (!available_after_at || available_after_at == '') available_after_at = null;
		if (!available_before_at || available_before_at == '') available_before_at = null;
		
		if (!body || body == '') {
			alert('empty body');
			return false;
		}
		
		var e = {
			recv_force: recv_force,			
			market_type : recv_market,
			available_after_at : available_after_at,
			available_before_at : available_before_at,
			body : body,
			notice_type: 1
		};

		$.post('../operation/op_api.php?op=notice_add', e, function(data) {
			if (d = response_check(data)) {
				sessionStorage.notice_list = JSON.stringify(d.notice_list);
				notices_notice_update();
			}
		});
	});

	function notices_notice_update_post() {
		$.post('../operation/op_api.php?op=notice_list', {}, function(data) {
			if (d = response_check(data)) {
				sessionStorage.notice_list = JSON.stringify(d.notice_list);
				notices_notice_update();
			}
		});
	}

	notices_notice_update();
}
