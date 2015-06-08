/*
created: 2013-06-21
modified: 2013-11-06
model: eternal-war
database: mysql 5.5
*/



-- create tables section -------------------------------------------------

-- table legion

create table legion
(
  legion_id int not null auto_increment,
  created_at timestamp null
  comment '군단 생성 일시',
  name char(20)
  comment '군단 이름',
  score int
  comment '군단 점수',
  asset char(20)
  comment '군단 자산',
  rank int
  comment '군단 랭킹',
  member_cur int
  comment '현재 군단원 수',
  member_max int
  comment '최대 군단원 수',
  combat_win int
  comment '승리 횟수',
  combat_lose int
  comment '패배 횟수',
  combat_draw int
  comment '무승부 횟수',
 primary key (legion_id)
)
;

-- table general

create table general
(
  general_id int not null auto_increment,
  legion_joined_id int
  comment '소속 군단',
  legion_joining_id int
  comment '가입 요청한 군단id',
  user_id int not null,
  name varchar(200) not null
  comment '장군 이름',
  picture varchar(200)
  comment '장군 이미지',
  rank varchar(20)
  comment '계급',
  level int not null default 1
  comment '레벨',
  country int not null
  comment '소속 국가
(1: 연합, 2: 제국)',
  total_score int
  comment '종합 점수',
  total_rank int
  comment '종합 랭킹',
  power int
  comment '전투력',
  honor_coin int
  comment '명예 코인',
  honor_score int
  comment '명예 점수',
  star int not null default 0
  comment '스타(캐쉬)',
  gold bigint not null default 0
  comment '돈(자원)',
  gold_max bigint not null default 28800
  comment '유효 최대 골드량',
  honor bigint not null default 0
  comment '명예(자원)',
  honor_max bigint not null default 28800
  comment '유효 최대 명예량',
  activity_cur int not null default 0
  comment '현재 행동력',
  activity_max int not null default 12
  comment '유효 최대 행동력',
  activity_willbe_refreshed_at timestamp null
  comment 'activity_cur가 다음 번에 리프레시 될 시간',
  exp_cur int not null default 0
  comment '현재 경험치
',
  exp_max int not null default 2000
  comment '최대 경험치, 넘어서면 레벨업',
  pop_cur int not null default 0
  comment '현재 병력 수',
  pop_max int not null default 1200
  comment '유효 최대 인구(병력)수',
  legion_contrib int
  comment '군단 공헌도',
  legion_joined_at timestamp null
  comment '군단 가입 일시',
  legion_parted_at timestamp null
  comment '군단 탈퇴 일시',
  officer_hired_max int not null default 0
  comment '장교 고용 가능 슬롯 최대 수',
  officer_unhired_max int not null default 0
  comment '장교 비고용-목록의 최대 수 (주점 건물에 의해서 결정)',
  officer_hired_level_max int not null default 0
  comment '고용된 장교 중의 최대 레벨 (quest때문)',
  officer_list_willbe_reset_at timestamp null
  comment '장교 목록 리셋될 시간 (일정 시간이 지나면 자동 리셋해야 함)',
  tax_collectable_count int not null default 0
  comment '세금 징수 가능 회수',
  tax_timer_willbe_refreshed_at timestamp null
  comment '세금 징수가 업데이트 될 일시',
  item_storage_slot_cur int not null default 0
  comment '현재 소유 아이템 개수',
  item_storage_slot_cap int not null default 10
  comment '아이템 저장고(창고)의 최대치. 스타를 이용해서 증가 시킨다',
  bld_cool_end_at timestamp null
  comment '건설 쿨타임 종료 예상 시간',
  extra text
  comment '부가 정보(json object)',
  leading_officer_id int
  comment '리더 장교id',
  skills text
  comment '장군 스킬. json dict
{points_offense:10,
points_defense:15,
points_support:5,
id:{level:n}, ...}',
  badge_list text
  comment '소유한 훈장 목록(level포함), json dict of dicts. {badge_id:{level:int}, ...}',
  badge_equipped_id int
  comment '장착된 훈장 아이디',
  badge_willbe_refreshed_at timestamp null
  comment '다음번에 훈장 장착이 가능해질 시간 (cooltime 때문)',
  running_combat_id int
  comment '진행 중인 전투의 아이디',
  building_list text
  comment '건물 리스트. json. dict.
{
  "hq_cid": cid,
  "hq_bid": bid,
  "hq_level": level,
  "shq_bid": bid,
  "non_hq": {
    bid: {
      cid: level, ...
    },
    ...
  }
}
',
  effects text
  comment '정적 효과 테이블(bld, skills, badge). json. dict
{
  effect_id1 : value1,
  effect_id2 : value2,
  ...
}',
  quest_list text
  comment '진행중/완료한 퀘스트 리스트
{
  accepted: {qid1:qid1, qid2:qid2, ...}
  completed: {qid3:qid3, qid4:qid4, ...}
  rewarded: {qid5:qid5, qid6:qid6, ...}
}
',
  chat_ban_ids text
  comment '채팅 밴 general_id 리스트. json list of int',
  mail_unchecked smallint not null default 0
  comment '읽지 않은 mail 개수',
  pvp_combat_count int not null default 0
  comment '점령전 플레이 카운트',
  pve_combat_count int not null default 0
  comment '섬멸전 플레이 카운트',
  pvl_combat_count int not null default 0
  comment '군단전 플레이 카운트',
  pve_combat_win int not null default 0
  comment '섬멸전 승리 회수',
  pvp_combat_win int not null default 0
  comment '점령전 승리 회수',
  pve_combat_top_rank_count int not null default 0
  comment '섬멸전 최고 랭크 회수',
  pvp_combat_top_rank_count int not null default 0
  comment '점령전 최고 랭크 회수',
 primary key (general_id)
)
;

