/*
Created: 2013-11-04
Modified: 2013-11-08
Model: eternal-war-operator
Database: MySQL 5.5
*/

-- Create tables section -------------------------------------------------

-- Table opuser

CREATE TABLE opuser
(
  opuser_id Int NOT NULL AUTO_INCREMENT,
  username Varchar(200) NOT NULL,
  password Varchar(200) NOT NULL,
  acl Varchar(200) NOT NULL,
  created_at Timestamp NOT NULL,
  login_at Timestamp NULL,
  status Int NOT NULL DEFAULT 0
  COMMENT '0: ok
1: suspended (inactive)
2: blocked',
 PRIMARY KEY (opuser_id)
)
;

INSERT opuser (username, password, acl, created_at) VALUES ('admin', 'admin', 'master', NOW());

-- Table legion

CREATE TABLE legion
(
  legion_id Int NOT NULL AUTO_INCREMENT,
  created_at Timestamp NULL
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
  action_at Timestamp NOT NULL,
  type Text NOT NULL,
  detail Text,
 PRIMARY KEY (action_id)
)
;

-- Table actions_general

CREATE TABLE actions_general
(
  action_id Bigint NOT NULL AUTO_INCREMENT,
  user_id Int NOT NULL,
  action_at Timestamp NOT NULL,
  type Text NOT NULL,
  detail Text,
 PRIMARY KEY (action_id)
)
;

-- Table actions_officer

CREATE TABLE actions_officer
(
  action_id Bigint NOT NULL AUTO_INCREMENT,
  user_id Int NOT NULL,
  action_at Timestamp NOT NULL,
  type Text NOT NULL,
  detail Text,
 PRIMARY KEY (action_id)
)
;

-- Table actions_troop

CREATE TABLE actions_troop
(
  action_id Bigint NOT NULL AUTO_INCREMENT,
  user_id Int NOT NULL,
  action_at Timestamp NOT NULL,
  type Text NOT NULL,
  detail Text,
 PRIMARY KEY (action_id)
)
;

-- Table actions_build

CREATE TABLE actions_build
(
  action_id Bigint NOT NULL AUTO_INCREMENT,
  user_id Int NOT NULL,
  action_at Timestamp NOT NULL,
  type Text NOT NULL,
  detail Text,
 PRIMARY KEY (action_id)
)
;

-- Table actions_item

CREATE TABLE actions_item
(
  action_id Bigint NOT NULL AUTO_INCREMENT,
  user_id Int,
  action_at Timestamp NOT NULL,
  type Text NOT NULL,
  detail Text,
  recv_market Tinyint,
  send_at Timestamp NULL,
  title Text,
  body Text,
  gifts Text,
 PRIMARY KEY (action_id)
)
;

-- Table actions_mail

CREATE TABLE actions_mail
(
  action_id Bigint NOT NULL AUTO_INCREMENT,
  user_id Int NOT NULL,
  action_at Timestamp NOT NULL,
  type Text NOT NULL,
  detail Text,
 PRIMARY KEY (action_id)
)
;

-- Table actions_combat

CREATE TABLE actions_combat
(
  action_id Bigint NOT NULL AUTO_INCREMENT,
  user_id Int NOT NULL,
  action_at Timestamp NOT NULL,
  type Text NOT NULL,
  detail Text,
 PRIMARY KEY (action_id)
)
;

-- Table actions_penalty

CREATE TABLE actions_penalty
(
  action_id Bigint NOT NULL AUTO_INCREMENT,
  user_id Int NOT NULL,
  old_status Tinyint NOT NULL,
  new_status Tinyint NOT NULL,
  reason Tinyint NOT NULL DEFAULT 0,
  status_from Timestamp NOT NULL,
  status_to Timestamp NULL,
  action_at Timestamp NOT NULL,
  detail Text,
 PRIMARY KEY (action_id)
)
;

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
  payment_sum Int NOT NULL DEFAULT 0
  COMMENT '누적 지불 총계',
  dev_type Tinyint NOT NULL DEFAULT 1
  COMMENT '장치 종류. 1: 안드로이드, 2: ios',
  dev_uuid Varchar(100)
  COMMENT '장치고유 아이디',
  timezone Varchar(200)
  COMMENT '장치의 timezone',
  recommender Varchar(200)
  COMMENT '추천인',
  created_at Timestamp NOT NULL
  COMMENT '생성 일시',
  login_at Timestamp NULL
  COMMENT '로그인 일시',
  extra Text
  COMMENT '부가 정보(JSON OBJECT)',
 PRIMARY KEY (user_id)
)
;

