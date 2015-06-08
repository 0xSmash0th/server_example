function on_tab_opadmin(tab) {
	if (!logged_in())
		return;

	console.log('on activate tabs_opadmin: ' + tab.index());

	if (tab.index() == 0)
		on_tabs_opadmin_mypage();
	if (tab.index() == 1)
		on_tabs_opadmin_auth();
	if (tab.index() == 2)
		on_tabs_opadmin_action();
}

function on_tabs_opadmin_mypage() {

}

function on_tabs_opadmin_auth() {
	$("#opuser_mod_dialog").dialog({
		autoOpen : false,
		modal : true
	});

	$('.auth_detail').unbind();
	$('.auth_detail').click(
			function(event) {
				var id = $(this).attr('id');
				var val = $(this).val();
				var acl = id.replace('btn_', '');
				console.log('auth_detail: ' + acl);

				var new_rows = [];
				var rows = JSON.parse(sessionStorage.opuser_list);
				for ( var i = 0; i < rows.length; i++) { // filter by
					// acl
					if ($.inArray(acl, rows[i].acl.split(',')) != -1) {
						rows[i]['modify'] = '변경';
						rows[i]['remove'] = '삭제';
						new_rows.push(rows[i]);
					}
				}
				rows = new_rows;

				var cols = build_column_header(rows, 'opuser_id');
				var grid = build_slickgrid($('#tabs_opadmin #user_list #grid'), rows, cols,
						$("#tabs_opadmin #user_list #pager"), 5);
				grid.onDblClick.subscribe(function(e, args) {
					if (args.grid.cols[args.cell].id === 'modify') {
						// console.log('onDblClick ' +
						// sjs(args));
						var row = args.grid.dataView.getItem(args.row);
						console.log('row: ' + sjs(row));
						$("#opuser_mod_dialog").dialog("option", "row", row);
						$("#opuser_mod_dialog").dialog("option", "title", "권한 변경");
						$("#opuser_mod_dialog #username").text(row['username']);
						$("#opuser_mod_dialog #acl").text(row['acl']);
						$("#opuser_mod_dialog #btn_modify").unbind();
						$("#opuser_mod_dialog #btn_modify").click(function(ui, e) {
							var row = $("#opuser_mod_dialog").dialog('option', 'row');
							var new_acl = $("#opuser_mod_dialog #new_acl").val();
							var e = {
								opuser_id : row['opuser_id'],
								new_acl : new_acl
							};
							$.post('../operation/op_api.php?op=opuser_mod', e, function(data) {
								if (d = response_check(data)) {
								}
								$("#opuser_mod_dialog").dialog('close');
							});
						});
						$("#opuser_mod_dialog").dialog("open");
					}
					if (args.grid.cols[args.cell].id === 'remove') {
						// console.log('onDblClick ' +
						// sjs(args));
						var row = args.grid.dataView.getItem(args.row);
						console.log('row: ' + sjs(row));
						msg = 'username: ' + row['username'] + ' 을 삭제합니까?';
						if (confirm(msg)) {
							var e = {
								opuser_id : row['opuser_id']
							};
							$.post('../operation/op_api.php?op=opuser_del', e, function(data) {
								if (d = response_check(data)) {
								}
							});
						}
					}
				});
			});

	$('#btn_user_add').unbind();
	$('#btn_user_add').click(function(event) {
		var username = prompt('insert username');
		if (username) {
			var password = prompt('insert password');
			if (password) {
				var e = {
					username : username,
					password : password
				};
				$.post('../operation/op_api.php?op=register', e, function(data) {
					if (d = response_check(data)) {
					}
				});
			}
		}
	});

	$.post('../operation/op_api.php?op=opuser_list', {}, function(data) {
		if (d = response_check(data)) {
			var acl_groups = {};
			sessionStorage.opuser_list = JSON.stringify(d.opuser_list);

			acl_groups['master'] = [];
			acl_groups['operator'] = [];
			acl_groups['monitor'] = [];

			for ( var i = 0; i < d.opuser_list.length; i++) {
				var user = d.opuser_list[i];
				// console.log('user: ' + JSON.stringify(user));
				if (!user.acl)
					continue;
				var tokens = user.acl.split(',');
				for ( var j = 0; j < tokens.length; j++) {
					var token = tokens[j];
					acl_groups[token].push(user.username);
				}
			}

			console.log('acl_groups: ' + JSON.stringify(acl_groups));
			$('#td_master').text(acl_groups['master'].join(','));
			$('#td_operator').text(acl_groups['operator'].join(','));
			$('#td_monitor').text(acl_groups['monitor'].join(','));
		}
	});
}

function tabs_opadmin_action_update() {
	var rows = JSON.parse(sessionStorage.opaction_list ? sessionStorage.opaction_list : '[]');
	var cols = build_column_header(rows, 'action_id');
	var grid = build_slickgrid($('#tabs_opadmin #action_list #grid'), rows, cols,
			$("#tabs_opadmin #action_list #pager"), 10);
}

function on_tabs_opadmin_action() {
	var dp_options = {
		changeMonth : true,
		changeYear : true,
		numberOfMonths : 1,
		showButtonPanel : true,
		dateFormat : 'yy-mm-dd'
	};
	$("#tabs_opadmin #tabs_action #search_date_from").datepicker(dp_options);
	$("#tabs_opadmin #tabs_action #search_date_to").datepicker(dp_options);
	$("#tabs_opadmin #tabs_action #search_action_type");
	var search_run = $("#tabs_opadmin #tabs_action #search_run");
	search_run.unbind();
	search_run.click(function(event) {
		tabs_opadmin_action_update_post();
	});
	var search_download = $("#tabs_opadmin #tabs_action #search_download");
	search_download.unbind();
	search_download.click(function(event) {
		tabs_opadmin_action_update_post(1);
	});

	function tabs_opadmin_action_update_post(download) {
		var date_fr = $("#tabs_opadmin #tabs_action #search_date_from").val();
		var date_to = $("#tabs_opadmin #tabs_action #search_date_to").val();
		var username = $("#tabs_opadmin #tabs_action #search_username").val();
		var action_type = $("#tabs_opadmin #tabs_action #search_action_type").val();

		var e = {
			bgn_at : date_fr,
			end_at : date_to,
			username : username,
			action_type : action_type,
			download : download > 0 ? 1 : 0
		};

		if (download > 0) {
			$.fileDownload('../operation/op_api.php?op=opaction_list', {
				// preparingMessageHtml : "We are preparing your report, please wait...",
				failMessageHtml : "There was a problem downloading data, please try again.",
				httpMethod : "POST",
				data : e,
			});
		} else {
			$.post('../operation/op_api.php?op=opaction_list', e, function(data) {
				if (d = response_check(data)) {
					acl_groups = {};
					sessionStorage.opaction_list = JSON.stringify(d.opaction_list);
					on_tabs_opadmin_action();
				}
			});
		}
	}

	tabs_opadmin_action_update();
}