create index idx_activity_willbe_refreshed_at on general (activity_willbe_refreshed_at)
;

create index idx_officer_list_willbe_reset_at on general (officer_list_willbe_reset_at)
;

create index idx_tax_timer_willbe_refreshed_at on general (tax_timer_willbe_refreshed_at)
;

create index idx_bld_cool_end_at on general (bld_cool_end_at)
;

create index idx_badge_willbe_refreshed_at on general (badge_willbe_refreshed_at)
;

-- table officer

create table officer
(
  officer_id int not null auto_increment,
  general_id int,
  type_id int not null
  comment '장교 종류 (장교db의 id)',
  grade int not null
  comment '등급',
  status int not null default 1
  comment '장교 상태
1: un-hired
2: hired
3: training
4: healing
5: dead',
  level int not null default 1
  comment '현재 레벨',
  speciality int not null
  comment '특기',
  exp_cur int not null default 0
  comment '현재 경험치',
  exp_max int not null default 0
  comment '최대(다음 레벨업을 위한) 경험치',
  hired_at timestamp null
  comment '고용된 시간',
  status_changing_at timestamp null
  comment '상태 변경(훈련, 치료)이 시작된 일시',
  status_changed_at timestamp null
  comment '상태 변경(훈련, 치료)이 완료될 일시',
  status_change_context text
  comment '상태 변경의 내부 데이터 (json object)',
  equipments text
  comment '착용 장비 리스트 (json object)
{ "slot_num" : {"slot_num": item_id, ...}, ...}',
  offense int not null default 0
  comment '공격지휘',
  defense int not null default 0
  comment '방어지휘',
  tactics int not null default 0
  comment '전술지휘',
  resists int not null default 0
  comment '전술방어',
  command_cur int not null default 0
  comment '소모한 지휘력',
  command_max int not null default 300
  comment '최대 지휘력',
  offense_rank char(2)
  comment '공격지휘 강화 등급',
  defense_rank char(2)
  comment '방어지휘 강화 등급',
  tactics_rank char(2)
  comment '전술지휘 강화 등급',
  resists_rank char(2)
  comment '전술방어 강화 등급',
 primary key (officer_id)
)
;

