function on_tab_community(tab) {
	if (!logged_in())
		return;

	console.log('on activate on_tab_community: ' + tab.index());

	if (tab.index() == 0)
		on_tabs_community_chats();
	if (tab.index() == 1)
		on_tabs_community_badwords();

}

function community_chats_update() {
	var chat_list = JSON.parse(sessionStorage.chat_list ? sessionStorage.chat_list : '[]');

	var rows = chat_list;
	for ( var i = 0; i < rows.length; i++)
		rows[i]['modify'] = '설정';

	var cols = build_column_header(rows, 'chat_id');

	set_column_formatter(cols, 'user_status', status_formatter);
	set_column_formatter(cols, 'send_force', force_formatter);
	set_column_formatter(cols, 'recv_force', force_formatter);

	$.each([ 'send_force', 'recv_force', 'chat_id', 'general_id', 'user_id', 'modify' ], function(index, value) {
		var col = find_column(cols, value);
		if (col) {
			console.log('col: ' + sjs(col));
			col['maxWidth'] = col['minWidth'] = col['width'] = 80;
		}
	});

	var grid = build_slickgrid($('#tabs_community #chat_list #grid'), rows, cols,
			$("#tabs_community #chat_list #pager"), 20);
	grid.onDblClick.subscribe(function(e, args) {
		if (args.grid.cols[args.cell].id === 'modify') {
			var row = args.grid.dataView.getItem(args.row);
			user_status_dialog_handler(e, args);
		}
	});
}

function on_tabs_community_chats() {
	var dp_options = {
		changeMonth : true,
		changeYear : true,
		numberOfMonths : 1,
		showButtonPanel : true,
		dateFormat : 'yy-mm-dd'
	};
	$("#tabs_community #tabs_chats #chats_search_date_from").datepicker(dp_options);
	$("#tabs_community #tabs_chats #chats_search_date_to").datepicker(dp_options);

	$('#tabs_community #tabs_chats #search_run').unbind();
	$('#tabs_community #tabs_chats #search_run').click(function(event) {
		community_chats_update_post();
	});
	$('#tabs_community #tabs_chats #search_download').unbind();
	$('#tabs_community #tabs_chats #search_download').click(function(event) {
		community_chats_update_post(1);
	});

	function community_chats_update_post(download) {
		var date_fr = $("#tabs_community #tabs_chats #chats_search_date_from").val();
		var date_to = $("#tabs_community #tabs_chats #chats_search_date_to").val();
		var username = $("#tabs_community #tabs_chats #search_username").val();
		var body = $("#tabs_community #tabs_chats #search_body").val();
		var recv_force = $('#tabs_community #tabs_chats #search_recv_force').val();

		var e = {
			recv_force : recv_force,
			bgn_at : date_fr,
			end_at : date_to,
			username : username,
			body : body,
			download : download > 0 ? 1 : 0
		};

		if (download > 0) {
			$.fileDownload('../operation/op_api.php?op=chat_list', {
				// preparingMessageHtml : "We are preparing your report, please wait...",
				failMessageHtml : "There was a problem downloading data, please try again.",
				httpMethod : "POST",
				data : e,
			});
		} else {
			$.post('../operation/op_api.php?op=chat_list', e, function(data) {
				if (d = response_check(data)) {
					sessionStorage.chat_list = JSON.stringify(d.chat_list);
					community_chats_update();
				}
			});
		}
	}

	community_chats_update();
}

function community_badwords_update() {
	var chat_badword_list = JSON.parse(sessionStorage.chat_badword_list ? sessionStorage.chat_badword_list : '[]');

	var rows = chat_badword_list;
	for ( var i = 0; i < rows.length; i++)
		rows[i]['remove'] = '삭제';

	var cols = build_column_header(rows, 'bw_id');

	// set_column_formatter(cols, 'user_status', status_formatter);
	// set_column_formatter(cols, 'send_force', force_formatter);
	// set_column_formatter(cols, 'recv_force', force_formatter);
	//	
	// $.each(['send_force', 'recv_force', 'badword_id', 'general_id', 'user_id', 'modify'], function(index, value) {
	// var col = find_column(cols, value);
	// console.log('col: ' + sjs(col));
	// col['maxWidth'] = col['minWidth'] = col['width'] = 80;
	// });

	var grid = build_slickgrid($('#tabs_community #badword_list #grid'), rows, cols,
			$("#tabs_community #badword_list #pager"), 20);
	grid.onDblClick.subscribe(function(e, args) {
		if (args.grid.cols[args.cell].id === 'remove') {
			var row = args.grid.dataView.getItem(args.row);
			console.log('row: ' + sjs(row));
			var msg = '금칙어: ' + row['badword'] + ' 을 삭제합니까?';
			if (confirm(msg)) {
				var e = {
					bw_id : row['bw_id']
				};
				$.post('../operation/op_api.php?op=chat_badword_del', e, function(data) {
					if (d = response_check(data)) {
						sessionStorage.chat_badword_list = JSON.stringify(d.chat_badword_list);
						community_badwords_update();
					}
				});
			}
		}
	});
}

function on_tabs_community_badwords() {

	$('#tabs_community #badword_add').unbind();
	$('#tabs_community #badword_add').click(function(event) {
		var badword = $('#tabs_community #badword').val();
		var e = {
			badword : badword
		};
		$.post('../operation/op_api.php?op=chat_badword_add', e, function(data) {
			if (d = response_check(data)) {
				sessionStorage.chat_badword_list = JSON.stringify(d.chat_badword_list);
				community_badwords_update();
			}
		});
	});

	$('#tabs_badwords #btn_refresh').unbind();
	$('#tabs_badwords #btn_refresh').click(function(event) {
		community_badwords_update_post();
	});

	function community_badwords_update_post() {
		$.post('../operation/op_api.php?op=chat_badword_list', {}, function(data) {
			if (d = response_check(data)) {
				sessionStorage.chat_badword_list = JSON.stringify(d.chat_badword_list);
				community_badwords_update();
			}
		});
	}

	community_badwords_update();
}
