function on_tab_user(tab) {
	if (!logged_in())
		return;

	console.log('on activate on_tab_user: ' + tab.index());

	if (tab.index() == 0)
		on_tabs_user_manage();
	if (tab.index() == 1)
		on_tabs_user_detail();
}

function on_tab_user_detail(tab) {
	console.log('on activate on_tab_user_detail: ' + tab.index());

	if (tab.index() == 0)
		on_tab_user_detail_general();
	if (tab.index() == 1)
		on_tab_user_detail_skill();
	if (tab.index() == 2)
		on_tab_user_detail_badge();
}

function tabs_user_manage_update() {
	var user_list = JSON.parse(sessionStorage.user_list ? sessionStorage.user_list : '[]');
	var user_summary = JSON.parse(sessionStorage.user_summary ? sessionStorage.user_summary : '{}');

	var scols = [];
	for ( var key in user_summary)
		scols.push({
			id : key,
			name : key,
			field : key
		});
	if (scols.length > 0) {
		var srows = [ user_summary ];
		var scols = build_column_header(srows, 'all');
		// console.log(sjs(scols));
		var grid = build_slickgrid($('#tabs_user #tabs_manage #user_summary #grid'), srows, scols);
//		var gcols = grid.getColumns();
//		gcols[gcols.length - 1].minWidth = 1;
//		gcols[gcols.length - 1].maxWidth = 1;
//		gcols[gcols.length - 1].width = 1;
//		grid.setColumns(gcols);
	}

	var fields = [ 'user_id', 'username', 'market_type', 'user_status', 'line', 'payment_sum', 'created_at', 'login_at' ];
	var cols = build_column_header(user_list, 'user_id', fields);
	// console.log(sjs(cols));
	set_column_formatter(cols, 'market_type', market_formatter);
	set_column_formatter(cols, 'user_status', status_formatter);
	set_column_formatter(cols, 'line', line_formatter);
	set_column_formatter(cols, 'reason', reason_formatter);

	var grid = build_slickgrid($('#tabs_user #tabs_manage #user_list #grid'), user_list, cols,
			$("#tabs_user #tabs_manage #user_list #pager"), 10);

	grid.onDblClick.subscribe(function(e, args) {
		var row = args.grid.dataView.getItem(args.row);

		if (args.grid.cols[args.cell].id === 'kick') {

		}
		if (args.grid.cols[args.cell].id === 'user_status') {
			user_status_dialog_handler(e, args);
		}
		if (args.grid.cols[args.cell].id === 'username') {
			console.log('row: ' + sjs(row));

			sessionStorage.user_detail_selected = sjs(row);
			$("#tabs_user").tabs("option", "active", 1);
		}
	});
}

function on_tabs_user_manage() {
	$("#user_status_dialog").dialog({
		autoOpen : false,
		modal : true
	});

	var select = $("#tabs_user #tabs_manage #search_market")[0];
	select.options.length = 0;
	for ( var key in market_map)
		select.options[select.options.length] = new Option(market_map[key], key, false, false);

	var select = $("#tabs_user #tabs_manage #search_online")[0];
	select.options.length = 0;
	for ( var key in line_map)
		select.options[select.options.length] = new Option(line_map[key], key, false, false);

	var select = $("#tabs_user #tabs_manage #search_account_status")[0];
	select.options.length = 0;
	for ( var key in status_map)
		select.options[select.options.length] = new Option(status_map[key], key, false, false);

	var select = $("#tabs_user #tabs_manage #search_access_type")[0];
	select.options.length = 0;
	for ( var key in access_map)
		select.options[select.options.length] = new Option(access_map[key], key, false, false);

	var dp_options = {
		changeMonth : true,
		changeYear : true,
		numberOfMonths : 1,
		showButtonPanel : true,
		dateFormat : 'yy-mm-dd'
	};
	$("#tabs_user #tabs_manage #user_manage_search_date_from").datepicker(dp_options);
	$("#tabs_user #tabs_manage #user_manage_search_date_to").datepicker(dp_options);

	var search_run = $("#tabs_user #tabs_manage #search_run");
	search_run.unbind();
	search_run.click(function(event) {
		tabs_user_manage_update_post();
	});
	var search_download = $("#tabs_user #tabs_manage #search_download");
	search_download.unbind();
	search_download.click(function(event) {
		tabs_user_manage_update_post(1);
	});

	function tabs_user_manage_update_post(download) {
		var date_fr = $("#tabs_user #tabs_manage #user_manage_search_date_from").val();
		var date_to = $("#tabs_user #tabs_manage #user_manage_search_date_to").val();
		var username = $("#tabs_user #tabs_manage #search_username").val();
		var market_type = $("#tabs_user #tabs_manage #search_market").val();
		var line_type = $("#tabs_user #tabs_manage #search_online").val();
		var status_type = $("#tabs_user #tabs_manage #search_account_status").val();
		var access_type = $("#tabs_user #tabs_manage #search_access_type").val();
		var payment_min = $("#tabs_user #tabs_manage #search_payment_min").val();
		var payment_max = $("#tabs_user #tabs_manage #search_payment_max").val();

		var e = {
			bgn_at : date_fr,
			end_at : date_to,
			market_type : market_type,
			line_type : line_type,
			status_type : status_type,
			access_type : access_type,
			payment_min : payment_min,
			payment_max : payment_max,
			username : username,
			download : (download > 0) ? 1 : 0,
		};

		if (download > 0) {
			$.fileDownload('../operation/op_api.php?op=user_list', {
				// preparingMessageHtml : "We are preparing your report, please wait...",
				failMessageHtml : "There was a problem downloading data, please try again.",
				httpMethod : "POST",
				data : e,
			});
		} else {
			$.post('../operation/op_api.php?op=user_list', e, function(data) {
				if (d = response_check(data)) {
					sessionStorage.user_list = JSON.stringify(d.user_list);
					sessionStorage.user_summary = JSON.stringify(d.user_summary);
					tabs_user_manage_update();
				}
			});
		}
	}

	tabs_user_manage_update();
}