ALTER TABLE user ADD UNIQUE username (username)
;

-- Table item_pubrepo

CREATE TABLE item_pubrepo
(
  repo_id Int NOT NULL AUTO_INCREMENT,
  recv_market_type Tinyint NOT NULL DEFAULT 0
  COMMENT '0: all markets
1: ..

100: legions',
  send_at Timestamp NULL,
  title Text,
  body Text,
  gifts Text,
 PRIMARY KEY (repo_id)
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
  created_at Timestamp NOT NULL
  COMMENT '메시지 보낸 시간',
  body Text
  COMMENT '메시지 내용',
 PRIMARY KEY (chat_id)
)
;

-- Table item_prvrepo

CREATE TABLE item_prvrepo
(
  repo_id Int NOT NULL AUTO_INCREMENT,
  user_id Int,
  send_at Timestamp NULL,
  title Text,
  body Text,
  gifts Text,
 PRIMARY KEY (repo_id)
)
;

-- Table chat_legion

CREATE TABLE chat_legion
(
  chat_id Int NOT NULL AUTO_INCREMENT,
  general_id Int,
  legion_id Int NOT NULL
  COMMENT '군단 아이디',
  created_at Timestamp NOT NULL
  COMMENT '메시지 보낸 시간',
  body Text
  COMMENT '메시지 내용',
 PRIMARY KEY (chat_id)
)
;

-- Table maintenance_allowed

CREATE TABLE maintenance_allowed
(
  mt_id Int NOT NULL AUTO_INCREMENT,
  user_id Int,
  dev_uuid Text,
 PRIMARY KEY (mt_id)
)
;

-- Create relationships section ------------------------------------------------- 

ALTER TABLE actions_op ADD CONSTRAINT Relationship2 FOREIGN KEY (opuser_id) REFERENCES opuser (opuser_id) ON DELETE CASCADE ON UPDATE CASCADE
;

ALTER TABLE actions_general ADD CONSTRAINT Relationship4 FOREIGN KEY (user_id) REFERENCES user (user_id) ON DELETE CASCADE ON UPDATE CASCADE
;

ALTER TABLE actions_officer ADD CONSTRAINT Relationship5 FOREIGN KEY (user_id) REFERENCES user (user_id) ON DELETE CASCADE ON UPDATE CASCADE
;

ALTER TABLE actions_troop ADD CONSTRAINT Relationship6 FOREIGN KEY (user_id) REFERENCES user (user_id) ON DELETE CASCADE ON UPDATE CASCADE
;

ALTER TABLE actions_build ADD CONSTRAINT Relationship7 FOREIGN KEY (user_id) REFERENCES user (user_id) ON DELETE CASCADE ON UPDATE CASCADE
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

ALTER TABLE item_prvrepo ADD CONSTRAINT Relationship13 FOREIGN KEY (user_id) REFERENCES user (user_id) ON DELETE CASCADE ON UPDATE CASCADE
;

ALTER TABLE maintenance_allowed ADD CONSTRAINT Relationship14 FOREIGN KEY (user_id) REFERENCES user (user_id) ON DELETE CASCADE ON UPDATE CASCADE
;

ALTER TABLE chat_force ADD CONSTRAINT Relationship16 FOREIGN KEY (general_id) REFERENCES user (user_id) ON DELETE CASCADE ON UPDATE CASCADE
;

ALTER TABLE chat_legion ADD CONSTRAINT Relationship17 FOREIGN KEY (legion_id) REFERENCES legion (legion_id) ON DELETE CASCADE ON UPDATE CASCADE
;

ALTER TABLE chat_legion ADD CONSTRAINT Relationship18 FOREIGN KEY (general_id) REFERENCES user (user_id) ON DELETE CASCADE ON UPDATE CASCADE
;


