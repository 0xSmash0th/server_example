<?php

$dev = true;

$TAG = 'EW'; // project tag
$TXN_RETRY_MAX = 10;
$CACHE_TTL = 600;

//
$VERSION = "0.0.1";
$TIMESTAMP_MIN = '1970-01-01 00:00:00';
$TIMESTAMP_MAX = '2038-01-01 00:00:00'; // actually 2013-01-19

// forces (aka. country)
$ALLIES = 1;
$EMPIRE = 2;
$NEUTRAL = 3;
define('ALLIES', $ALLIES);
define('EMPIRE', $EMPIRE);
define('NEUTRAL', $NEUTRAL);

$FORCE_MAP = [$ALLIES=>'ALLIES', $EMPIRE=>'EMPIRE', $NEUTRAL=>'NEUTRAL'];
$MARKET_TYPE_MAP = [
0 => "any",
1 => "google",
2 => "tstore",
3 => "olleh",
4 => "ustore",
5 => "appstore",
"_"=>"_"
];
$MARKET_TYPE_RMAP = [];
foreach ($MARKET_TYPE_MAP as $k => $v) {
	if ( is_numeric($k) ) $MARKET_TYPE_RMAP[$v] = $k;
}
$COUPON_SEARCH_MAP = [
0 => '전체',
1 => '사용된 것',
2 => '사용되지 않은 것',
3 => '사용자 이름',
4 => '코드'
];

// officer
$OFFICER_UNHIRED = 1;
$OFFICER_HIRED = 2;
$OFFICER_TRAINING = 3; // DEPRECATED (TRAINING) at 1014
$OFFICER_HEALING = 4;
$OFFICER_DEAD = 5;

$TRAIN_NORMAL = 1;
$TRAIN_SPECIAL = 2;
$TRAIN_SPECIAL_STAR = 3;

$OFFICER_PROMOTE_REQ_QTY = 3; // 3-same-type_id, grade officers are required for promot
$OFFICER_RESET_COOLTIME = 3600-1; // in second
$OFFICER_UNHIRED_MIN = 3;
$OFFICER_HIRED_MAX = 20;
$OFFICER_HIRED_MIN = 5;
$OFFICER_HASTE_HEAL_COST_STAR_PER_HOUR = 1;
$OFFICER_HASTE_HEAL_COST_STAR = 1;
$OFFICER_HEAL_COST_TIME = 3600;
$OFFICER_HEAL_COST_GOLD_PER_LEVEL = 10;
$OFFICER_LIST_RESET_COST_STAR = 1;
$OFFICER_EXPAND_SLOT_COST_STAR = 1;
$OFFICER_ITEM_EQUIP_SLOT_MAX = 4;

$OFFICER_OFFENSE = 1;
$OFFICER_DEFENSE = 2;
$OFFICER_TACTICS = 3;
$OFFICER_RESISTS = 4;

$OFFICER_TRAIN_NORMAL_COST_GOLD = 3000;
$OFFICER_TRAIN_SPECIAL_COST_STAR = 20;
$OFFICER_TRAIN_TABLE_NORMAL = [
'SS' => ['rank'=>'SS', 'weight'=>1, 'mod'=>100],
'S' => ['rank'=>'S', 'weight'=>9, 'mod'=>80],
'A' => ['rank'=>'A', 'weight'=>30, 'mod'=>60],
'B' => ['rank'=>'B', 'weight'=>60, 'mod'=>40],
'C' => ['rank'=>'C', 'weight'=>900, 'mod'=>20],
'D' => ['rank'=>'D', 'weight'=>3000, 'mod'=>10],
'F' => ['rank'=>'F', 'weight'=>6000, 'mod'=>5],
];
$OFFICER_TRAIN_TABLE_SPECIAL = [
'SS' => ['rank'=>'SS', 'weight'=>1, 'mod'=>100],
'S' => ['rank'=>'S', 'weight'=>3, 'mod'=>80],
'A' => ['rank'=>'A', 'weight'=>6, 'mod'=>60],
'B' => ['rank'=>'B', 'weight'=>30, 'mod'=>40],
'C' => ['rank'=>'C', 'weight'=>60, 'mod'=>20],
];


// item
$ITEM_TYPE_MAJOR_COMBATS = 1;
$ITEM_TYPE_MAJOR_EQUIPS = 2;
$ITEM_TYPE_MAJOR_CONSUMES = 3;
$ITEM_TYPE_MAP = [$ITEM_TYPE_MAJOR_COMBATS=>'COMBAT', $ITEM_TYPE_MAJOR_EQUIPS=>'EQUIP', $ITEM_TYPE_MAJOR_CONSUMES=>'CONSUME'];

