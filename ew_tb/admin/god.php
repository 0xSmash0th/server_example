<?php
require_once '../army/item.php';

global $SYSTEM_OPERATOR_ALLOWED_IPS;
if ( $remote_addr = @$_SERVER['REMOTE_ADDR'] ) {
	$SYSTEM_OPERATOR_ALLOWED_IPS = empty($SYSTEM_OPERATOR_ALLOWED_IPS) ? [] : $SYSTEM_OPERATOR_ALLOWED_IPS;
	assert_render(in_array($remote_addr, $SYSTEM_OPERATOR_ALLOWED_IPS), "you are not allowed to access: $remote_addr");
}
?>
<!DOCTYPE html>
<html>

<head>
<meta http-equiv="Content-Type" content="text/html;charset=UTF-8">

<!-- jquery -->
<script type="text/javascript" src="js/jquery-2.0.3.min.js"></script>
<script type="text/javascript" src="js/jquery.cookie.js"></script>

<!-- for jquery-ui -->
<link href="css/ui-lightness/jquery-ui-1.10.3.custom.css" rel="stylesheet">
<script src="js/jquery-ui-1.10.3.custom.js"></script>

<!-- for jquery-msg -->
<script type="text/javascript" src="js/jquery.center.min.js"></script>
<script type="text/javascript" src="js/jquery.msg.js"></script>
<link media="screen" href="css/jquery.msg.css" rel="stylesheet" type="text/css">

<!-- for code highlighting -->
<link rel="stylesheet" href="highlight.js/styles/school_book.css">
<script type="text/javascript" src="highlight.js/highlight.pack.js"></script>
<script>hljs.initHighlightingOnLoad();</script>

<!-- for slickgrid -->
<link rel="stylesheet" href="slickgrid/slick.grid.css" type="text/css" />
<!-- <link rel="stylesheet" -->
<!-- 	href="slickgrid/css/smoothness/jquery-ui-1.8.16.custom.css" -->
<!-- 	type="text/css" /> -->
<!-- <script src="slickgrid/lib/jquery-1.7.min.js"></script> -->
<script src="slickgrid/plugins/slick.autotooltips.js"></script>
<script src="slickgrid/lib/jquery.event.drag-2.2.js"></script>
<script src="slickgrid/slick.core.js"></script>
<script src="slickgrid/slick.grid.js"></script>


<script type="text/javascript">
Object.size = function(obj) {
    var size = 0, key;
    for (key in obj) {
        if (obj.hasOwnProperty(key)) size++;
    }
    return size;
};

function response_check(jsonstr) {
	d = JSON.parse(jsonstr);
	if ( d['code'] != 'ok' ) {
		 $.msg('unblock');
		 
		console.log('data: ' + jsonstr)
		alert(d['message']);
		if ( d['message'] == 'login first' ) 
			$('#logout').trigger('submit');
		return null;
	}
	return d;
}

