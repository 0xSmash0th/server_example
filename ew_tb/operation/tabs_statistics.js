function on_tab_statistics(tab) {
	if (!logged_in())
		return;

	console.log('on activate on_tab_statistics: ' + tab.index());

	if (tab.index() == 0)
		on_tabs_statistics_service();
	if (tab.index() == 1)
		on_tabs_statistics_sale();
}

function statistics_service_update() {
	var stats_service = JSON.parse(sessionStorage.stats_service ? sessionStorage.stats_service : '[]');

	var rows = stats_service;
	for ( var i = 0; i < rows.length; i++) {
		// rows[i]['modify'] = '수정';
		// rows[i]['remove'] = '삭제';
	}

	var cols = build_column_header(rows, 'stat_id');

	set_column_formatter(cols, 'market_type', market_formatter);
	set_column_formatter(cols, 'stat_type', stat_service_type_formatter);

	$.each([ 'recv_force', 'general_id', 'user_id', 'remove', 'modify' ], function(index, value) {
		var col = find_column(cols, value);
		if (col) {
			console.log('col: ' + sjs(col));
			col['maxWidth'] = col['minWidth'] = col['width'] = 80;
		}
	});

	var grid = build_slickgrid($('#tabs_statistics #tabs_services #result_list #grid'), rows, cols,
			$("#tabs_statistics #tabs_services #result_list #pager"), 10);
	grid.onDblClick.subscribe(function(e, args) {
		var row = args.grid.dataView.getItem(args.row);
	});
}

var stat_service_type_map = {
	1 : '신규 가입자',
	2 : '동시 접속자',
	3 : '액티브 사용자',
	4 : '탈퇴 사용자'
};
var stat_service_type_formatter = function(row, cell, value, columnDef, dataContext) {
	return stat_service_type_map[value] ? stat_service_type_map[value] : 'unknown';
};

function on_tabs_statistics_service() {
	fill_selector_with_map($("#tabs_statistics #tabs_services #search_market")[0], market_map);
	fill_selector_with_map($("#tabs_statistics #tabs_services #search_result_type")[0], result_type_map);
	fill_selector_with_map($("#tabs_statistics #tabs_services #search_stat_service_type")[0], stat_service_type_map);

	var dp_options = {
		changeMonth : true,
		changeYear : true,
		numberOfMonths : 1,
		showButtonPanel : true,
		dateFormat : 'yy-mm-dd'
	};
	$("#tabs_statistics #tabs_services #stats_service_search_date_from").datepicker(dp_options);
	$("#tabs_statistics #tabs_services #stats_service_search_date_to").datepicker(dp_options);

	$('#tabs_statistics #tabs_services #search_run').unbind();
	$('#tabs_statistics #tabs_services #search_run').click(function(event) {
		statistics_service_update_post();
	});
	$('#tabs_statistics #tabs_services #search_download').unbind();
	$('#tabs_statistics #tabs_services #search_download').click(function(event) {
		statistics_service_update_post(1);
	});

	function statistics_service_update_post(download) {
		var e = {
			download : download > 0 ? 1 : 0
		};

		e['market_type'] = $("#tabs_statistics #tabs_services #search_market").val();
		e['result_type'] = $("#tabs_statistics #tabs_services #search_result_type").val();
		e['service_type'] = $("#tabs_statistics #tabs_services #search_stat_service_type").val();
		e['bgn_at'] = $("#tabs_statistics #tabs_services #stats_service_search_date_from").val();
		e['end_at'] = $("#tabs_statistics #tabs_services #stats_service_search_date_to").val();

		if (download > 0) {
			$.fileDownload('../operation/op_api.php?op=statistics_service_list', {
				// preparingMessageHtml : "We are preparing your report, please wait...",
				failMessageHtml : "There was a problem downloading data, please try again.",
				httpMethod : "POST",
				data : e,
			});
		} else {
			$.post('../operation/op_api.php?op=statistics_service_list', e, function(data) {
				if (d = response_check(data)) {
					sessionStorage.stats_service = JSON.stringify(d.stats_service);
					statistics_service_update();
				}
			});
		}
	}

	statistics_service_update();
}