create index idx_status_changed_at on officer (status_changed_at)
;

create index idx_gid_status on officer (general_id,status)
;

-- table mail

create table mail
(
  mail_id int not null auto_increment,
  general_id int
  comment '수신 장군 id',
  sender_id int
  comment '송신자. null 이면 시스템 혹은 운영자',
  archived bool not null default false
  comment '보관 여부',
  checked bool not null default false
  comment '읽은 메시지',
  type tinyint not null default 1
  comment '종류 (삭제됨1, 시스템2, 일반3)',
  created_at timestamp not null
  comment '메시지 보낸 시간',
  expire_at timestamp null
  comment '메시지가 삭제될 시간, null 이면 영구 보관',
  title text not null
  comment '제목',
  body text not null
  comment '메시지',
  gifts text
  comment '첨부된 아이템 및 자원. json dict.
{
  items: [item_id1, item_id2, item_id3, ...],
  gold: 100,
  honor: 10
}',
 primary key (mail_id)
)
;

create index idx_gid_ar_type_expire on mail (general_id,archived,type,expire_at)
;

-- table construction

create table construction
(
  construction_id int not null auto_increment,
  general_id int not null,
  building_id int not null
  comment '건물 아이디(종류)',
  position int not null
  comment '건물 위치',
  cur_level int not null default 1
  comment '현재 레벨',
  status int not null default 1
  comment '상태
0~1: 비어 있음
2: (최초) 건설중 
3: 확장 중
4: 완료 상태',
  created_at timestamp null
  comment '최초 건설(생성) 시간',
  extra text
  comment '부가 정보(json object 형식)',
 primary key (construction_id)
)
;

create index idx_gid_status on construction (general_id,status)
;

create index idx_gid_building_id on construction (general_id,building_id)
;

-- table combat

create table combat
(
  combat_id int not null auto_increment,
  general_id int,
  status int not null
  comment '전투 상태
1: 생성 됨 (전투 진행 중)
2: 결과 전달 받음 (검증 진행 중)
3: 전투 완료',
  seed int not null
  comment '랜덤 시드',
  brief text not null
  comment '전투 시작 내역 (json: dict)',
  result text
  comment '전투 수행 진행/결과 내역 (json: dict)',
  summary text
  comment '전투 결과에 대한 요약 (json dict)',
  created_at timestamp not null
  comment '전투 생성 시간',
  verified_at timestamp null
  comment '전투 로그 검증 시간',
  completed_at timestamp null
  comment '전투 종료 시간',
 primary key (combat_id)
)
;

-- table battlefield

create table battlefield
(
  battlefield_id int not null,
  name varchar(200) not null
  comment '전장 이름',
  picture varchar(200) not null,
  effects_allies text
  comment '연합군 전체 효과 
json dict: {effect_id: value, ...}',
  effects_empire text
  comment '제국군 전체 효과
json dict: {effect_id: value, ...}',
  effects_legion text
  comment '군단 전체 효과. json dict
{ legion_id: {effect_id:value, ...}, ...}',
  hotspots text
  comment '활성화 지역. json list or tile_name',
  willbe_rebuilt_at timestamp null
  comment '전장을 다시 구성할 시간',
  hotspots_willbe_rebuilt_at timestamp null
  comment '활성화 지역을 다시 구성할 시간'
)
;

alter table battlefield add primary key (battlefield_id)
;

-- table tile