$ITEM_OWNERLESS = 1;
$ITEM_MAKING = 2;
$ITEM_GENERAL_OWNED = 3;
$ITEM_MAIL_OWNED = 4;
$ITEM_OFFICER_OWNED = 5;
$ITEM_MAKE_HASTE_COST_STAR_PER_HOUR = 1;
$ITEM_STORAGE_SLOT_MIN = 8;
$ITEM_STORAGE_SLOT_MAX = 40;
$ITEM_EXPAND_STORAGE_SLOT_COST_STAR = 10;

// construction
$BLD_POS_MIN = 1;
$BLD_POS_MAX = 17;
$BLD_COUNT_MAX = 17;

$BLD_COOLTIME_LIMIT = 7200; // in second, 2 hours
// if ( dev ) $BLD_COOLTIME_LIMIT = 24*3600; // in second
$BLD_RESET_COOLTIME_COST_STAR_PER_HOUR = 1;

$COLLECTABLE_TAX_COST_ACTIVITY = 1;
$COLLECTABLE_TAX_COUNT_MAX = 4;
$COLLECTABLE_TAX_COOLTIME = 30*60; // in second, 30 min
// if ( dev ) $COLLECTABLE_TAX_COOLTIME = 10; // do not use small value

$BLD_EMPTY = 1;
$BLD_BUILDING = 2;
$BLD_UPGRADING = 3;
$BLD_COMPLETED = 4;

$BLD_DEFAULT_HQ_POSITION = 1;
$BLD_DEFAULT_HQ_ID_ALLIES = 1000;
$BLD_DEFAULT_HQ_ID_EMPIRE = 1030;
$BLD_DEPOT_ID = 1062;
$BLD_CAMP_ID = 1060;
$BLD_PUB_ID = 1065; // for officer unhired max
$BLD_HOSPITAL_ID = 1064; // for officer heal
$BLD_LABORATORY_ID = 1066; // for item
$BLD_TRAINING_ID_ALLIES = 1007; // for quest
$BLD_TRAINING_ID_EMPIRE = 1037; // for quest
$BLD_MACHINE_FACTORY_ID_ALLIES = 1008; // for quest
$BLD_MACHINE_FACTORY_ID_EMPIRE = 1038; // for quest

// troop
$TROOP_TRAINING = 1; // aka. UNTRAINED
$TROOP_TRAINED = 2; // aka. UNBANDED
$TROOP_BANDED = 3;
$TROOP_TRAIN_QTY_MAX = 600;
$TROOP_TRAIN_HASTE_COST_STAR_PER_HOUR = 1;
$TROOP_POPULATION_LIMIT = 999; // per unit type

// quests
$QUEST_DAILY_COUNT = 3;
$QUEST_DAILY_REFRESH_HOUR = 18; // 0~23
$QUEST_DAILY_REFRESH_MINUTE = 0; // 0~59

// general
$SKILLS_MULTIPLIER = 2;
$ACTIVITY_GAIN_COOLTIME = 10*60; //10 min
$BADGE_EQUIP_COOLTIME = 24*3600; // 24 hours
// if ( dev ) $BADGE_EQUIP_COOLTIME = 60;
$GENERAL_RESET_SKILLS_COST_STAR = 1;
$BADGE_ACQUIRES_BY_DEFAULT = 0; // 1: get all, 2: get some half, otherwise, don't get by default

$GENERAL_RESOURCE_MAX = 999999999; // 10억

$GENERAL_INITIAL_GOLD = 500;
$GENERAL_INITIAL_HONOR = 100;
$GENERAL_INITIAL_STAR = 100;
$GENERAL_INITIAL_ACTIVITY = 10;
$GENERAL_INITIAL_TAX_COLLECTABLE_COUNT = 1;

// randoms
$RANDOM_INTEGER_NUMBERS = 100;
$RANDOM_FLOAT_NUMBERS = 100;

// battlefield
$BATTLEFIELD_REBUILD_PERIOD_BY_DAY = 3;
$BATTLEFIELD_HOTSPOT_TIME_QUANTUM = 5; // by minutes
$BATTLEFIELD_HOTSPOT_QTY = 4; // maximum 4 spots
$BATTLEFIELD_RANKING_QTY = 100;

$TILE_SAFEZONE_ALLIES = ['I01', 'I03', 'J01', 'J04', 'K03', 'K04', 'K05', 'H01', 'H03', 'I05', 'K06', 'J02'];
$TILE_SAFEZONE_EMPIRE = ['A10', 'A11', 'A12', 'B10', 'B13', 'C12', 'C14', 'A09', 'C10', 'D11', 'D13', 'B12'];
$TILE_INIT_DISCONNECTED_ALLIES = ['B03', 'C03', 'D02', 'E02'];
$TILE_INIT_DISCONNECTED_EMPIRE = ['G13', 'H12', 'I12', 'J11'];

