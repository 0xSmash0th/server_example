/*
Created: 2013-11-04
Modified: 2013-11-08
Model: eternal-war-operator
Database: MySQL 5.5
*/

-- Create tables section -------------------------------------------------


-- Table user

CREATE TABLE user
(
  user_id Int NOT NULL AUTO_INCREMENT,
  username Varchar(200) NOT NULL,
  password Varchar(200) NOT NULL,
  email Varchar(200),
  market_type Tinyint NOT NULL DEFAULT 0
  COMMENT '마켓 종류
0: unknown
1: 구글
2: TStore
3: olleh
4: ustore
5: appstore

',
  user_status Tinyint NOT NULL DEFAULT 0
  COMMENT '계정상태
0: 정상
1: 일시정지
2: 영구정지',
  payment_sum FLOAT NOT NULL DEFAULT 0
  COMMENT '누적 지불 총계',
  dev_type Tinyint NOT NULL DEFAULT 1
  COMMENT '장치 종류. 1: 안드로이드, 2: ios',
  dev_uuid Varchar(100)
  COMMENT '장치고유 아이디',
  timezone Varchar(200)
  COMMENT '장치의 timezone',
  recommender Varchar(200)
  COMMENT '추천인',
  created_at TIMESTAMP NULL DEFAULT NULL
  COMMENT '생성 일시',
  login_at TIMESTAMP NULL DEFAULT NULL
  COMMENT '로그인 일시',
  extra Text
  COMMENT '부가 정보(JSON OBJECT)',
 PRIMARY KEY (user_id)
)
;

ALTER TABLE user ADD UNIQUE username (username);
CREATE INDEX idx__market_type__status__payment_sum__created_at__login_at ON user (market_type, user_status, payment_sum, created_at, login_at);

-- Table opuser

CREATE TABLE opuser
(
  opuser_id Int NOT NULL AUTO_INCREMENT,
  username Varchar(200) NOT NULL,
  password Varchar(200) NOT NULL,
  acl Varchar(200) NOT NULL,
  created_at TIMESTAMP NULL DEFAULT NULL,
  login_at TIMESTAMP NULL DEFAULT NULL,
  status Int NOT NULL DEFAULT 0
  COMMENT '0: ok
1: suspended (inactive)
2: blocked',
 PRIMARY KEY (opuser_id)
)
;

INSERT opuser (username, password, acl, created_at) VALUES ('admin', 'admin', 'master', NOW());
INSERT opuser (username, password, acl, created_at) VALUES ('eventbot', 'eventbot', 'master', NOW());

-- Table legion

CREATE TABLE legion
(
  legion_id Int NOT NULL AUTO_INCREMENT,
  created_at TIMESTAMP NULL DEFAULT NULL
  COMMENT '군단 생성 일시',
  name Char(20)
  COMMENT '군단 이름',
  score Int
  COMMENT '군단 점수',
  asset Char(20)
  COMMENT '군단 자산',
  rank Int
  COMMENT '군단 랭킹',
  member_cur Int
  COMMENT '현재 군단원 수',
  member_max Int
  COMMENT '최대 군단원 수',
  combat_win Int
  COMMENT '승리 횟수',
  combat_lose Int
  COMMENT '패배 횟수',
  combat_draw Int
  COMMENT '무승부 횟수',
 PRIMARY KEY (legion_id)
)
;

-- Table actions_op

CREATE TABLE actions_op
(
  action_id Bigint NOT NULL AUTO_INCREMENT,
  opuser_id Int NOT NULL,
  user_id Int
  COMMENT '대상 user_id',
  action_at TIMESTAMP NULL DEFAULT NULL,
  type VARCHAR(32) NOT NULL,
  detail Text,
 PRIMARY KEY (action_id)
)
;
CREATE INDEX idx_op__type__action_at ON actions_op (type, action_at);