create table tile
(
  tile_id int not null auto_increment,
  battlefield_id int not null,
  legion_id int,
  position char(3) not null
  comment '지역 위치',
  dispute tinyint not null default 0
  comment '분쟁 상태.
0: 분쟁 아님
1: 연합측 분쟁
2: 제국측 분쟁',
  connected tinyint not null default 0
  comment '본진과 연결되어 있음 (0: 연결 x, 1: 연합이 연결, 2: 제국이 연결)',
  occupy_force tinyint not null
  comment '점령 진영
1: 연합, 2: 제국, 3: 중립',
  occupied_at timestamp null
  comment '점령 일시',
  occupy_win_allies int not null default 0
  comment '연합의 승리 횟수',
  occupy_win_empire int not null default 0
  comment '제국의 승리 횟수',
  occupy_count_allies int not null default 0
  comment '연합의 점령전 횟수',
  occupy_count_empire int not null default 0
  comment '제국의 점령전 횟수',
  occupy_score_allies int not null default 0
  comment '연합 점령전 점수',
  occupy_score_empire int not null default 0
  comment '제국 점령전 점수',
 primary key (tile_id)
)
;

alter table tile add unique position (position)
;

-- table troop

create table troop
(
  troop_id int not null auto_increment,
  general_id int not null,
  officer_id int
  comment '병력 운용 장교',
  type_major int not null
  comment '대분류 병과 
1: 보병
2: 전차
',
  type_minor int not null
  comment '소분류 병과
',
  status int not null default 1
  comment '병력 상태
1: 훈련중
2: 미-지정 (훈련 완료 상태)
3: 지정, officer_id가 반드시 필요함
',
  qty int not null
  comment '병력수',
  slot int
  comment '슬롯번호(1~3: 전열, 4~6 후열)',
  training_at timestamp null
  comment '병력의 훈련 시작 시간',
  trained_at timestamp null
  comment '병력의 훈련 완료 시간',
 primary key (troop_id)
)
;

create index idx_trained_at on troop (trained_at)
;

create index idx_gid_status_types on troop (general_id,status,type_major,type_minor)
;

create index idx_gid_officer_id on troop (general_id,officer_id)
;

-- table item

create table item
(
  item_id int not null auto_increment,
  general_id int
  comment '아이템 소유 장군',
  type_major int
  comment '아이템 종류 (대분류)
1: 전투
2: 장비
3: 소모',
  type_minor int
  comment '아이템 종류 (소분류)',
  status tinyint not null default 1
  comment '아이템 상태. 
1: 소유되지 않음
2: 작성중 - owner: general_id
3: 창고에 있음.(장군 소유), owner: general_id
4: 메시지에 있음,(메시지 소유) owner: mail_id
5: 장비중.(장교 소유) owner: officer_id',
  owner_id int
  comment '아이템의 소유자 아이디 (general_id, officer_id, mail_id 중 하나). status와 함께 판단',
  qty int not null default 0
  comment '수량',
  level int
  comment '아이템 레벨',
  willbe_made_at timestamp null
  comment '아이템 작성 완료 시간',
 primary key (item_id)
)
;

create index idx_gid_status_willbe_made_at on item (general_id,status,willbe_made_at)
;

-- table quest

create table quest
(
  quest_id int not null auto_increment,
  general_id int,
  type int
  comment '퀘스트 종류
1: 메인
2: 일일
3: 주간',
  status int
  comment '퀘스트 진행 상태
1: 수락 (진행 중)
2: 완료 (종료 대기 중)
3: 종료 (보상 완료)',
  accepted_at timestamp null
  comment '퀘스트 수락 일시',
  completed_at timestamp null
  comment '퀘스트 완료 일시',
 primary key (quest_id)
)
;

-- table chat_force

create table chat_force
(
  chat_id int not null auto_increment,
  general_id int not null
  comment '메시지 sender',
  send_force tinyint not null default 3
  comment 'sender 진영 (1: 연합, 2: 제국, 3: 중립)',
  recv_force tinyint not null default 3
  comment 'receiver 진영 (1: 연합, 2: 제국, 3: 중립)',
  created_at timestamp not null
  comment '메시지 보낸 시간',
  body text
  comment '메시지 내용',
 primary key (chat_id)
)
;

-- table chat_legion

create table chat_legion
(
  chat_id int not null auto_increment,
  legion_id int not null
  comment '군단 아이디',
  general_id int not null
  comment '메시지 sender',
  created_at timestamp not null
  comment '메시지 보낸 시간',
  body text
  comment '메시지 내용',
 primary key (chat_id)
)
;

