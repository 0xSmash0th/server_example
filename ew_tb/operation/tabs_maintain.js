function on_tab_maintain(tab) {
	if (!logged_in())
		return;

	console.log('on activate on_tab_maintain: ' + tab.index());

	if (tab.index() == 0)
		on_tabs_maintain_manage();
}

function on_tabs_maintain_manage_update() {
	maintenance_on = JSON.parse(sessionStorage.maintenance_on ? sessionStorage.maintenance_on : '0');
	maintenance_message = JSON.parse(sessionStorage.maintenance_message ? sessionStorage.maintenance_message : '""');
	maintenance_allowed_list_user_id = JSON
			.parse(sessionStorage.maintenance_allowed_list_user_id ? sessionStorage.maintenance_allowed_list_user_id
					: '[]');
	maintenance_allowed_list_dev_uuid = JSON
			.parse(sessionStorage.maintenance_allowed_list_dev_uuid ? sessionStorage.maintenance_allowed_list_dev_uuid
					: '[]');

	$('#tabs_maintain #maintenance_active').val(maintenance_on);
	$('#tabs_maintain #maintenance_message').val(maintenance_message);

	var allowed_remover = function(e, args) {
		if (args.grid.cols[args.cell].id === 'remove') {
			var row = args.grid.dataView.getItem(args.row);
			console.log('row: ' + sjs(row));
			var msg = undefined;
			if (row['username'])
				msg = 'username: ' + row['username'] + ' 을 삭제합니까?';
			if (row['dev_uuid'])
				msg = 'dev_uuid: ' + row['dev_uuid'] + ' 을 삭제합니까?';
			if (msg && confirm(msg)) {
				var e = {
					mt_id : row['mt_id']
				};
				$.post('../operation/op_api.php?op=maintenance_allowed_del', e, function(data) {
					if (d = response_check(data)) {
						sessionStorage.maintenance_on = JSON.stringify(d.maintenance_on);
						sessionStorage.maintenance_message = JSON.stringify(d.maintenance_message);
						sessionStorage.maintenance_allowed_list_user_id = JSON
								.stringify(d.maintenance_allowed_list_user_id);
						sessionStorage.maintenance_allowed_list_dev_uuid = JSON
								.stringify(d.maintenance_allowed_list_dev_uuid);

						on_tabs_maintain_manage_update();
					}
				});
			}
		}
		;
	};

	var rows = maintenance_allowed_list_user_id;
	for ( var i = 0; i < rows.length; i++)
		rows[i]['remove'] = '삭제';
	var cols = build_column_header(rows, 'mt_id');
	var grid = build_slickgrid($('#tabs_maintain #username_list #grid'), rows, cols,
			$("#tabs_maintain #username_list #pager"), 10);
	grid.onDblClick.subscribe(allowed_remover);

	var rows = maintenance_allowed_list_dev_uuid;
	for ( var i = 0; i < rows.length; i++)
		rows[i]['remove'] = '삭제';
	var cols = build_column_header(rows, 'mt_id');
	var grid = build_slickgrid($('#tabs_maintain #devuuid_list #grid'), rows, cols,
			$("#tabs_maintain #devuuid_list #pager"), 10);
	grid.onDblClick.subscribe(allowed_remover);
}

function on_tabs_maintain_manage() {

	$('#tabs_maintain #btn_refresh').unbind();
	$('#tabs_maintain #btn_refresh').click(function(event) {
		maintain_manage_update_post();
	});

	$('#tabs_maintain #maintenance_set').unbind();
	$('#tabs_maintain #maintenance_set').click(function(event) {
		var new_active = $('#tabs_maintain #maintenance_active').val();
		var e = {
			active : new_active
		};
		$.post('../operation/op_api.php?op=maintenance_set', e, function(data) {
			if (d = response_check(data)) {
			}
		});
	});

	$('#tabs_maintain #message_set').unbind();
	$('#tabs_maintain #message_set').click(function(event) {
		var mtmsg = $('#tabs_maintain #maintenance_message').val();
		var e = {
			mtmsg : mtmsg
		};
		$.post('../operation/op_api.php?op=maintenance_message_set', e, function(data) {
			if (d = response_check(data)) {
			}
		});
	});

	$('#tabs_maintain #allowed_username_add').unbind();
	$('#tabs_maintain #allowed_username_add').click(function(event) {
		var username = $('#tabs_maintain #allowed_username').val();
		var e = {
			username : username
		};
		$.post('../operation/op_api.php?op=maintenance_allowed_add', e, function(data) {
			if (d = response_check(data)) {
				sessionStorage.maintenance_on = JSON.stringify(d.maintenance_on);
				sessionStorage.maintenance_message = JSON.stringify(d.maintenance_message);
				sessionStorage.maintenance_allowed_list_user_id = JSON.stringify(d.maintenance_allowed_list_user_id);
				sessionStorage.maintenance_allowed_list_dev_uuid = JSON.stringify(d.maintenance_allowed_list_dev_uuid);

				on_tabs_maintain_manage_update();
			}
		});
	});

	$('#tabs_maintain #allowed_devuuid_add').unbind();
	$('#tabs_maintain #allowed_devuuid_add').click(function(event) {
		var dev_uuid = $('#tabs_maintain #allowed_devuuid').val();
		var e = {
			dev_uuid : dev_uuid
		};
		$.post('../operation/op_api.php?op=maintenance_allowed_add', e, function(data) {
			if (d = response_check(data)) {
				sessionStorage.maintenance_on = JSON.stringify(d.maintenance_on);
				sessionStorage.maintenance_message = JSON.stringify(d.maintenance_message);
				sessionStorage.maintenance_allowed_list_user_id = JSON.stringify(d.maintenance_allowed_list_user_id);
				sessionStorage.maintenance_allowed_list_dev_uuid = JSON.stringify(d.maintenance_allowed_list_dev_uuid);

				on_tabs_maintain_manage_update();
			}
		});
	});

	function maintain_manage_update_post() {
		$.post('../operation/op_api.php?op=maintenance_allowed_list', {}, function(data) {
			if (d = response_check(data)) {
				sessionStorage.maintenance_on = JSON.stringify(d.maintenance_on);
				sessionStorage.maintenance_message = JSON.stringify(d.maintenance_message);
				sessionStorage.maintenance_allowed_list_user_id = JSON.stringify(d.maintenance_allowed_list_user_id);
				sessionStorage.maintenance_allowed_list_dev_uuid = JSON.stringify(d.maintenance_allowed_list_dev_uuid);

				on_tabs_maintain_manage_update();
			}
		});
	}

	on_tabs_maintain_manage_update();
}