-- Table actions_user
CREATE TABLE actions_user
(
  action_id Bigint NOT NULL AUTO_INCREMENT,
  user_id Int NOT NULL,
  action_at TIMESTAMP NULL DEFAULT NULL,
  type VARCHAR(32) NOT NULL,
  detail Text,
  FOREIGN KEY (user_id) REFERENCES user (user_id) ON DELETE CASCADE ON UPDATE CASCADE,
  PRIMARY KEY (action_id)
)
;
CREATE INDEX idx_user__type__action_at ON actions_user (type, action_at);

-- Table actions_general

CREATE TABLE actions_general
(
  action_id Bigint NOT NULL AUTO_INCREMENT,
  user_id Int NOT NULL,
  action_at TIMESTAMP NULL DEFAULT NULL,
  type VARCHAR(32) NOT NULL,
  detail Text,
 PRIMARY KEY (action_id)
)
;
CREATE INDEX idx_general__type__action_at ON actions_general (type, action_at);

-- Table actions_officer

CREATE TABLE actions_officer
(
  action_id Bigint NOT NULL AUTO_INCREMENT,
  user_id Int NOT NULL,
  action_at TIMESTAMP NULL DEFAULT NULL,
  type VARCHAR(32) NOT NULL,
  detail Text,
 PRIMARY KEY (action_id)
)
;
CREATE INDEX idx_officer__type__action_at ON actions_officer (type, action_at);

-- Table actions_troop

CREATE TABLE actions_troop
(
  action_id Bigint NOT NULL AUTO_INCREMENT,
  user_id Int NOT NULL,
  action_at TIMESTAMP NULL DEFAULT NULL,
  type VARCHAR(32) NOT NULL,
  detail Text,
 PRIMARY KEY (action_id)
)
;
CREATE INDEX idx_troop__type__action_at ON actions_troop (type, action_at);

-- Table actions_construction

CREATE TABLE actions_construction
(
  action_id Bigint NOT NULL AUTO_INCREMENT,
  user_id Int NOT NULL,
  action_at TIMESTAMP NULL DEFAULT NULL,
  type VARCHAR(32) NOT NULL,
  detail Text,
 PRIMARY KEY (action_id)
)
;
CREATE INDEX idx_construction__type__action_at ON actions_construction (type, action_at);

-- Table actions_item

CREATE TABLE actions_item
(
  action_id Bigint NOT NULL AUTO_INCREMENT,
  user_id Int,
  action_at TIMESTAMP NULL DEFAULT NULL,
  type VARCHAR(32) NOT NULL,
  detail Text,
 PRIMARY KEY (action_id)
)
;
CREATE INDEX idx_item__type__action_at ON actions_item (type, action_at);

-- Table actions_mail

CREATE TABLE actions_mail
(
  action_id Bigint NOT NULL AUTO_INCREMENT,
  user_id Int NOT NULL,
  action_at TIMESTAMP NULL DEFAULT NULL,
  type VARCHAR(32) NOT NULL,
  detail Text,
 PRIMARY KEY (action_id)
)
;
CREATE INDEX idx_mail__type__action_at ON actions_mail (type, action_at);

-- Table actions_combat

CREATE TABLE actions_combat
(
  action_id Bigint NOT NULL AUTO_INCREMENT,
  user_id Int NOT NULL,
  action_at TIMESTAMP NULL DEFAULT NULL,
  type VARCHAR(32) NOT NULL,
  detail Text,
 PRIMARY KEY (action_id)
)
;
CREATE INDEX idx_combat__type__action_at ON actions_combat (type, action_at);

-- Table actions_tile
CREATE TABLE actions_tile
(
  action_id Bigint NOT NULL AUTO_INCREMENT,
  user_id Int NOT NULL,
  action_at TIMESTAMP NULL DEFAULT NULL,
  type VARCHAR(32) NOT NULL,
  detail Text,
  FOREIGN KEY (user_id) REFERENCES user (user_id) ON DELETE CASCADE ON UPDATE CASCADE,
  PRIMARY KEY (action_id)
)
;
CREATE INDEX idx_tile__type__action_at ON actions_tile (type, action_at);

