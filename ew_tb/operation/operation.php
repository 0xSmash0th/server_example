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
<script type="text/javascript" src="../admin/js/jquery-2.0.3.min.js"></script>
<script type="text/javascript" src="../admin/js/jquery.cookie.js"></script>

<!-- for jquery-ui -->
<link href="../admin/css/ui-lightness/jquery-ui-1.10.3.custom.css" rel="stylesheet">
<script src="../admin/js/jquery-ui-1.10.3.custom.js"></script>
<style>
.ui-tabs-vertical {
	width: 55em;
}

.ui-tabs-vertical .ui-tabs-nav {
	padding: .2em .1em .2em .2em;
	float: left;
	width: 12em;
}

.ui-tabs-vertical .ui-tabs-nav li {
	clear: left;
	width: 100%;
	border-bottom-width: 1px !important;
	border-right-width: 0 !important;
	margin: 0 -1px .2em 0;
}

.ui-tabs-vertical .ui-tabs-nav li a {
	display: block;
}

.ui-tabs-vertical .ui-tabs-nav li.ui-tabs-active {
	padding-bottom: 0;
	padding-right: .1em;
	border-right-width: 1px;
	border-right-width: 1px;
}

.ui-tabs-vertical .ui-tabs-panel {
	padding: 1em;
	float: right;
	width: 40em;
}
</style>

<!-- for jquery-ui datetimepicker -->
<script type="text/javascript" src="../admin/js/jquery-ui-timepicker-addon.js"></script>
<link rel="stylesheet" href="../admin/css/jquery-ui-timepicker-addon.css" type="text/css" />

<!-- jquery file downloader -->
<script type="text/javascript" src="../admin/js/jquery.fileDownload.js"></script>

<!-- for jquery-msg -->
<script type="text/javascript" src="../admin/js/jquery.center.min.js"></script>
<script type="text/javascript" src="../admin/js/jquery.msg.js"></script>
<link media="screen" href="../admin/css/jquery.msg.css" rel="stylesheet" type="text/css">

<!-- for slickgrid -->
<link rel="stylesheet" href="../admin/slickgrid/slick.grid.css" type="text/css" />
<link rel="stylesheet" href="../admin/slickgrid/controls/slick.pager.css" type="text/css" />
<!-- <link rel="stylesheet" -->
<!-- 	href="../admin/slickgrid/css/smoothness/jquery-ui-1.8.16.custom.css" -->
<!-- 	type="text/css" /> -->
<!-- <script src="../admin/slickgrid/lib/jquery-1.7.min.js"></script> -->
<script src="../admin/slickgrid/lib/jquery.event.drag-2.2.js"></script>
<script src="../admin/slickgrid/slick.core.js"></script>
<script src="../admin/slickgrid/slick.grid.js"></script>
<script src="../admin/slickgrid/slick.formatters.js"></script>
<script src="../admin/slickgrid/slick.dataview.js"></script>
<script src="../admin/slickgrid/controls/slick.pager.js"></script>
<script src="../admin/slickgrid/plugins/slick.autotooltips.js"></script>

<script type="text/javascript" src="operation.js"></script>
<script type="text/javascript" src="utils.js"></script>
<script type="text/javascript" src="tabs_opadmin.js"></script>
<script type="text/javascript" src="tabs_user.js"></script>
<script type="text/javascript" src="tabs_maintain.js"></script>
<script type="text/javascript" src="tabs_community.js"></script>
<script type="text/javascript" src="tabs_message.js"></script>
<script type="text/javascript" src="tabs_notice.js"></script>
<script type="text/javascript" src="tabs_statistics.js"></script>
<script type="text/javascript" src="tabs_event.js"></script>

<!-- 
<script type="text/javascript" src="tabs_item.js"></script>
-->


</head>