var user_status_dialog_handler = function(e, args) {
	var row = args.grid.dataView.getItem(args.row);
	console.log('row: ' + sjs(row));

	var dialog = $("#user_status_dialog");
	dialog.dialog("option", "row", row);
	dialog.dialog("option", "title", "계정 상태 변경");
	var width = $(window).width();
	dialog.dialog("option", "width", width * 0.8);
	dialog.dialog("option", "buttons", [ {
		text : "확인(상태 변경)",
		click : function() {
			var e = {
				user_id : row['user_id'],
				new_status : $("#user_status_dialog #new_status").val(),
				reason : $("#user_status_dialog #reason").val(),
				status_date_from : $("#user_status_dialog #status_date_from").val(),
				status_date_to : $("#user_status_dialog #status_date_to").val()
			};
			console.log('e: ' + sjs(e));
			$.post('../operation/op_api.php?op=user_penalty_mod', e, function(data) {
				if (d = response_check(data)) {
				}
			});
			$(this).dialog("close");
		}
	}, {
		text : "취소",
		click : function() {
			$(this).dialog("close");
		}
	} ]);
	$("#user_status_dialog #username").val(row['username']);

	var select = $("#user_status_dialog #new_status")[0];
	select.options.length = 0;
	for ( var key in status_map)
		select.options[select.options.length] = new Option(status_map[key], key, false, false);

	var select = $("#user_status_dialog #reason")[0];
	select.options.length = 0;
	for ( var key in reason_map)
		select.options[select.options.length] = new Option(reason_map[key], key, false, false);

	$("#user_status_dialog #new_status").val(row['user_status']);
	// $("#user_status_dialog #reason").val(row['user_status']);

	var dp_options = {
		changeMonth : true,
		changeYear : true,
		numberOfMonths : 1,
		showButtonPanel : true,
		dateFormat : 'yy-mm-dd'
	};
	$("#user_status_dialog #status_date_from").datepicker(dp_options);
	$("#user_status_dialog #status_date_to").datepicker(dp_options);

	var e = {
		user_id : row['user_id']
	};
	$.post('../operation/op_api.php?op=user_penalty_list', e,
			function(data) {
				var row = $("#user_status_dialog").dialog('option', 'row');
				$("#user_status_dialog").dialog("open");
				if (d = response_check(data)) {
					// put history here
					var rows = d['user_penalty_list'] ? d['user_penalty_list'] : [];
					var cols = build_column_header(rows, 'action_id');

					set_column_formatter(cols, 'old_status', status_formatter);
					set_column_formatter(cols, 'new_status', status_formatter);
					set_column_formatter(cols, 'reason', reason_formatter);

					var grid = build_slickgrid($('#user_status_dialog #grid'), rows, cols,
							$("#user_status_dialog #pager"), 10);
				}
			});
}

function on_tabs_user_detail() {
	$("#tabs_user_detail").tabs({
		create : function(event, ui) {
			on_tab_user_detail(ui.tab);
		},
		activate : function(event, ui) {
			on_tab_user_detail(ui.newTab);
		}
	});
}

function on_tab_user_detail_general() {

}
function on_tab_user_detail_skill() {

}
function on_tab_user_detail_badge() {

}