-- Table actions_shop
CREATE TABLE actions_shop
(
  action_id Bigint NOT NULL AUTO_INCREMENT,
  user_id Int NOT NULL,
  action_at TIMESTAMP NULL DEFAULT NULL,
  type VARCHAR(32) NOT NULL,
  detail Text,
  price FLOAT NULL,
  FOREIGN KEY (user_id) REFERENCES user (user_id) ON DELETE CASCADE ON UPDATE CASCADE,
  PRIMARY KEY (action_id)
)
;
CREATE INDEX idx_shop__type__action_at ON actions_shop (type, action_at);

-- Table actions_quest
CREATE TABLE actions_quest
(
  action_id Bigint NOT NULL AUTO_INCREMENT,
  user_id Int NOT NULL,
  action_at TIMESTAMP NULL DEFAULT NULL,
  type VARCHAR(32) NOT NULL,
  detail Text,
  FOREIGN KEY (user_id) REFERENCES user (user_id) ON DELETE CASCADE ON UPDATE CASCADE,
  PRIMARY KEY (action_id)
)
;
CREATE INDEX idx_quest__type__action_at ON actions_quest (type, action_at);

-- Table actions_event
CREATE TABLE actions_event
(
  action_id Bigint NOT NULL AUTO_INCREMENT,
  user_id Int NOT NULL,
  action_at TIMESTAMP NULL DEFAULT NULL,
  type VARCHAR(32) NOT NULL,
  detail Text,
  FOREIGN KEY (user_id) REFERENCES user (user_id) ON DELETE CASCADE ON UPDATE CASCADE,
  PRIMARY KEY (action_id)
)
;
CREATE INDEX idx_event__type__action_at ON actions_event (type, action_at);

-- Table actions_penalty

CREATE TABLE actions_penalty
(
  action_id Bigint NOT NULL AUTO_INCREMENT,
  user_id Int NOT NULL,
  old_status Tinyint NOT NULL,
  new_status Tinyint NOT NULL,
  reason Tinyint NOT NULL DEFAULT 0,
  status_from TIMESTAMP NULL DEFAULT NULL,
  status_to TIMESTAMP NULL DEFAULT NULL,
  action_at TIMESTAMP NULL DEFAULT NULL,
  detail Text,
 PRIMARY KEY (action_id)
)
;

-- Table chat_force

CREATE TABLE chat_force
(
  chat_id Int NOT NULL AUTO_INCREMENT,
  general_id Int,
  send_force Tinyint NOT NULL DEFAULT 3
  COMMENT 'sender 진영 (1: 연합, 2: 제국, 3: 중립)',
  recv_force Tinyint NOT NULL DEFAULT 3
  COMMENT 'receiver 진영 (1: 연합, 2: 제국, 3: 중립)',
  created_at TIMESTAMP NULL DEFAULT NULL
  COMMENT '메시지 보낸 시간',
  body Text
  COMMENT '메시지 내용',
 PRIMARY KEY (chat_id)
)
;
CREATE INDEX idx__recv_force__created_at ON chat_force (recv_force, created_at);

-- Table chat_legion

CREATE TABLE chat_legion
(
  chat_id Int NOT NULL AUTO_INCREMENT,
  general_id Int,
  legion_id Int NOT NULL
  COMMENT '군단 아이디',
  created_at TIMESTAMP NULL DEFAULT NULL
  COMMENT '메시지 보낸 시간',
  body Text
  COMMENT '메시지 내용',
 PRIMARY KEY (chat_id)
)
;
CREATE INDEX idx__legion_id__created_at ON chat_legion (legion_id, created_at);

-- Create relationships section ------------------------------------------------- 

ALTER TABLE actions_op ADD CONSTRAINT Relationship2 FOREIGN KEY (opuser_id) REFERENCES opuser (opuser_id) ON DELETE CASCADE ON UPDATE CASCADE
;

ALTER TABLE actions_general ADD CONSTRAINT Relationship4 FOREIGN KEY (user_id) REFERENCES user (user_id) ON DELETE CASCADE ON UPDATE CASCADE
;