<body id='mybody'>
	<div id='loginout'>
		<div id='div_login'>
			Eternal War Operation Tool<br /> username: <input type='text' name='username'> password: <input type='password'
				name='password'
			>
			<button id='login'>login</button>
		</div>
		<div id='div_logout'>
			<button id='logout'>logout</button>
		</div>
	</div>

	<div id="tabs">
		<ul>
			<li><a href="#tabs_opadmin">운영툴 관리</a></li>
			<li><a href="#tabs_user">계정 관리</a></li>
			<li><a href="#tabs_item">아이템</a></li>
			<li><a href="#tabs_message">메시지</a></li>
			<li><a href="#tabs_community">커뮤니티</a></li>
			<li><a href="#tabs_event">이벤트</a></li>
			<li><a href="#tabs_maintain">점검</a></li>
			<li><a href="#tabs_notices">공지</a></li>
			<li><a href="#tabs_statistics">통계</a></li>
		</ul>
		<div id="tabs_opadmin">
			<ul>
				<li><a href="#tabs_mypage">내 계정</a></li>
				<li><a href="#tabs_auth">계정권한</a></li>
				<li><a href="#tabs_action">사용기록</a></li>
			</ul>
			<div id="tabs_mypage"></div>
			<div id="tabs_auth">
				<div id='auth_list'>
					권한 정보<br>
					<table border=1 width=50%>
						<tr>
							<th>등급</th>
							<th>게임정보</th>
							<th>아이템</th>
							<th>이벤트</th>
							<th>공지</th>
							<th>계정권한 관리</th>
						</tr>
						<tr>
							<td>master</td>
							<td>수정</td>
							<td>수정</td>
							<td>수정</td>
							<td>수정</td>
							<td>수정</td>
						</tr>
						<tr>
							<td>operator</td>
							<td>수정</td>
							<td>수정</td>
							<td>수정</td>
							<td>수정</td>
							<td>-</td>
						</tr>
						<tr>
							<td>monitor</td>
							<td>보기</td>
							<td>보기</td>
							<td>보기</td>
							<td>수정</td>
							<td>-</td>
						</tr>
					</table>
					<br> 권한 리스트
					<button id='btn_user_add'>사용자 추가</button>
					<br>
					<table border=1 width=50%>
						<tr>
							<th>등급</th>
							<th>계정명</th>
							<th>수정</th>
						</tr>
						<tr>
							<td>master</td>
							<td id='td_master'></td>
							<td><button id='btn_master' class='auth_detail'>자세히</button></td>
						</tr>
						<tr>
							<td>operator</td>
							<td id='td_operator'></td>
							<td><button id='btn_operator' class='auth_detail'>자세히</button></td>
						</tr>
						<tr>
							<td>monitor</td>
							<td id='td_monitor'></td>
							<td><button id='btn_monitor' class='auth_detail'>자세히</button></td>
						</tr>
					</table>
				</div>
				<div id='user_list' style="width: 100%">
					<div id="pager" style="width: 100%; height: 20px;"></div>
					<div id='grid' class='tab_grid' style="width: 100%; height: 200px;"></div>
				</div>
				<div id='opuser_mod_dialog'>
					<table border=1>
						<tr>
							<th>계정명</th>
							<th>등급</th>
							<th>권한변경</th>
						</tr>
						<tr>
							<td id='username'></td>
							<td id='acl'></td>
							<td><select id='new_acl'>
									<option value='operator'>operator</option>
									<option value='monitor'>monitor</option>
							</select></td>
						</tr>
					</table>
					<button id='btn_modify'>변경</button>
				</div>
			</div>
			<div id="tabs_action">
				<div id='search_filter' style="width: 100%">
					일자: <label>From: <input type="text" id="search_date_from" />
					</label> <label>To: <input type="text" id="search_date_to" />
					</label><br /> 계정: <input type="text" id="search_username" /> 변경 항목<select id='search_action_type'>
						<option value='all'>전체</option>
						<option value='maintain'>점검공지</option>
						<option value='notice'>인게임공지</option>
						<option value='gameinfo'>게임정보</option>
					</select>
					<button id='search_run'>검색</button>
					<button id='search_download'>다운로드</button>
				</div>
				<div id='action_list' style="width: 100%">
					<div id="pager" style="width: 100%; height: 20px;"></div>
					<div id='grid' class='tab_grid' style="width: 100%; height: 200px;"></div>
				</div>
			</div>
		</div>
		<div id="tabs_user">
			<ul>
				<li><a href="#tabs_manage">계정 관리</a></li>
				<li><a href="#tabs_detail">계정 상세</a></li>
			</ul>
			<div id="tabs_manage">
				<div id='search_filter' style="width: 100%">
					구분 검색: <label>마켓<select id='search_market'></select>
					</label> <label>접속 <select id='search_online'></select>
					</label> <label>계정 상태 <select id='search_account_status'></select>
					</label> <label>계정 이름<input type="text" id="search_username" />
					</label> <br /> 일자: <label>종류<select id='search_access_type'>
					</select>
					</label><label>From: <input type="text" id="user_manage_search_date_from" />
					</label> <label>To: <input type="text" id="user_manage_search_date_to" />
					</label> <br /> 매출: <label>최소 매출<input type="text" id="search_payment_min">
					</label> <label>최대 매출<input type="text" id="search_payment_max">
					</label>
					<button id='search_run'>검색</button>
					<button id='search_download'>다운로드</button>
				</div>
				<div id='search_summary' style="width: 100%">
					<div id='user_summary' style="width: 100%">
						<div id='grid' class='tab_grid' style="width: 100%; height: 80px;"></div>
					</div>
				</div>
				<div id='user_list' style="width: 100%">
					<div id="pager" style="width: 100%; height: 20px;"></div>
					<div id='grid' class='tab_grid' style="width: 100%; height: 200px;"></div>
				</div>
				<div id='user_status_dialog'>
					<label>닉네임<input readonly="readonly" id='username'>
					</label><br> <label>계정 상태<select id='new_status'></select>
					</label> <label>사유<select id='reason'></select>
					</label><br> 기간설정 <label>From: <input type="text" id="status_date_from" />
					</label> <label>To: <input type="text" id="status_date_to" />
					</label><br>
					<hr>
					<label>계정 상태 변경 이력</label>
					<div id='status_list' style="width: 100%">
						<div id="pager" style="width: 100%; height: 20px;"></div>
						<div id='grid' class='tab_grid' style="width: 100%; height: 200px;"></div>
					</div>
				</div>
			</div>
			<div id="tabs_detail">
				<label>username</label> <input type="text" readonly="readonly" value=""> <label>mobile dev number</label> <input
					type="text" readonly="readonly" value=""
				> <label>market</label> <input type="text" readonly="readonly" value=""> <label>unique dev id</label> <input
					type="text" readonly="readonly" value=""
				> <br /> <label>created_at</label> <input type="text" readonly="readonly" value=""> <label>login_at</label> <input
					type="text" readonly="readonly" value=""
				> <label>payment_sum</label> <input type="text" readonly="readonly" value=""> <label>status</label> <input
					type="text" readonly="readonly" value=""
				> <br />
				<div id='tabs_user_detail'>
					<ul>
						<li><a href="#tabs_detail_general">장군</a></li>
						<li><a href="#tabs_detail_skill">스킬</a></li>
						<li><a href="#tabs_detail_badge">훈장</a></li>
					</ul>
					<div id="tabs_detail_general">tabs_detail_general</div>
					<div id="tabs_detail_skill">tabs_detail_skill</div>
					<div id="tabs_detail_badge">tabs_detail_badge</div>
				</div>
			</div>
		</div>
		<div id="tabs_item">
			<ul>
				<li><a href="#tabs_gift">아이템 지급</a></li>
			</ul>
			<div id="tabs_gift">tabs_gift</div>
			<div id='json' class='tab_body'></div>
			<div id='grid' class='tab_grid' style="height: 500px;"></div>
		</div>
		<div id="tabs_message">
			<ul>
				<li><a href="#tabs_mail">쪽지</a></li>
				<li><a href="#tabs_push">Push 메시지</a></li>
			</ul>
			<div id="tabs_mail">
				<div id='mailq' style="width: 100%">
					<div style='float: left; width: 49%;'>
						<label>수신 대상<select id='recv_type' /></select>
						</label> <label>수신 마켓<select id='recv_market' /></select>
						</label> <label>수신 진영<select id='recv_force' /></select>
						</label> <label>수신 이름<input type="text" id="recv_name" />
						</label> <br /> <label>발송일시:(비어있으면 즉시 발송)<input type="text" id="mailq_send_at" />
						</label><br> <label>제목:<input type='text' id='mailq_title' />
						</label><br> <label>내용:<textarea rows='5' cols='40' id='mailq_body'></textarea>
						</label><br>
					</div>
					<div style='float: right; width: 49%;'>
						<label>첨부 gold<input type='text' id='mailq_gift_gold'>
						</label> <label>첨부 honor<input type='text' id='mailq_gift_honor'>
						</label> <label>첨부 star<input type='text' id='mailq_gift_star'>
						</label><br> 첨부 아이템 <br>
						<?php
						global $FORCE_MAP, $ITEM_TYPE_MAP;
						for ($i = 1 ; $i <= 4 ; $i++ ) {
								echo sprintf("<label>아이템 %d: <select id='mailq_gift_item_type_%d'>", $i, $i);

								echo sprintf("<option value='0'>%s</option>", '보내지 않음');
								foreach(item::get_combats() as $id => $ELEM)
									echo sprintf("<option value='$id'>[%s][%s] %s</option>", $FORCE_MAP[$ELEM['force']], $ITEM_TYPE_MAP[$ITEM_TYPE_MAJOR_COMBATS], $ELEM['name']);
								foreach(item::get_consumes() as $id => $ELEM)
									echo sprintf("<option value='$id'>[%s][%s] %s</option>", $FORCE_MAP[$ELEM['force']], $ITEM_TYPE_MAP[$ITEM_TYPE_MAJOR_CONSUMES], $ELEM['name']);

								echo "</select></label>";
								echo "<label>수량<input type='text' id='mailq_gift_item_qty_$i' value=0></label><br>";
							}
							?>
					</div>
					<div style="clear: both; font-size: 1px;"></div>
					<hr />
					<button id='mailq_add'>발송 리스트에 추가</button>
					<button id='btn_refresh'>새로 고침</button>
				</div>
				<div id='mailq_list' style="width: 100%">
					<div id="pager" style="width: 100%; height: 20px;"></div>
					<div id='grid' class='tab_grid' style="width: 100%; height: 400px;"></div>
				</div>
			</div>
			<div id="tabs_push">tabs_push</div>
			<div id='json' class='tab_body'></div>
			<div id='grid' class='tab_grid' style="height: 500px;"></div>
		</div>
		<div id="tabs_community">
			<ul>
				<li><a href="#tabs_chats">채팅 검색</a></li>
				<li><a href="#tabs_badwords">금칙어 관리</a></li>
			</ul>
			<div id="tabs_chats">
				<div id='search_filter' style="width: 100%">
					구분 검색: <label>수신 진영<select id='search_recv_force'>
							<option value='3'>중립(전체)</option>
							<option value='1'>연합</option>
							<option value='2'>제국</option>
					</select>
					</label> <label>작성자<input type="text" id="search_username" />
					</label><label>대화 내용<input type="text" id="search_body" />
					</label> <br /> 작성 일자: <label>From: <input type="text" id="chats_search_date_from" />
					</label> <label>To: <input type="text" id="chats_search_date_to" />
					</label>
					<button id='search_run'>검색</button>
					<button id='search_download'>다운로드</button>
				</div>
				<div id='chat_list' style="width: 100%">
					<div id="pager" style="width: 100%; height: 20px;"></div>
					<div id='grid' class='tab_grid' style="width: 100%; height: 400px;"></div>
				</div>
			</div>
			<div id="tabs_badwords">
				<label>금칙어 <input type='text' id=badword>
				</label>
				<button id='badword_add'>추가</button>
				<button id='btn_refresh'>새로 고침</button>
				<br> 금칙어 리스트
				<div id='badword_list' style="width: 100%">
					<div id="pager" style="width: 100%; height: 20px;"></div>
					<div id='grid' class='tab_grid' style="width: 100%; height: 400px;"></div>
				</div>
			</div>
		</div>
		<div id="tabs_event">
			<ul>
				<li><a href="#tabs_manage">이벤트 관리</a></li>
				<li><a href="#tabs_coupon">쿠폰 관리</a></li>
			</ul>
			<div id="tabs_manage">tabs_manage</div>
			<div id="tabs_coupon">
				<div id='coupon' style="width: 100%">
					<div style='float: left; width: 49%;'>
						<label>수신 가능 마켓<select id='recv_market' /></select>
						</label> <label>수신 가능 진영<select id='recv_force' /></select>
						</label> <label>수신 가능 이름<input type="text" id="recv_name" />
						</label> <br /> <label>유효 일시:(비어있으면 즉시 유효)<input type="text" id="coupon_send_at" />
						</label><br> <label>제목:<input type='text' id='coupon_title' />
						</label><br> <label>내용:<textarea rows='5' cols='40' id='coupon_body'></textarea>
						</label><br>
						<label>동일 쿠폰 생성 개수<input type='text' id='coupon_qty' value='1' />
					</label>
					<button id='coupon_add'>쿠폰 생성</button>
					</div>
					<div style='float: right; width: 49%;'>
						<label>첨부 gold<input type='text' id='coupon_gift_gold'>
						</label> <label>첨부 honor<input type='text' id='coupon_gift_honor'>
						</label> <label>첨부 star<input type='text' id='coupon_gift_star'>
						</label><br> 첨부 아이템 <br>
						<?php
						global $FORCE_MAP, $ITEM_TYPE_MAP;
						for ($i = 1 ; $i <= 4 ; $i++ ) {
								echo sprintf("<label>아이템 %d: <select id='coupon_gift_item_type_%d'>", $i, $i);

								echo sprintf("<option value='0'>%s</option>", '보내지 않음');
								foreach(item::get_combats() as $id => $ELEM)
									echo sprintf("<option value='$id'>[%s][%s] %s</option>", $FORCE_MAP[$ELEM['force']], $ITEM_TYPE_MAP[$ITEM_TYPE_MAJOR_COMBATS], $ELEM['name']);
								foreach(item::get_consumes() as $id => $ELEM)
									echo sprintf("<option value='$id'>[%s][%s] %s</option>", $FORCE_MAP[$ELEM['force']], $ITEM_TYPE_MAP[$ITEM_TYPE_MAJOR_CONSUMES], $ELEM['name']);

								echo "</select></label>";
								echo "<label>수량<input type='text' id='coupon_gift_item_qty_$i' value=0></label><br>";
							}
							?>
					</div>
					<div style="clear: both; font-size: 1px;"></div>
					<hr />
					<label>검색 조건 <select id='search_type'></select></label>
					<label>검색 내용<input type="text" id="search_value" /></label>	
					<button id='btn_refresh'>쿠폰 검색</button>
					<button id='search_download'>다운로드</button>
				</div>
				<div id='coupon_list' style="width: 100%">
					<div id="pager" style="width: 100%; height: 20px;"></div>
					<div id='grid' class='tab_grid' style="width: 100%; height: 400px;"></div>
				</div>
			</div>
			<div id='json' class='tab_body'></div>
			<div id='grid' class='tab_grid' style="height: 500px;"></div>
		</div>
		<div id="tabs_maintain">
			<ul>
				<li><a href="#tabs_manage">점검 관리</a></li>
			</ul>
			<div id="tabs_manage">
				점검 상태: <select id='maintenance_active'><option value='1'>점검 중</option>
					<option value='0'>서비스 중</option>
				</select>
				<button id='maintenance_set'>상태 변경</button>
				<button id='btn_refresh'>새로 고침</button>
				<hr>
				점검 메시지:
				<textarea id='maintenance_message' rows="3" cols="40"></textarea>
				<button id='message_set'>메시지 저장</button>
				<hr>
				<div>
					<div style='float: left; width: 49%;'>
						점검 중 접속 가능 닉네임 <label><input type="text" id='allowed_username'> </label>
						<button id='allowed_username_add'>추가</button>
						<div id='username_list' style="width: 100%">
							<div id="pager" style="width: 100%; height: 20px;"></div>
							<div id='grid' class='tab_grid' style="width: 100%; height: 200px;"></div>
						</div>
					</div>
					<div style='float: right; width: 49%;'>
						점검 중 접속 가능 장치아이디 <label><input type="text" id='allowed_devuuid'> </label>
						<button id='allowed_devuuid_add'>추가</button>
						<div id='devuuid_list' style="width: 100%">
							<div id="pager" style="width: 100%; height: 20px;"></div>
							<div id='grid' class='tab_grid' style="width: 100%; height: 200px;"></div>
						</div>
					</div>
					<div style="clear: both; font-size: 1px;"></div>
				</div>
			</div>
			<div id='json' class='tab_body'></div>
			<div id='grid' class='tab_grid' style="height: 500px;"></div>
		</div>
		<div id="tabs_notices">
			<ul>
				<li><a href="#tabs_manage_notice">공지 관리</a></li>
				<li><a href="#tabs_manage_banner">배너 관리</a></li>
			</ul>
			<div id="tabs_manage_notice">
				<div id='notice' style="width: 100%">
					<div style="width: 100%">
						<div style='float: left; width: 49%;'>

							<label>공지 마켓<select id='recv_market' /></select>
							</label> <label>공지 진영<select id='recv_force' /></select>
							</label> <br> <label>공지 시작 일시:(비어있으면 즉시)<input type="text" id="available_after_at" />
							</label><br> <label>공지 종료 일시:(비어있으면 계속)<input type="text" id="available_before_at" />
							</label><br>
						</div>
						<div style='float: right; width: 49%;'>
							<label>내용:<textarea rows='5' cols='40' id='notice_body'></textarea>
							</label><br>
						</div>
						<div style="clear: both; font-size: 1px;"></div>
					</div>
					<hr />
					<button id='notice_add'>공지 리스트에 추가</button>
					<button id='btn_refresh'>새로 고침</button>
				</div>
				<div id='notice_list' style="width: 100%">
					<div id="pager" style="width: 100%; height: 20px;"></div>
					<div id='grid' class='tab_grid' style="width: 100%; height: 400px;"></div>
				</div>
			</div>
			<div id="tabs_manage_banner">tabs_manage_banner</div>
		</div>
		<div id="tabs_statistics">
			<ul>
				<li><a href="#tabs_services">서비스 지표</a></li>
				<li><a href="#tabs_sales">매출 지표</a></li>
				<li><a href="#tabs_game">게임 지표</a></li>
				<li><a href="#tabs_battlefield">전장 지표</a></li>
				<li><a href="#tabs_occupy">점령전 지표</a></li>
			</ul>
			<div id="tabs_services">
				<div id='search_filter' style="width: 100%">
					<label>결과 표시: <select id='search_result_type'></select>
					</label> <label>검색 마켓<select id='search_market' /></select>
					</label> <label>검색 조건<select id='search_stat_service_type'></select>
					</label><br> 검색 기간: <label>From: <input type="text" id="stats_service_search_date_from" />
					</label> <label>To: <input type="text" id="stats_service_search_date_to" />
					</label>
					<button id='search_run'>검색</button>
					<button id='search_download'>다운로드</button>
				</div>
				<hr />
				<div id='result_list' style="width: 100%">
					<div id="pager" style="width: 100%; height: 20px;"></div>
					<div id='grid' class='tab_grid' style="width: 100%; height: 400px;"></div>
				</div>
			</div>
			<div id="tabs_sales">
				<div id='search_filter' style="width: 100%">
					<label>결과 표시: <select id='search_result_type'></select>
					</label> <label>검색 마켓<select id='search_market' /></select>
					</label> <label>검색 조건<select id='search_stat_sale_type'></select>
					</label><br> 검색 기간: <label>From: <input type="text" id="stats_sale_search_date_from" />
					</label> <label>To: <input type="text" id="stats_sale_search_date_to" />
					</label>
					<button id='search_run'>검색</button>
					<button id='search_download'>다운로드</button>
				</div>
				<hr />
				<div id='result_list' style="width: 100%">
					<div id="pager" style="width: 100%; height: 20px;"></div>
					<div id='grid' class='tab_grid' style="width: 100%; height: 400px;"></div>
				</div>
			</div>

			<div id="tabs_game">tabs_game</div>
			<div id="tabs_battlefield">tabs_battlefield</div>
			<div id="tabs_occupy">tabs_occupy</div>
		</div>
	</div>

</body>

</html>