function statistics_sale_update() {
	var stats_sale = JSON.parse(sessionStorage.stats_sale ? sessionStorage.stats_sale : '[]');

	var rows = stats_sale;
	for ( var i = 0; i < rows.length; i++) {
		// rows[i]['modify'] = '수정';
		// rows[i]['remove'] = '삭제';
	}

	var cols = build_column_header(rows, 'stat_id');

	set_column_formatter(cols, 'market_type', market_formatter);
	set_column_formatter(cols, 'stat_type', stat_sale_type_formatter);

	$.each([ 'recv_force', 'general_id', 'user_id', 'remove', 'modify' ], function(index, value) {
		var col = find_column(cols, value);
		if (col) {
			console.log('col: ' + sjs(col));
			col['maxWidth'] = col['minWidth'] = col['width'] = 80;
		}
	});

	var grid = build_slickgrid($('#tabs_statistics #tabs_sales #result_list #grid'), rows, cols,
			$("#tabs_statistics #tabs_sales #result_list #pager"), 10);
	grid.onDblClick.subscribe(function(e, args) {
		var row = args.grid.dataView.getItem(args.row);
	});
}

var stat_sale_type_map = {
	1 : '매출',
	2 : '구매 횟수',
	3 : '구매자 수',
	4 : 'ARPU'
};
var stat_sale_type_formatter = function(row, cell, value, columnDef, dataContext) {
	return stat_sale_type_map[value] ? stat_sale_type_map[value] : 'unknown';
};

function on_tabs_statistics_sale() {
	fill_selector_with_map($("#tabs_statistics #tabs_sales #search_market")[0], market_map);
	fill_selector_with_map($("#tabs_statistics #tabs_sales #search_result_type")[0], result_type_map);
	fill_selector_with_map($("#tabs_statistics #tabs_sales #search_stat_sale_type")[0], stat_sale_type_map);

	var dp_options = {
		changeMonth : true,
		changeYear : true,
		numberOfMonths : 1,
		showButtonPanel : true,
		dateFormat : 'yy-mm-dd'
	};
	$("#tabs_statistics #tabs_sales #stats_sale_search_date_from").datepicker(dp_options);
	$("#tabs_statistics #tabs_sales #stats_sale_search_date_to").datepicker(dp_options);

	$('#tabs_statistics #tabs_sales #search_run').unbind();
	$('#tabs_statistics #tabs_sales #search_run').click(function(event) {
		statistics_sale_update_post();
	});
	$('#tabs_statistics #tabs_sales #search_download').unbind();
	$('#tabs_statistics #tabs_sales #search_download').click(function(event) {
		statistics_sale_update_post(1);
	});

	function statistics_sale_update_post(download) {
		var e = {
			download : download > 0 ? 1 : 0
		};

		e['market_type'] = $("#tabs_statistics #tabs_sales #search_market").val();
		e['result_type'] = $("#tabs_statistics #tabs_sales #search_result_type").val();
		e['sale_type'] = $("#tabs_statistics #tabs_sales #search_stat_sale_type").val();
		e['bgn_at'] = $("#tabs_statistics #tabs_sales #stats_sale_search_date_from").val();
		e['end_at'] = $("#tabs_statistics #tabs_sales #stats_sale_search_date_to").val();

		if (download > 0) {
			$.fileDownload('../operation/op_api.php?op=statistics_sale_list', {
				// preparingMessageHtml : "We are preparing your report, please wait...",
				failMessageHtml : "There was a problem downloading data, please try again.",
				httpMethod : "POST",
				data : e,
			});
		} else {
			$.post('../operation/op_api.php?op=statistics_sale_list', e, function(data) {
				if (d = response_check(data)) {
					sessionStorage.stats_sale = JSON.stringify(d.stats_sale);
					statistics_sale_update();
				}
			});
		}
	}

	statistics_sale_update();
}