ALTER TABLE actions_officer ADD CONSTRAINT Relationship5 FOREIGN KEY (user_id) REFERENCES user (user_id) ON DELETE CASCADE ON UPDATE CASCADE
;

ALTER TABLE actions_troop ADD CONSTRAINT Relationship6 FOREIGN KEY (user_id) REFERENCES user (user_id) ON DELETE CASCADE ON UPDATE CASCADE
;

ALTER TABLE actions_construction ADD CONSTRAINT Relationship7 FOREIGN KEY (user_id) REFERENCES user (user_id) ON DELETE CASCADE ON UPDATE CASCADE
;

ALTER TABLE actions_item ADD CONSTRAINT Relationship8 FOREIGN KEY (user_id) REFERENCES user (user_id) ON DELETE CASCADE ON UPDATE CASCADE
;

ALTER TABLE actions_mail ADD CONSTRAINT Relationship9 FOREIGN KEY (user_id) REFERENCES user (user_id) ON DELETE CASCADE ON UPDATE CASCADE
;

ALTER TABLE actions_combat ADD CONSTRAINT Relationship10 FOREIGN KEY (user_id) REFERENCES user (user_id) ON DELETE CASCADE ON UPDATE CASCADE
;

ALTER TABLE actions_penalty ADD CONSTRAINT Relationship11 FOREIGN KEY (user_id) REFERENCES user (user_id) ON DELETE CASCADE ON UPDATE CASCADE
;

ALTER TABLE actions_op ADD CONSTRAINT Relationship12 FOREIGN KEY (user_id) REFERENCES user (user_id) ON DELETE CASCADE ON UPDATE CASCADE
;

ALTER TABLE chat_force ADD CONSTRAINT Relationship16 FOREIGN KEY (general_id) REFERENCES user (user_id) ON DELETE CASCADE ON UPDATE CASCADE
;

ALTER TABLE chat_legion ADD CONSTRAINT Relationship17 FOREIGN KEY (legion_id) REFERENCES legion (legion_id) ON DELETE CASCADE ON UPDATE CASCADE
;

ALTER TABLE chat_legion ADD CONSTRAINT Relationship18 FOREIGN KEY (general_id) REFERENCES user (user_id) ON DELETE CASCADE ON UPDATE CASCADE
;

-- Table stats_service

CREATE TABLE stats_service
(
	stat_id INT NOT NULL AUTO_INCREMENT,
	stat_type TINYINT NOT NULL COMMENT '1 : 신규 가입자, 2 : 동시 접속자, 3 : 액티브 사용자, 4 : 탈퇴 사용자',
	market_type TINYINT NOT NULL DEFAULT 0,
	stat_at TIMESTAMP NULL DEFAULT NULL,
	value INT DEFAULT 0,
	PRIMARY KEY (stat_id)
)
;
CREATE UNIQUE INDEX idx__stat_type__market_type__stat_at ON stats_service (stat_type, market_type, stat_at);

-- Table probe_concurrent_users
CREATE TABLE probe_concurrent_users
(
	probe_id INT NOT NULL AUTO_INCREMENT,
	probe_at TIMESTAMP NULL DEFAULT NULL,
	market_type TINYINT NOT NULL DEFAULT 0,
	qty INT NOT NULL DEFAULT 0,
	PRIMARY KEY (probe_id)
);
CREATE INDEX idx__market_type__probe_at ON probe_concurrent_users (market_type, probe_at);


-- Table stats_sale

CREATE TABLE stats_sale
(
	stat_id INT NOT NULL AUTO_INCREMENT,
	stat_type TINYINT NOT NULL COMMENT '1 : 매출, 2 : 건수, 3 : 구입 유저 수, 4 : ARPU',
	market_type TINYINT NOT NULL DEFAULT 0,
	stat_at TIMESTAMP NULL DEFAULT NULL,
	value INT DEFAULT 0,
	PRIMARY KEY (stat_id)
)
;
CREATE UNIQUE INDEX idx__stat_type__market_type__stat_at ON stats_sale (stat_type, market_type, stat_at);
