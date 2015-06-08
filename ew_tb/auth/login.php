<?php
require_once '../connect.php';
require_once 'register.php';

if ( strstr($_SERVER["REQUEST_URI"], basename(__FILE__)) ) {

	$ops = explode(',', $op = queryparam_fetch('op', 'login'));

	if ( login_check(false) )
		render_ok('already logged in as ' . $_SESSION['user_id'], array('fcode' => 30102));

	if ( sizeof(array_intersect_key(['login'], $ops)) ) {
		for ( $retried = 1 ; $retried <= $TXN_RETRY_MAX ; $retried++ ) {
			try {
				$tb = new TxnBlock();

				if ( in_array("login", $ops) ) auth::login($tb);

				break;
			} catch ( Exception $e ) {
				$emsg = $e->getMessage();
				if ( $emsg === 'Deadlock found' )
					elog("got [$emsg] exception, trying again... with [$retried / $TXN_RETRY_MAX]");
			}
		}
	}

	render_error("invalid:op:$op");
}