-- table payment

create table payment
(
  payment_id int not null auto_increment,
  general_id int,
  created_at timestamp null
  comment '결제 생성 일시',
  type int
  comment '결제 아이템 종류',
  status int
  comment '결제 상태
1: created (requires verification)
2: veritied (completed)
',
  qty int
  comment '아이템 구매 수량',
  receipt varchar(2000)
  comment '영수증 문자열',
 primary key (payment_id)
)
;

-- table notify

create table notify
(
  notify_id int not null auto_increment,
  general_id int,
  created_at timestamp null
  comment '푸시 메시지 요청 일시',
  sent_at timestamp null
  comment '푸시 메시지 전달 시간',
  status int
  comment '알림 상태
1: queued (not sent)
2: send requeted to push server
',
  body varchar(2000),
 primary key (notify_id)
)
;

-- table logs

create table logs
(
  logs_id int not null auto_increment,
  created_at timestamp null,
  type int not null default 1
  comment '기록 종류
(1: game-trace, 2: event-process, 3: item-event)',
  body text
  comment '메시지',
 primary key (logs_id)
)
;

-- table legion_invite

create table legion_invite
(
  legion_invite_id int not null auto_increment,
  legion_id int,
  general_id int,
  invited_at timestamp null
  comment '초대 일시',
  body varchar(2000)
  comment '초대 메시지',
 primary key (legion_invite_id)
)
;

-- table user

create table user
(
  user_id int not null auto_increment,
  username varchar(200) not null,
  password varchar(200) not null,
  email varchar(200),
  market_type tinyint not null default 0
  comment '마켓 종류
0: unknown
1: 구글
2: tstore
3: olleh
4: uplus
5: appstore

',
  user_status tinyint not null default 0
  comment '계정상태
0: 정상
1: 일시정지
2: 영구정지',
  payment_sum int not null default 0
  comment '누적 지불 총계',
  dev_type tinyint not null default 1
  comment '장치 종류. 1: 안드로이드, 2: ios',
  dev_uuid varchar(100)
  comment '장치고유 아이디',
  timezone varchar(200)
  comment '장치의 timezone',
  recommender varchar(200)
  comment '추천인',
  created_at timestamp not null
  comment '생성 일시',
  login_at timestamp null
  comment '로그인 일시',
  extra text
  comment '부가 정보(json object)',
 primary key (user_id)
)
;

alter table user add unique username (username)
;

-- table config

create table config
(
  name varchar(200) not null,
  value text not null
)
;

-- 
delete from user;
delete from general;



alter table config add primary key (name)
;

-- table randoms

create table randoms
(
  random_id int not null auto_increment,
  created_at timestamp not null,
  integers text not null
  comment 'json array of integers',
  floats text not null
  comment 'json array of floats',
 primary key (random_id)
)
;

-- table pushes

create table pushes
(
  pid bigint not null auto_increment,
  user_id int not null
  comment '유저아이디',
  src_id varchar(30) not null
  comment '큐에 넣은 source id string. 취소를 위해 필요함',
  queued_at timestamp null
  comment '큐에 들어온 일시',
  send_at timestamp not null
  comment '전달될 시간',
  sent bool not null default false
  comment '전달되었음',
  dev_type tinyint not null
  comment '장치 종류 (1: android, 2: ios)',
  dev_uuid varchar(200) not null
  comment '장치 uuid',
  body text not null
  comment '전달할 메시지',
 primary key (pid)
)
  comment = 'explain extended select * from `pushes` use index (idx_uid_sent) where (user_id > 10 and sent = false) and send_at < now()'
;

create index idx_src_id on pushes (src_id)
;

create index idx_send_at on pushes (sent,send_at)
;

create index idx_uid_sent on pushes (user_id,sent)
;

-- table tile_scores_general

