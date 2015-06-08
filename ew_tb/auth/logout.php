<?php
require_once '../connect.php';
require_once 'register.php';

if ( login_check(false) ) {

	auth::logout();
}

render_ok('not logged-in');
