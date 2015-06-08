function response_check(jsonstr) {
	d = JSON.parse(jsonstr);
	if (d['code'] != 'ok') {
		$.msg('unblock');

		console.log('data: ' + jsonstr);
		alert(d['message']);
		if (d['message'].indexOf('login first') != -1 )
			$('#logout').trigger('submit');
		return null;
	}
	return d;
}

function refresh_infos() {
	console.log('on refresh_infos()');

	return null;

	var terms = new Array('general', 'user', 'serverinfo', 'constants', 'battlefield');
	for ( var i = 0; i < terms.length; i++) {
		term = terms[i];
		e = $('#tabs_' + term + ' #json');
		$('#tabs_' + term + ' #grid').hide();
		// console.log('term: ' + term + ' e: ' + e);

		if (e) {
			ctx = JSON.parse(sessionStorage.getItem(term));
			// ctx = $.cookie(term);
			if (ctx)
				e.html('<pre><code>' + JSON.stringify(ctx, null, 4) + '</code></pre>');
			else
				e.text('N/A, needs refresh');
		}
	}

	$('#mail_recv_name').value = sessionStorage.user['username'];

	var gterms = new Array('officer', 'troop', 'construction', 'tile', 'combat', 'item', 'community', 'mail');
	for ( var i = 0; i < gterms.length; i++) {
		term = gterms[i];
		e = $('#tabs_' + term + ' #grid');
		$('#tabs_' + term + ' #json').hide();
		// console.log('term: ' + term + ' e: ' + e);

		if (e) {
			ctx = JSON.parse(sessionStorage.getItem(term));
			if (ctx) {
				var rows = ctx;
				var cols = [];
				d = {};
				for ( var j = 0; j < rows.length; j++) {
					for ( var k in rows[j]) {
						found = false;
						for ( var p in d) {
							if (p == k)
								found = true;
						}
						if (!found)
							cols.push(d[k] = {
								id : k,
								name : k,
								field : k,
								sortable : true
							});

						type = Object.prototype.toString.call(rows[j][k]);
						if (type === '[object Array]' || type === '[object Object]')
							rows[j][k] = JSON.stringify(rows[j][k]);
					}
				}
				// console.log(cols);
				var options = {
					enableCellNavigation : true,
					enableColumnReorder : true,
					multiColumnSort : true,
					forceFitColumns : true
				};
				var grid = new Slick.Grid(e, rows, cols, options);
				grid.registerPlugin(new Slick.AutoTooltips({
					enableForHeaderCells : true
				}));
				grid.data = rows;
				grid.cols = cols;
				grid.onSort.subscribe(function(e, args) {
					var cols = args.sortCols;

					// console.log('args: ' + JSON.stringify(args));
					args.grid.data.sort(function(dataRow1, dataRow2) {
						for ( var i = 0, l = cols.length; i < l; i++) {
							var field = cols[i].sortCol.field;
							var sign = cols[i].sortAsc ? 1 : -1;
							var value1 = dataRow1[field], value2 = dataRow2[field];
							var result = (value1 == value2 ? 0 : (value1 > value2 ? 1 : -1)) * sign;
							if (result != 0) {
								return result;
							}
						}
						return 0;
					});

					args.grid.invalidate();
					args.grid.render();
				});
				grid.onDblClick.subscribe(function(e, args) {
					// console.log('on dblclick: ' + JSON.stringify(args));
					row = args.grid.data[args.row];
					col = args.grid.cols[args.cell]['field'];
					body = row[col];
					// console.log('on dblclick: ' + body + ',' +
					// JSON.stringify(row) + ',' + JSON.stringify(col));
					if (body)
						$.msg({
							bgPath : '../admin/images/',
							content : body,
							fadeIn : 0,
							autoUnblock : false,
							clickUnblock : true
						});
				});
				// for ( i = 0 ; i < rows.length ; i++ ) console.log('row:' +
				// JSON.stringify(rows[i]));
			} else
				e.text('N/A, needs refresh');
		}
	}
}

function on_login(d) {
	sessionStorage.opuser = JSON.stringify(d.opuser);

	refresh();
}

function clear_cookies() {
	cookie_json = $.cookie.json;

	$.cookie.json = false;
	for (k in $.cookie())
		if (k)
			$.removeCookie(k);
	$.cookie('PHPSESSID', null, {
		path : '/'
	});

	$.cookie.json = cookie_json;

	sessionStorage.clear();
}