function refresh_infos() {
	console.log('on refresh_infos()');
	
	var terms = new Array('general', 'user', 'serverinfo', 'constants', 'battlefield');
	for ( i = 0 ; i < terms.length ; i++ ) {
		term = terms[i];		
		e = $('#tabs_'+term+' #json');
		$('#tabs_'+term+' #grid').hide();
	//	console.log('term: ' + term + ' e: ' + e);
		
		if ( e ) {
			ctx = JSON.parse(sessionStorage.getItem(term));
			//ctx = $.cookie(term);			
			if ( ctx )
				e.html('<pre><code>' + JSON.stringify(ctx, null, 4) + '</code></pre>');
			else
				e.text('N/A, needs refresh');			
		}
	}

	$('#mail_recv_name').value = sessionStorage.user['username'];

	var gterms = new Array('officer', 'troop', 'construction', 'tile', 'combat', 'item','chat', 'mail');
	for ( i = 0 ; i < gterms.length ; i++ ) {
		term = gterms[i];
		e = $('#tabs_'+term+' #grid');
		$('#tabs_'+term+' #json').hide();
	//	console.log('term: ' + term + ' e: ' + e);
		
		if ( e ) {
			ctx = JSON.parse(sessionStorage.getItem(term));			
			if ( ctx ) {
				var rows = ctx;
				var cols = []; d = {}; // probe columns
				for ( j = 0 ; j < rows.length ; j++ ) {
					for(var k in rows[j]) {
						found = false;
						for(var p in d) {
							if ( p == k ) found = true;
						}			
						if ( !found )
							 cols.push( d[k] = {id: k, name: k, field: k, sortable:true} );

						type = Object.prototype.toString.call(rows[j][k]);
						if ( type === '[object Array]' || type === '[object Object]' )
							rows[j][k] = JSON.stringify(rows[j][k]);
					}
				}
//				console.log(cols);
				var options = {
						enableCellNavigation: true, 
						enableColumnReorder: true,
						multiColumnSort: true,
						forceFitColumns: true};
				var grid = new Slick.Grid(e, rows, cols, options);
				grid.registerPlugin( new Slick.AutoTooltips({ enableForHeaderCells: true }) );	
				grid.data = rows;
				grid.cols = cols;
				grid.onSort.subscribe(function (e, args) {
				      var cols = args.sortCols;

				 // 	  console.log('args: ' + JSON.stringify(args));
				  	  args.grid.data.sort(function (dataRow1, dataRow2) {
				        for (var i = 0, l = cols.length; i < l; i++) {
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
			   	grid.onDblClick.subscribe(function (e, args) {
				   //	console.log('on dblclick: ' + JSON.stringify(args));
				   	row = args.grid.data[args.row];
				   	col = args.grid.cols[args.cell]['field'];
					body = row[col];
				//	console.log('on dblclick: ' + body + ',' + JSON.stringify(row) + ',' + JSON.stringify(col));
					if ( body )
				   		$.msg({bgPath : 'images/', content: body, fadeIn:0, autoUnblock: false, clickUnblock : true});				   	
			   	});
//				for ( i = 0 ; i < rows.length ; i++ ) console.log('row:' + JSON.stringify(rows[i]));
			}
			else
				e.text('N/A, needs refresh');			
		}		
	}	
}

function on_login(d){
	/*
	$.cookie('general', d.general);
	$.cookie('user', d.user);
	$.cookie('serverinfo', d.serverinfo);
	$.cookie('constants', d.constants);
	*/

	sessionStorage.general = JSON.stringify(d.general);
	sessionStorage.user = JSON.stringify(d.user);
	sessionStorage.serverinfo = JSON.stringify(d.serverinfo);
	sessionStorage.constants = JSON.stringify(d.constants);
	
	refresh();
}

function clear_cookies() {
	cookie_json = $.cookie.json;
	
	$.cookie.json = false;
	for ( k in $.cookie() )
		if(k) $.removeCookie(k);
	$.cookie('PHPSESSID', null, {path: '/' });
	
	$.cookie.json = cookie_json;

	sessionStorage.clear();
}

function refresh() {
	console.log('on refresh');

	$('#register').unbind();
	$('#register').submit(function(event) {
		event.preventDefault();
		console.log('on register submit');

		username = $('#register input[name=username]').val();
		password = $('#register input[name=password]').val();
		force = $('#register input[name=force]:checked').val();

		js = {username: username, password:password, country:force};
		sjs = JSON.stringify(js);
		console.log(sjs);
		$.post('../auth/register.php', sjs, function(data){
			if ( response_check(data) ) {
				$('#register').hide();
				$('#login').hide();
				$('#logout').show();
			}
		});
	});
	$('#login').unbind();
	$('#login').submit(function(event) {
		event.preventDefault();
		console.log('on login submit');

		username = $('#login input[name=username]').val();
		password = $('#login input[name=password]').val();

		js = {username: username, password:password};
		sjs = JSON.stringify(js);
		console.log(sjs);
		$.post('../auth/login.php', sjs, function(data){
			if ( d = response_check(data) ) {
				$('#register').hide();
				$('#login').hide();
				$('#logout').show();

				on_login(d);
			}				
		});
	});
	$('#logout').unbind();
	$('#logout').submit(function(event) {
		event.preventDefault();
		console.log('on logout submit');
		
		$.post('../auth/logout.php', function(data){				
			if ( response_check(data) ) {
				$('#register').show();
				$('#login').show();
				$('#logout').hide();	
			}
			clear_cookies();			
			refresh();
		});
		clear_cookies();
	});

	// init jquery-ui
	$("#tabs").tabs();
	$("#tabs").tabs({
		beforeActivate: function(event, ui) {
			// console.log('tabsbeforeactivate: ' + ui.newTab.id);
		} 
	});
	
	$("button").button();

	$("#tabs_general #refresh").unbind();
	$("#tabs_general #refresh").click(function() {
		$.post('../general/general.php', function(data){
			if ( d = response_check(data) ) {
			//	$.cookie('general', d.general);
				sessionStorage.general = JSON.stringify(d.general);
				refresh_infos();
			}
		});
	});
	$("#tabs_general #reset").unbind();
	$("#tabs_general #reset").click(function() {
		if ( r = confirm('cannot rollback, proceed?') ) {
			e = {op: 'clear'};
			$.post('../general/general.php', JSON.stringify(e), function(data){
				if ( d = response_check(data) ) {
				//	$.cookie('general', d.general);
					alert('done');
					sessionStorage.general = JSON.stringify(d.general);
					refresh_infos();
				}
			});
		}
	});
	$("#tabs_general #delete").unbind();
	$("#tabs_general #delete").click(function() {
		if ( r = confirm('cannot rollback, proceed?') ) {
			console.log('delete general');
			general = JSON.parse(sessionStorage.general);
			e = {op: 'run',
				query: 'delete from user where user_id = ' + general.user_id	
				};
			$.post('../admin/god_api.php', JSON.stringify(e), function(data){
				if ( d = response_check(data) ) {
					alert('done');
					$('#logout').trigger('submit');					
				}
			});			
		}
	});

	$("#tabs_general #reset_battlefield").unbind();
	$("#tabs_general #reset_battlefield").click(function() {
		if ( r = confirm('cannot rollback, proceed?') ) {
			password = prompt('Enter password');
			if ( password != null ) {
				if ( password == '9906' ) {
					console.log('reset_battlefield');
					e = {op: 'run',
						querys: "DELETE FROM battlefield;DELETE FROM combat;DELETE FROM tile;"
						};
					$.post('../admin/god_api.php', JSON.stringify(e), function(data){
						if ( d = response_check(data) ) {
							e = {op: 'get'};
							$.post('../battlefield/tile.php', JSON.stringify(e), function(data){
								alert('done');
								refresh_infos();
							});
						}						
					});		
				}
			}
		}
	});	

	$("#tabs_general #resetdb").unbind();
	$("#tabs_general #resetdb").click(function() {
		if ( r = confirm('cannot rollback, proceed?') ) {
			password = prompt('Enter password');
			if ( password != null ) {
				if ( password == '9906' ) {
					console.log('resetdb');
					e = {op: 'resetdb'};
					$.post('../admin/god_api.php', JSON.stringify(e), function(data){
						if ( d = response_check(data) ) {
							alert('done');
							$('#logout').trigger('submit');
						}
					});
				}
			}			
		}
	});
	$("#tabs_general .edit").unbind();
	$("#tabs_general .edit").click(function(event) {	
		id = $(this).attr('id');
		val = $(this).val();
		console.log('id:' + $(this).attr('id') + ', val: ' + $(this).val());
	//	general_id = $.cookie('general').general_id;
		general_id = JSON.parse(sessionStorage.general).general_id;

		query = null;
		querys = null;

		if ( id.indexOf('reset_daily_quest') != -1 ) {
			e = {op: 'reset_daily_quest'};
			$.post('../general/quest.php', JSON.stringify(e), function(data){
				if ( d = response_check(data) ) {
					alert('done');
					$("#tabs_general #refresh").trigger('click');
				}
			});
		} else if ( id.indexOf('tutorial_reset') != -1 ) {
			e = {op: 'tutorial_reset'};
			$.post('../general/general.php', JSON.stringify(e), function(data){
				if ( d = response_check(data) ) {
					alert('done');
					$("#tabs_general #refresh").trigger('click');
				}
			});
		} else if ( id.indexOf('clear_constant_caches') != -1 ) {
			if ( r = confirm('cannot rollback, proceed?') ) {
				e = {op: 'clear_constant_caches'};
				$.post('../general/general.php', JSON.stringify(e), function(data){
					if ( d = response_check(data) ) {
						alert('done');
						$("#tabs_general #refresh").trigger('click');
					}
				});
			}
		} else if ( id.indexOf('badge_') != -1 && id.indexOf('reset_badge_equip') == -1 ) {
			e = {op: 'badge_acquire', acquire: val};
			$.post('../general/general.php', JSON.stringify(e), function(data){
				if ( d = response_check(data) ) {
					alert('done');
					$("#tabs_general #refresh").trigger('click');
				}
			});
		} else {		
			if ( id.indexOf('activity') != -1 )
				uc = 'activity_cur = IF(activity_cur+'+val+' <= activity_max, activity_cur+'+val+', activity_max)';			
			else if ( id.indexOf('reset_badge_equip_cooltime') != -1 ) uc = 'badge_willbe_refreshed_at = NULL';
			else if ( id.indexOf('reset_officer_list') != -1 ) uc = 'officer_list_willbe_reset_at = NULL';		 
			else if ( id.indexOf('reset_running_combat') != -1 ) {
				querys = 'UPDATE general SET running_combat_id = NULL WHERE general_id = ' + general_id;
				querys = querys + '; DELETE FROM combat WHERE general_id = ' + general_id + ' AND status = 1';
			}
			else
				uc = id + '=' + id + ' + ' + val;
	
			if ( query == null && querys == null )
				query = 'UPDATE general SET ' + uc + ' WHERE general_id = ' + general_id;
			
			e = {op: 'run', query: query, querys: querys};
			$.post('../admin/god_api.php', JSON.stringify(e), function(data){
				if ( d = response_check(data) ) {
					if ( id.indexOf('reset') != -1 )
						alert('done');
					$("#tabs_general #refresh").trigger('click');
				}
			});
		}
	});

	$("#tabs_chat .refresh").unbind();
	$("#tabs_chat .refresh").click(function(event) {	
		id = $(this).attr('id');
		val = $(this).val();
		console.log('id:' + $(this).attr('id') + ', val: ' + $(this).val());
		general_id = JSON.parse(sessionStorage.general).general_id;

		e = {recv_force: 0};
		if ( id.indexOf('neutral') != -1 )
			e = {recv_force: 3};
		if ( id.indexOf('allies') != -1 )
			e = {recv_force: 1};
		if ( id.indexOf('empire') != -1 )
			e = {recv_force: 2};

		e['ignore_force'] = 1;
		e['op'] = 'get';
		e['fromdb'] = 1;
		e['no_badwords_filter'] = 1;
		$.post('../general/chat.php', JSON.stringify(e), function(data){
			if ( d = response_check(data) ) {
				sessionStorage.chat = JSON.stringify(d.chats);
				refresh_infos();
			}
		});
	});

	$("#tabs_chat #send_chat").unbind();
	$("#tabs_chat #send_chat").click(function(event) {

		recv_force = $('#tabs_chat #recv_force').val();
		chat_body = $('#tabs_chat #chat_body').val();
		
		console.log('sending to ' + recv_force + ' : ' + chat_body);

		if ( chat_body == '' ) {
			alert('empty chat body');
			return false;
		}

		e = {recv_force: 0};
		if ( recv_force.indexOf('neutral') != -1 )
			e = {recv_force: 3};
		if ( recv_force.indexOf('allies') != -1 )
			e = {recv_force: 1};
		if ( recv_force.indexOf('empire') != -1 )
			e = {recv_force: 2};
		e['body'] = chat_body;
		e['op'] = 'send';
		e['ignore_force'] = 1;
		e['ignore'] = 1;
		
		$.post('../general/chat.php', JSON.stringify(e), function(data){
			if ( d = response_check(data) ) {
			//	sessionStorage.chat = JSON.stringify(d.chats);
			//	refresh_infos();
			}
		});
	});
	
	$("#tabs_mail .refresh").unbind();
	$("#tabs_mail .refresh").click(function(event) {	
		id = $(this).attr('id');
		val = $(this).val();
		console.log('id:' + $(this).attr('id') + ', val: ' + $(this).val());
		general_id = JSON.parse(sessionStorage.general).general_id;

		if ( id.indexOf('system') != -1 )
			e = {archived: 0, type: 2};
		if ( id.indexOf('user') != -1 )
			e = {archived: 0, type: 3};
		if ( id.indexOf('archived') != -1 )
			e = {archived: 1};

		e['with_detail'] = 1;
		e['no_badwords_filter'] = 1;
		$.post('../general/mail.php', JSON.stringify(e), function(data){
			if ( d = response_check(data) ) {
				sessionStorage.mail = JSON.stringify(d.mails);
				refresh_infos();
			}
		});
	});
	$("#tabs_mail #send_mail").unbind();
	$("#tabs_mail #send_mail").click(function(event) {

		var mail_type = $('#mail_type').val();
		var mail_recv_type = $('#mail_recv_type').val();
		var mail_recv_name = $('#mail_recv_name').val();		
		var mail_title = $('#mail_title').val();
		var mail_body = $('#mail_body').val();

		var mail_attach_gold = $('#mail_gift_gold').val();
		var mail_attach_honor = $('#mail_gift_honor').val();
		var mail_attach_star = $('#mail_gift_star').val();
		
		var mail_attach_items = [];
		for (var i = 1 ; i <= 4 ; i++ ) {
			var type = $('#mail_gift_item'+i+'_type').val();
			var qty = $('#mail_gift_item'+i+'_qty').val();
			if ( type > 0 ) {
				if ( !qty || (qty <= 0 || qty >= 100) ) {
					alert('invalid qty(allowed: 1~99): ' + qty);
					return false;
				}

				var item = {type_minor: type, qty: qty};
				if ( new String(type).charAt(0) === '3' )
					item['type_major'] = 3; // consume item
				if ( new String(type).charAt(0) === '2' )
					item['type_major'] = 1; // combat item

				mail_attach_items.push(item);
			}
		}

		var mail_attach = {};
		if ( mail_attach_gold && mail_attach_gold > 0 ) mail_attach['gold'] = mail_attach_gold;
		if ( mail_attach_honor && mail_attach_honor > 0) mail_attach['honor'] = mail_attach_honor;
		if ( mail_attach_star && mail_attach_star > 0 ) mail_attach['star'] = mail_attach_star;
		if ( mail_attach_items && mail_attach_items.length > 0 ) mail_attach['items'] = mail_attach_items;
		
		console.log('sending to ' + mail_recv_name + ' :[' + mail_title + ']' + mail_body);
		if ( Object.size(mail_attach) > 0 )
			console.log(' with attachments: ' + JSON.stringify(mail_attach));

		if ( mail_title == '' || mail_body == '' || (mail_recv_type != 3 && mail_recv_name == '') ) {
			alert('empty name or title or body');
			return false;
		}

		var e = {recv_name: mail_recv_name, title:mail_title, body:mail_body};		
		if ( mail_type.indexOf('system') != -1 ) {			
			e['acl'] = 'operator';
			e['gifts'] = mail_attach;			
		} else {
			if ( Object.size(mail_attach) > 0 ) {
				alert('user mail cannot attach resources or items');
				return false;
			}
		}
		e['recv_type'] = mail_recv_type;
		if ( mail_recv_type == 3 )
			e['recv_force'] = 3;
		
		e['op'] = 'send';
		
		$.post('../general/mail.php', JSON.stringify(e), function(data){
			if ( d = response_check(data) ) {
			//	sessionStorage.chat = JSON.stringify(d.chats);
			//	refresh_infos();
			}
		});
	});
	$("#tabs_officer #refresh").unbind();	
	$("#tabs_officer #refresh").click(function() {
		$.post('../army/officer.php', function(data){
			if ( d = response_check(data) ) {
			//	$.cookie('officer', d.officers);
				sessionStorage.officer = JSON.stringify(d.officers);
				refresh_infos();
			}
		});
	});

	$("#tabs_troop #refresh").unbind();
	$("#tabs_troop #refresh").click(function() {
		$.post('../army/troop.php', function(data){
			if ( d = response_check(data) ) {
			//	$.cookie('troop', d.troops);
				sessionStorage.troop = JSON.stringify(d.troops);
				refresh_infos();
			}
		});
	});
	$("#tabs_troop #send_troops").unbind();
	$("#tabs_troop #send_troops").click(function(event) {
		qty = $('#troop_send_qty').val();		
		console.log('sending troops by:' + qty);
		e = {acl:'operator'};
		e['op'] = 'gift';
		e['gift_all_units'] = qty;		
		$.post('../army/troop.php', JSON.stringify(e), function(data){
			if ( d = response_check(data) ) {
			//	$("#tabs_item #refresh").trigger('click');
			}
		});
	});
	
	$("#tabs_construction #refresh").unbind();
	$("#tabs_construction #refresh").click(function() {
		$.post('../build/construction.php', function(data){
			if ( d = response_check(data) ) {
			//	$.cookie('construction', d.constructions);
				sessionStorage.construction = JSON.stringify(d.constructions);
				refresh_infos();
			}
		});
	});
	$("#tabs_tile #refresh").unbind();
	$("#tabs_tile #refresh").click(function() {
		$.post('../battlefield/tile.php', function(data){
			if ( d = response_check(data) ) {
			//	$.cookie('tile', d.tiles);
				sessionStorage.tile = JSON.stringify(d.tiles);
				refresh_infos();
			}
		});
	});
	$("#tabs_tile #refresh_ranks").unbind();
	$("#tabs_tile #refresh_ranks").click(function() {
		$.post('../battlefield/tile.php?op=ranks', function(data){
			if ( d = response_check(data) ) {
				e = $('#tabs_tile #json');
				if ( e ) {
					ctx = d.tiles;
					if ( ctx ) e.html('<pre><code>' + JSON.stringify(ctx, null, 4) + '</code></pre>');
					else e.text('N/A, needs refresh');			
				}
			}
		});
	});	
	$("#tabs_battlefield #refresh").unbind();
	$("#tabs_battlefield #refresh").click(function() {
		$.post('../battlefield/tile.php', function(data){
			if ( d = response_check(data) ) {
			//	$.cookie('tile', d.tiles);
				sessionStorage.battlefield = JSON.stringify(d.battlefield);
				refresh_infos();
			}
		});
	});
	$("#tabs_battlefield #rebuild_battlefield").unbind();
	$("#tabs_battlefield #rebuild_battlefield").click(function() {
		if ( r = confirm('cannot rollback, proceed?') ) {
			password = prompt('Enter password');
			if ( password != null ) {
				if ( password == '9906' ) {
					console.log('rebuild_battlefield');
					$.post('../battlefield/tile.php?op=rebuild_battlefield', function(data){
						if ( d = response_check(data) ) {
							alert('done');
						}
					});
				}
			}			
		}
	});
	$("#tabs_battlefield #initialize_battlefield").unbind();
	$("#tabs_battlefield #initialize_battlefield").click(function() {
		if ( r = confirm('cannot rollback, proceed?') ) {
			password = prompt('Enter password');
			if ( password != null ) {
				if ( password == '9906' ) {
					console.log('initialize_battlefield');
					var e = {query: 'DELETE FROM battlefield'};
					$.post('../admin/god_api.php?op=run', JSON.stringify(e), function(data){
						if ( d = response_check(data) ) {
							alert('done');
						}
					});
				}
			}			
		}
	});	
	$("#tabs_combat #refresh").unbind();
	$("#tabs_combat #refresh").click(function() {
		$.post('../battlefield/combat.php', function(data){
			if ( d = response_check(data) ) {
				sessionStorage.combat = JSON.stringify(d.combats);
				refresh_infos();
			}
		});
	});

	$("#tabs_item #send_combat_item").unbind();
	$("#tabs_item #send_combat_item").click(function(event) {
		minor = $('#item_combat_minor').val();		
		console.log('sending item:' + minor);
		e = {type_major:1, type_minor:minor, qty:1};
		e['op'] = 'gift';
		e['acl'] = 'operator';
		e['ignore'] = 1;		
		$.post('../army/item.php', JSON.stringify(e), function(data){
			if ( d = response_check(data) ) {
			//	$("#tabs_item #refresh").trigger('click');
			}
		});
	});
	$("#tabs_item #send_consume_item").unbind();
	$("#tabs_item #send_consume_item").click(function(event) {
		minor = $('#item_consume_minor').val();		
		console.log('sending consume item:' + minor);
		e = {type_major:3, type_minor:minor, qty:1};
		e['op'] = 'gift';
		e['acl'] = 'operator';
		e['ignore'] = 1;		
		$.post('../army/item.php', JSON.stringify(e), function(data){
			if ( d = response_check(data) ) {
			//	$("#tabs_item #refresh").trigger('click');
			}
		});
	});
	
	$("#tabs_item #refresh").unbind();
	$("#tabs_item #refresh").click(function() {
		$.post('../army/item.php', function(data){
			if ( d = response_check(data) ) {
				sessionStorage.item = JSON.stringify(d.items);
				refresh_infos();
			}
		});
	});
	
	$("#dialog").dialog({
		autoOpen: false,
		dialogClass: "no-close",
		buttons: [
			{
				text: "OK",
				click: function() {
					$( this ).dialog( "close" );
				}
			}
		]
	});
	
	$.cookie.json = false;
	PHPSESSID = $.cookie('PHPSESSID');
	$.cookie.json = true;
	
	if ( !PHPSESSID || !sessionStorage.user ) {
		console.log('not logged in');
		//alert("No cookie exists");
		
		$.msg('unblock');

		$('#register').show();
		$('#login').show();
		$('#logout').hide();
		$('#tabs').hide();
	} else {
		refresh_infos();
		
		$('#register').hide();
		$('#login').hide();
		$('#logout').show();
		$('#tabs').show();
	}
};

$(document).ready(function(){
	console.log('on ready!');

//	$("#recv_force").combobox();        
	
	$.ajaxSetup({
	    beforeSend:function(){
	        $.msg({bgPath : 'images/', content : 'Processing request ... (automatically dismisses on complete)', fadeIn:0, autoUnblock : false, clickUnblock : false});
	    },
	    error:function(){
	        $.msg('unblock');
	    },
	    complete:function(){
	        $.msg('unblock');
	    }
	});
	
	$.cookie.json = true;
	refresh();
});
</script>

</head>

<body id='mybody'>
	<form id='register' method=get>
		force: <input type='radio' name='force' value=1 checked>연합 <input type='radio' name='force' value=2>제국 username: <input
			type='text' name='username'
		> password: <input type='password' name='password'> <input type=submit value='register'>
	</form>
	<form id='login' method=get>
		username: <input type='text' name='username'> password: <input type='password' name='password'> <input type=submit
			value='login'
		>
	</form>
	<form id='logout' method=get>
		<input type=submit value='logout'>
	</form>
	<div id="tabs">
		<ul>
			<li><a href="#tabs_general">general</a></li>
			<li><a href="#tabs_officer">officer</a></li>
			<li><a href="#tabs_troop">troop</a></li>
			<li><a href="#tabs_construction">construction</a></li>
			<li><a href="#tabs_tile">tile</a></li>
			<li><a href="#tabs_battlefield">battlefield</a></li>
			<li><a href="#tabs_combat">combat</a></li>
			<li><a href="#tabs_item">item</a></li>
			<li><a href="#tabs_chat">chat</a></li>
			<li><a href="#tabs_mail">mail</a></li>
			<li><a href="#tabs_user">user</a></li>
			<li><a href="#tabs_serverinfo">serverinfo</a></li>
			<li><a href="#tabs_constants">constants</a></li>
		</ul>
		<div id="tabs_general">
			<button id='refresh'>refresh</button>
			<button id='reset'>reset-general data</button>
			<button class=edit id='reset_badge_equip_cooltime'>reset_badge_equip_cooltime</button>
			<button class=edit id='reset_running_combat'>reset_running_combat</button>
			<button class=edit id='reset_officer_list'>reset_officer_list</button>
			<button class=edit id='reset_daily_quest'>reset_daily_quest</button>
			<button class=edit id='tutorial_reset'>tutorial_reset</button>
			<br />
			<button class=edit id='gold' value=1000>gold+1000</button>
			<button class=edit id='gold' value=10000>gold+10000</button>
			<button class=edit id='honor' value=100>honor+100</button>
			<button class=edit id='honor' value=1000>honor+1000</button>
			<button class=edit id='star' value=100>star+100</button>
			<button class=edit id='star' value=1000>star+1000</button>
			<button class=edit id='activity' value=10>activity+10</button>
			<button class=edit id='exp_cur' value=1>exp+1</button>
			<button class=edit id='exp_cur' value=1000>exp+1000</button>
			<br />
			<button class=edit id='badge_clear' value='none'>badge clear</button>
			<button class=edit id='badge_all' value='all'>badge get all</button>
			<button class=edit id='badge_random' value='random'>badge get random</button>
			<br />
			<button id='reset_battlefield'>reset-battlefield/combat data</button>
			<button class=edit id='clear_constant_caches'>clear_constant_caches</button>
			<button id='delete'>delete general</button>
			<button id='resetdb'>reset-database</button>

			<div id='json' class='tab_body'></div>
			<div id='grid' class='tab_grid' style="height: 500px;"></div>
			<div id="dialog" title="Edit general value"></div>
		</div>
		<div id="tabs_officer">
			<button id='refresh'>refresh</button>
			<div id='json' class='tab_body'></div>
			<div id='grid' class='tab_grid' style="height: 500px;"></div>
		</div>
		<div id="tabs_troop">
			<button id='refresh'>refresh</button>
			<label>Send Troops:</label> <select id="troop_send_qty">
				<option value="10">all_troops+10</option>
				<option value="20">all_troops+20</option>
				<option value="50">all_troops+50</option>
				<option value="100">all_troops+100</option>
				<option value="200">all_troops+200</option>
			</select>
			<button id='send_troops'>send_troops</button>
			<div id='json' class='tab_body'></div>
			<div id='grid' class='tab_grid' style="height: 500px;"></div>
		</div>
		<div id="tabs_construction">
			<button id='refresh'>refresh</button>
			<div id='json' class='tab_body'></div>
			<div id='grid' class='tab_grid' style="height: 500px;"></div>
		</div>
		<div id="tabs_tile">
			<button id='refresh'>refresh</button>
			<button id='refresh_ranks'>refresh with rankings</button>
			<div id='json' class='tab_body'></div>
			<div id='grid' class='tab_grid' style="height: 500px;"></div>
		</div>
		<div id="tabs_battlefield">
			<button id='refresh'>refresh</button>
			<button id='rebuild_battlefield'>rebuild_battlefield</button>
			<button id='initialize_battlefield'>initialize_battlefield</button>
			<a href="battlefield_dashboard.php" target='_blank'>open battlefield_dashboard</a>
			<div id='json' class='tab_body'></div>
			<div id='grid' class='tab_grid' style="height: 500px;"></div>
		</div>
		<div id="tabs_combat">
			<button id='refresh'>refresh</button>
			<div id='json' class='tab_body'></div>
			<div id='grid' class='tab_grid' style="height: 500px;"></div>
		</div>
		<div id="tabs_item">
			<button id='refresh'>refresh</button>
			<br /> <label>Send Combat Item:</label> <select id="item_combat_minor">
				<?php
				foreach(item::get_combats() as $id => $ELEM)
					echo sprintf("<option value='$id'>[%s][%s] %s</option>", $FORCE_MAP[$ELEM['force']], $ITEM_TYPE_MAP[$ITEM_TYPE_MAJOR_COMBATS], $ELEM['name']);
				?>
			</select>
			<button id='send_combat_item'>send_combat_item</button>
			<br /> <label>Send Consume Item:</label> <select id="item_consume_minor">
				<?php
				foreach(item::get_consumes() as $id => $ELEM)
					echo sprintf("<option value='$id'>[%s][%s] %s</option>", $FORCE_MAP[$ELEM['force']], $ITEM_TYPE_MAP[$ITEM_TYPE_MAJOR_CONSUMES], $ELEM['name']);
				?>
			</select>
			<button id='send_consume_item'>send_consume_item</button>

			<div id='json' class='tab_body'></div>
			<div id='grid' class='tab_grid' style="height: 500px;"></div>
		</div>
		<div id="tabs_chat">
			<button class=refresh id='refresh_neutral'>refresh_neutral</button>
			<button class=refresh id='refresh_allies'>refresh_allies</button>
			<button class=refresh id='refresh_empire'>refresh_empire</button>
			<br /> <label>Send Chat To:</label> <select id="recv_force">
				<option value="neutral">neutral</option>
				<option value="allies">allies</option>
				<option value="empire">empire</option>
			</select> <label>Body:</label> <input type='text' id='chat_body' />

			<button id='send_chat'>send_chat</button>
			<div id='json' class='tab_body'></div>
			<div id='grid' class='tab_grid' style="height: 500px;"></div>
		</div>
		<div id="tabs_mail">
			<button class=refresh id='refresh_system_mails'>refresh_system_mails</button>
			<button class=refresh id='refresh_user_mails'>refresh_user_mails</button>
			<button class=refresh id='refresh_archived_mails'>refresh_archived_mails</button>
			<br /> <label>Send Mail As:<select id="mail_type">
					<option value="user">user</option>
					<option value="system">system</option>
			</select>
			</label> <label>recv_type: <select id="mail_recv_type">
					<option value="1">one user</option>
					<option value="2">legion members</option>
					<option value="3">all_user</option>
			</select>
			</label> <label>recv_name/id:<input type='text' id='mail_recv_name' />
			</label><label>Title:<input type='text' id='mail_title' />
			</label><label>Body:<input type='text' id='mail_body' />
			</label>
			<button id='send_mail'>send_mail</button>
			<br> <label>attached gold<input type='text' id='mail_gift_gold'>
			</label> <label>attached honor<input type='text' id='mail_gift_honor'>
			</label> <label>attached star<input type='text' id='mail_gift_star'>
			</label> <br> <label>attach item1 <select id="mail_gift_item1_type">
					<?php
					global $FORCE_MAP;
					echo sprintf("<option value='0'>%s</option>", '보내지 않음');
					foreach(item::get_combats() as $id => $ELEM)
						echo sprintf("<option value='$id'>[%s][%s] %s</option>", $FORCE_MAP[$ELEM['force']], $ITEM_TYPE_MAP[$ITEM_TYPE_MAJOR_COMBATS], $ELEM['name']);
					foreach(item::get_consumes() as $id => $ELEM)
						echo sprintf("<option value='$id'>[%s][%s] %s</option>", $FORCE_MAP[$ELEM['force']], $ITEM_TYPE_MAP[$ITEM_TYPE_MAJOR_CONSUMES], $ELEM['name']);
					?>
			</select>
			</label> qty<input type='text' id='mail_gift_item1_qty' value=1><br> <label>attach item2 <select
				id="mail_gift_item2_type"
			>
					<?php
					echo sprintf("<option value='0'>%s</option>", '보내지 않음');
					foreach(item::get_combats() as $id => $ELEM)
						echo sprintf("<option value='$id'>[%s][%s] %s</option>", $FORCE_MAP[$ELEM['force']], $ITEM_TYPE_MAP[$ITEM_TYPE_MAJOR_COMBATS], $ELEM['name']);
					foreach(item::get_consumes() as $id => $ELEM)
						echo sprintf("<option value='$id'>[%s][%s] %s</option>", $FORCE_MAP[$ELEM['force']], $ITEM_TYPE_MAP[$ITEM_TYPE_MAJOR_CONSUMES], $ELEM['name']);
					?>
			</select>
			</label> qty<input type='text' id='mail_gift_item2_qty' value=1><br> <label>attach item3 <select
				id="mail_gift_item3_type"
			>
					<?php
					echo sprintf("<option value='0'>%s</option>", '보내지 않음');
					foreach(item::get_combats() as $id => $ELEM)
						echo sprintf("<option value='$id'>[%s][%s] %s</option>", $FORCE_MAP[$ELEM['force']], $ITEM_TYPE_MAP[$ITEM_TYPE_MAJOR_COMBATS], $ELEM['name']);
					foreach(item::get_consumes() as $id => $ELEM)
						echo sprintf("<option value='$id'>[%s][%s] %s</option>", $FORCE_MAP[$ELEM['force']], $ITEM_TYPE_MAP[$ITEM_TYPE_MAJOR_CONSUMES], $ELEM['name']);
					?>
			</select>
			</label> qty<input type='text' id='mail_gift_item3_qty' value=1><br> <label>attach item4 <select
				id="mail_gift_item4_type"
			>
					<?php
					echo sprintf("<option value='0'>%s</option>", '보내지 않음');
					foreach(item::get_combats() as $id => $ELEM)
						echo sprintf("<option value='$id'>[%s][%s] %s</option>", $FORCE_MAP[$ELEM['force']], $ITEM_TYPE_MAP[$ITEM_TYPE_MAJOR_COMBATS], $ELEM['name']);
					foreach(item::get_consumes() as $id => $ELEM)
						echo sprintf("<option value='$id'>[%s][%s] %s</option>", $FORCE_MAP[$ELEM['force']], $ITEM_TYPE_MAP[$ITEM_TYPE_MAJOR_CONSUMES], $ELEM['name']);
					?>
			</select>
			</label> qty<input type='text' id='mail_gift_item4_qty' value=1><br>

			<hr>
			<div id='json' class='tab_body'></div>
			<div id='grid' class='tab_grid' style="height: 500px;"></div>
		</div>
		<div id="tabs_user">
			<div id='json' class='tab_body'></div>
			<div id='grid' class='tab_grid' style="height: 500px;"></div>
		</div>
		<div id="tabs_serverinfo">
			<div id='json' class='tab_body'></div>
			<div id='grid' class='tab_grid' style="height: 500px;"></div>
		</div>
		<div id="tabs_constants">
			<div id='json' class='tab_body'></div>
			<div id='grid' class='tab_grid' style="height: 500px;"></div>
		</div>
	</div>

</body>

</html>