create table tile_scores_general
(
  general_id int not null,
  tile_name char(3) not null
  comment '점수 적용 타일 이름',
  score int not null default 0
  comment '현재 점령전 점수',
  occupy_score int not null default 0
  comment '지난 점령전 점수'
)
;

create unique index idx_tilename_gid on tile_scores_general (tile_name,general_id)
;

-- table tile_scores_legion

create table tile_scores_legion
(
  legion_id int not null,
  tile_name char(3) not null
  comment '점수 적용 타일 이름',
  score int not null default 0
  comment '현재 군단전 점수',
  occupy_score int not null default 0
  comment '지난 군단전 점수'
)
;

create unique index idx_tilename_lid on tile_scores_legion (tile_name,legion_id)
;

-- table army

create table army
(
  general_id int not null,
  empire bool not null default false
  comment '군대의 진영
true: 제국, false: 연합',
  legion bool not null default false
  comment '군단의 군대인가?',
  officer_level_command int not null
  comment '매칭을 위한 장교 (100000*레벨 + 지휘력)',
  brief text
  comment '군대 정보. general, officer, troops'
)
;

alter table army add primary key (general_id)
;

create index idx_empire_legion_levelcommand on army (empire,legion,officer_level_command)
;

-- create relationships section ------------------------------------------------- 

alter table general add constraint relationship5 foreign key (legion_joined_id) references legion (legion_id) on delete cascade on update cascade
;

alter table officer add constraint relationship8 foreign key (general_id) references general (general_id) on delete cascade on update cascade
;

alter table mail add constraint relationship9 foreign key (general_id) references general (general_id) on delete cascade on update cascade
;

alter table construction add constraint relationship10 foreign key (general_id) references general (general_id) on delete cascade on update cascade
;

alter table combat add constraint relationship11 foreign key (general_id) references general (general_id) on delete cascade on update cascade
;

alter table tile add constraint relationship12 foreign key (battlefield_id) references battlefield (battlefield_id) on delete cascade on update cascade
;

alter table tile add constraint relationship14 foreign key (legion_id) references legion (legion_id) on delete cascade on update cascade
;

alter table troop add constraint relationship15 foreign key (officer_id) references officer (officer_id) on delete cascade on update cascade
;

alter table quest add constraint relationship20 foreign key (general_id) references general (general_id) on delete cascade on update cascade
;

alter table chat_force add constraint relationship22 foreign key (general_id) references general (general_id) on delete cascade on update cascade
;

alter table payment add constraint relationship39 foreign key (general_id) references general (general_id) on delete cascade on update cascade
;

alter table notify add constraint relationship40 foreign key (general_id) references general (general_id) on delete cascade on update cascade
;

alter table troop add constraint relationship43 foreign key (general_id) references general (general_id) on delete cascade on update cascade
;

alter table legion_invite add constraint relationship48 foreign key (legion_id) references legion (legion_id) on delete cascade on update cascade
;

alter table legion_invite add constraint relationship49 foreign key (general_id) references general (general_id) on delete cascade on update cascade
;

alter table general add constraint relationship52 foreign key (legion_joining_id) references legion (legion_id) on delete cascade on update cascade
;

alter table general add constraint relationship54 foreign key (user_id) references user (user_id) on delete cascade on update cascade
;

alter table item add constraint relationship55 foreign key (general_id) references general (general_id) on delete cascade on update cascade
;

alter table tile_scores_general add constraint relationship58 foreign key (general_id) references general (general_id) on delete cascade on update cascade
;

alter table tile_scores_legion add constraint relationship59 foreign key (legion_id) references legion (legion_id) on delete cascade on update cascade
;

alter table army add constraint relationship61 foreign key (general_id) references general (general_id) on delete cascade on update cascade
;

alter table chat_legion add constraint relationship65 foreign key (general_id) references general (general_id) on delete no action on update no action
;

alter table chat_legion add constraint relationship66 foreign key (legion_id) references legion (legion_id) on delete cascade on update cascade
;