function configure_events() {
	console.log('on configure_events');

	$('#login').unbind();
	$('#login').click(function(event) {
		console.log('on login submit');

		username = $('#div_login input[name=username]').val();
		password = $('#div_login input[name=password]').val();

		js = {
			username : username,
			password : password
		};
		e = sjs(js);
		console.log('logging with: ' + e);
		$.post('../operation/op_api.php?op=login', e, function(data) {
			if (d = response_check(data)) {
				$('#div_login').hide();
				$('#div_logout').show();

				on_login(d);
			}
		});
	});
	$('#logout').unbind();
	$('#logout').click(function(event) {
		console.log('on logout submit');

		$.post('../operation/op_api.php?op=logout', function(data) {
			if (response_check(data)) {
				$('#div_login').show();
				$('#div_logout').hide();
			}
			clear_cookies();
			refresh();
		});
		clear_cookies();
	});

	// init jquery-ui
	$("#tabs").tabs();

	$("#tabs_opadmin").tabs({
		create : function(event, ui) {
			on_tab_opadmin(ui.tab);
		},
		activate : function(event, ui) {
			on_tab_opadmin(ui.newTab);
		}
	});
	$("#tabs_user").tabs({
		create : function(event, ui) {
			on_tab_user(ui.tab);
		},
		activate : function(event, ui) {
			on_tab_user(ui.newTab);
		}
	});
	$("#tabs_maintain").tabs({
		create : function(event, ui) {
			on_tab_maintain(ui.tab);
		},
		activate : function(event, ui) {
			on_tab_maintain(ui.newTab);
		}
	});
	$("#tabs_community").tabs({
		create : function(event, ui) {
			on_tab_community(ui.tab);
		},
		activate : function(event, ui) {
			on_tab_community(ui.newTab);
		}
	});
	$("#tabs_message").tabs({
		create : function(event, ui) {
			on_tab_message(ui.tab);
		},
		activate : function(event, ui) {
			on_tab_message(ui.newTab);
		}
	});
	$("#tabs_event").tabs({
		create : function(event, ui) {
			on_tab_event(ui.tab);
		},
		activate : function(event, ui) {
			on_tab_event(ui.newTab);
		}
	});
	$("#tabs_notices").tabs({
		create : function(event, ui) {
			on_tab_notices(ui.tab);
		},
		activate : function(event, ui) {
			on_tab_notices(ui.newTab);
		}
	});

	$("#tabs_statistics").tabs({
		create : function(event, ui) {
			on_tab_statistics(ui.tab);
		},
		activate : function(event, ui) {
			on_tab_statistics(ui.newTab);
		}
	});

	$("#tabs_item").tabs();
	$("#tabs_event").tabs();

	/*
	 * $("#tabs_opadmin").tabs().addClass('ui-tabs-vertical ui-helper-clearfix');
	 * $("#tabs_user").tabs().addClass('ui-tabs-vertical ui-helper-clearfix');
	 * $("#tabs_item").tabs().addClass('ui-tabs-vertical ui-helper-clearfix');
	 * $("#tabs_message").tabs().addClass('ui-tabs-vertical ui-helper-clearfix');
	 * $("#tabs_community").tabs().addClass('ui-tabs-vertical ui-helper-clearfix');
	 * $("#tabs_event").tabs().addClass('ui-tabs-vertical ui-helper-clearfix');
	 * $("#tabs_maintain").tabs().addClass('ui-tabs-vertical ui-helper-clearfix');
	 * $("#tabs_notice").tabs().addClass('ui-tabs-vertical ui-helper-clearfix');
	 * $("#tabs_statistics").tabs().addClass('ui-tabs-vertical ui-helper-clearfix');
	 */

	$("button").button();

	$("#tabs_general #refresh").unbind();
	$("#tabs_general #refresh").click(function() {
		$.post('../general/general.php', function(data) {
			if (d = response_check(data)) {
				// $.cookie('general', d.general);
				sessionStorage.general = JSON.stringify(d.general);
				refresh_infos();
			}
		});
	});
	$("#tabs_general #reset").unbind();
	$("#tabs_general #reset").click(function() {
		if (r = confirm('cannot rollback, proceed?')) {
			e = {
				op : 'clear'
			};
			$.post('../general/general.php', JSON.stringify(e), function(data) {
				if (d = response_check(data)) {
					// $.cookie('general', d.general);
					alert('done');
					sessionStorage.general = JSON.stringify(d.general);
					refresh_infos();
				}
			});
		}
	});

	$("#tabs_tile #refresh_ranks").unbind();
	$("#tabs_tile #refresh_ranks").click(function() {
		$.post('../battlefield/tile.php?op=ranks', function(data) {
			if (d = response_check(data)) {
				e = $('#tabs_tile #json');
				if (e) {
					ctx = d.tiles;
					if (ctx)
						e.html('<pre><code>' + JSON.stringify(ctx, null, 4) + '</code></pre>');
					else
						e.text('N/A, needs refresh');
				}
			}
		});
	});
}