// combat
// for combat.status
$COMBAT_RUNNING = 1; // aka. created
$COMBAT_VERIFYING = 2; // aka. result submitted
$COMBAT_COMPLETED = 3; // aka. done
$COMBAT_REWARD_EXP_OFFICER_RATIO_TO_GENERAL = 0.5;
$COMBAT_REWARD_RATIO_WHEN_LOST = 1.0/3.0; // 33.3...%
$COMBAT_TOP_RANK = 'S';

// chat
$CHAT_FETCH_LIMIT = 50;
$CHAT_SEND_COST_GOLD = 10;
$CHAT_SEND_COOLTIME = 5; // 5 seconds
$CHAT_SEND_LIMIT = 80*3; // consider UTF-8

$NOTICE_TYPE_SYSTEM = 1;
$NOTICE_TYPE_EVENT = 2;

// mail
$MAIL_ARCHIVE_MAX = 100;
$MAIL_TITLE_LIMIT = 18*3; // consider UTF-8
$MAIL_BODY_LIMIT = 400*3; // consider UTF-8
$MAIL_RECV_TYPE_USER = 1;
$MAIL_RECV_TYPE_LEGION = 2;
$MAIL_RECV_TYPE_PUBLIC = 3;

// shop
$SHOP_EQUIPS = 1;
$SHOP_ITEMS = 2;
$SHOP_GOLDS = 3;
$SHOP_HONORS = 4;
$SHOP_STARS = 5;

// about operation
$USER_STATUS_ALL = 0;
$USER_STATUS_ACTIVE = 1;
$USER_STATUS_SUSPEND = 2;
$USER_STATUS_BAN = 3;

$USER_LINE_ALL = 0;
$USER_LINE_OFF = 1;
$USER_LINE_ON = 2;
$USER_LINE_THRESHOLD_TO_OFFLINE = 10*60; // 10 mins

$USER_ACCESS_ALL = 0;
$USER_ACCESS_JOIN = 1;
$USER_ACCESS_LOGIN = 2;

// other misc
// note that, SYSTEM_*, MYSQL_*, REDIS_* variables will be ignored by get_constants
$SYSTEM_OPLOG_BULK_PROCESS_QTY = 30;
$SYSTEM_SHOW_CACHE_METRICS = 0;
$SYSTEM_EXECUTION_TIME_LIMIT = 30;
$SYSTEM_EVENT_SOURCE_IPS = ['127.0.0.1', '::1']; // localhost(ipv4, ipv6)
$SYSTEM_EVENT_TARGET_URLBASE = 'http://localhost/ew_tb_$TAG/';
$SYSTEM_FETCH_ALL_MAX = 1000;
$SYSTEM_OPERATOR_ALLOWED_IPS = ['127.0.0.1', '::1', '222.117.240.10']; // localhost(ipv4, ipv6), ee

$STATS_SERVICE_JOINED = 1;
$STATS_SERVICE_CONCURRENT = 2;
$STATS_SERVICE_ACTIVE = 3;
$STATS_SERVICE_PARTED = 4;
$STATS_SERVICE_RMAP = ['active_users'=>$STATS_SERVICE_ACTIVE, 'concurrent_users'=>$STATS_SERVICE_CONCURRENT, 'joined_users'=>$STATS_SERVICE_JOINED, 'parted_users'=>$STATS_SERVICE_PARTED];
$STATS_SALE_REVENUE = 1;
$STATS_SALE_COUNT = 2;
$STATS_SALE_BUYERS = 3;
$STATS_SALE_ARPU = 4;
$STATS_SALE_RMAP = ['revenue'=>$STATS_SALE_REVENUE, 'count'=>$STATS_SALE_COUNT, 'buyers'=>$STATS_SALE_BUYERS, 'arpu'=>$STATS_SALE_ARPU];



$MYSQL_CLUSTERED = false;
// prefixing 'p:' to 'hostname' will estsablish persistent connections
$MYSQL_USER = 'ew_tb';
$MYSQL_PASS = 'dlxjsjfdnj';
$MYSQL_DB = 'ew_tb';
$MYSQL_HOSTS = ['p:ew_tb_db_1p'];
$MYSQL_PORT = 23306;

$MYSQL_USER_OP = 'ew_op';
$MYSQL_DB_OP = 'ew_op';

$REDIS_HOSTS = ['ew_tb_was_3p'];
$REDIS_PORTS = [6379];

if ( @file_exists(__DIR__ . '/my_constants.php') ) {
	require_once __DIR__ . '/my_constants.php';
}

$SYSTEM_OPERATOR_ALLOWED_IPS = array_merge($SYSTEM_OPERATOR_ALLOWED_IPS, $SYSTEM_EVENT_SOURCE_IPS);
define('dev', $dev);
define('TXN_RETRY_MAX', $TXN_RETRY_MAX);
define('CACHE_TTL', $CACHE_TTL);