function logged_in() {
	$.cookie.json = false;
	PHPSESSID = $.cookie('PHPSESSID');
	$.cookie.json = true;

	if (!PHPSESSID || !sessionStorage.opuser)
		return false;
	return true;
}

function refresh() {

	if (logged_in()) {
		refresh_infos();

		$('#div_login').hide();
		$('#div_logout').show();
		$('#tabs').show();
	} else {
		console.log('not logged in');

		$.msg('unblock');

		$('#div_login').show();
		$('#div_logout').hide();
		$('#tabs').hide();
	}
};

$(document).ready(function() {
	console.log('on ready!');

	$.ajaxSetup({
		beforeSend : function() {
			$.msg({
				bgPath : '../admin/images/',
				content : 'Processing request ... (automatically dismisses on complete)',
				fadeIn : 0,
				autoUnblock : false,
				clickUnblock : false
			});
		},
		error : function() {
			$.msg('unblock');
		},
		complete : function() {
			$.msg('unblock');
		}
	});

	$.cookie.json = true;
	configure_events();
	refresh();
});

var market_map = {
	0 : 'any',
	1 : 'google',
	2 : 'tstore',
	3 : 'olleh',
	4 : 'ustore',
	5 : 'appstore'
};
var status_map = {
	0 : '전체',
	1 : '정상',
	2 : '일시정지',
	3 : '영구정지'
};
var notice_status_map = {
	1 : '대기',
	2 : '진행',
	3 : '완료'
};
var line_map = {
	0 : '전체',
	1 : '미접속',
	2 : '접속'
};
var access_map = {
	0 : '전체',
	1 : '가입일',
	2 : '접속일'
};
var reason_map = {
	0 : '기타',
	1 : '욕설',
	2 : '해킹',
	3 : '계정도용'
};
var force_map = {
	1 : '연합',
	2 : '제국',
	3 : '중립'
};
var recv_type_map = {
	1 : '유저',
	2 : '군단',
	3 : '진영'
};
var result_type_map = {
	1 : '일별',
	2 : '월별'
};

var status_formatter = function(row, cell, value, columnDef, dataContext) {
	return status_map[value] ? status_map[value] : 'unknown';
};
var line_formatter = function(row, cell, value, columnDef, dataContext) {
	return line_map[value] ? line_map[value] : 'unknown';
};
var access_formatter = function(row, cell, value, columnDef, dataContext) {
	return access_map[value] ? access_map[value] : 'unknown';
};
var market_formatter = function(row, cell, value, columnDef, dataContext) {
	return market_map[value] ? market_map[value] : 'unknown';
};
var reason_formatter = function(row, cell, value, columnDef, dataContext) {
	return reason_map[value] ? reason_map[value] : 'unknown';
};

var force_formatter = function(row, cell, value, columnDef, dataContext) {
	return force_map[value] ? force_map[value] : 'unknown';
};

var recv_type_formatter = function(row, cell, value, columnDef, dataContext) {
	return recv_type_map[value] ? recv_type_map[value] : 'unknown';
};

var notice_status_formatter = function(row, cell, value, columnDef, dataContext) {
	return notice_status_map[value] ? notice_status_map[value] : 'unknown';
};

function fill_selector_with_map(selector, map) {
	selector.options.length = 0;
	for ( var key in map)
		selector.options[selector.options.length] = new Option(map[key], key, false, false);
}
