-- 
DELETE FROM user;
DELETE FROM general;

INSERT INTO user (user_id, username, password, created_at, login_at) VALUES 
	(1, 'test', '1234', NOW(), NOW());
INSERT INTO general (country, name, user_id) VALUES ('1', 'test', 1);

DELETE FROM region;
DELETE FROM battlefield;

INSERT INTO battlefield (battlefield_id, name, picture) VALUES
	(1, 'battlefield1', 'battlefield_background_1');

INSERT INTO region (
	region_id,
	battlefield_id,
	name,
	position,
	type	
) VALUES (
	1,
	1,
	'regioname_1',
	1,
	3
);

INSERT INTO region (
	region_id,
	battlefield_id,
	name,
	position,
	type	
) VALUES (
	2,
	1,
	'regioname_2',
	2,
	3
);

INSERT INTO region (
	region_id,
	battlefield_id,
	name,
	position,
	type	
) VALUES (
	3,
	1,
	'regioname_3',
	3,
	2
);

INSERT INTO region (
	region_id,
	battlefield_id,
	name,
	position,
	type	
) VALUES (
	4,
	1,
	'regioname_4',
	4,
	1
);

INSERT INTO region (
	region_id,
	battlefield_id,
	name,
	position,
	type	
) VALUES (
	5,
	1,
	'regioname_5',
	5,
	3
);

INSERT INTO region (
	region_id,
	battlefield_id,
	name,
	position,
	type	
) VALUES (
	6,
	1,
	'regioname_6',
	6,
	2
);

INSERT INTO region (
	region_id,
	battlefield_id,
	name,
	position,
	type	
) VALUES (
	7,
	1,
	'regioname_7',
	7,
	2
);

INSERT INTO region (
	region_id,
	battlefield_id,
	name,
	position,
	type	
) VALUES (
	8,
	1,
	'regioname_8',
	8,
	5
);

INSERT INTO region (
	region_id,
	battlefield_id,
	name,
	position,
	type	
) VALUES (
	9,
	1,
	'regioname_9',
	9,
	2
);

INSERT INTO region (
	region_id,
	battlefield_id,
	name,
	position,
	type	
) VALUES (
	10,
	1,
	'regioname_10',
	10,
	5
);

INSERT INTO region (
	region_id,
	battlefield_id,
	name,
	position,
	type	
) VALUES (
	11,
	1,
	'regioname_11',
	11,
	1
);

INSERT INTO region (
	region_id,
	battlefield_id,
	name,
	position,
	type	
) VALUES (
	12,
	1,
	'regioname_12',
	12,
	3
);

INSERT INTO region (
	region_id,
	battlefield_id,
	name,
	position,
	type	
) VALUES (
	13,
	1,
	'regioname_13',
	13,
	1
);

INSERT INTO region (
	region_id,
	battlefield_id,
	name,
	position,
	type	
) VALUES (
	14,
	1,
	'regioname_14',
	14,
	3
);

INSERT INTO region (
	region_id,
	battlefield_id,
	name,
	position,
	type	
) VALUES (
	15,
	1,
	'regioname_15',
	15,
	5
);

INSERT INTO region (
	region_id,
	battlefield_id,
	name,
	position,
	type	
) VALUES (
	16,
	1,
	'regioname_16',
	16,
	5
);

INSERT INTO region (
	region_id,
	battlefield_id,
	name,
	position,
	type	
) VALUES (
	17,
	1,
	'regioname_17',
	17,
	4
);

INSERT INTO region (
	region_id,
	battlefield_id,
	name,
	position,
	type	
) VALUES (
	18,
	1,
	'regioname_18',
	18,
	3
);

INSERT INTO region (
	region_id,
	battlefield_id,
	name,
	position,
	type	
) VALUES (
	19,
	1,
	'regioname_19',
	19,
	3
);

INSERT INTO region (
	region_id,
	battlefield_id,
	name,
	position,
	type	
) VALUES (
	20,
	1,
	'regioname_20',
	20,
	5
);

INSERT INTO region (
	region_id,
	battlefield_id,
	name,
	position,
	type	
) VALUES (
	21,
	1,
	'regioname_21',
	21,
	4
);

INSERT INTO region (
	region_id,
	battlefield_id,
	name,
	position,
	type	
) VALUES (
	22,
	1,
	'regioname_22',
	22,
	1
);

INSERT INTO region (
	region_id,
	battlefield_id,
	name,
	position,
	type	
) VALUES (
	23,
	1,
	'regioname_23',
	23,
	3
);

INSERT INTO region (
	region_id,
	battlefield_id,
	name,
	position,
	type	
) VALUES (
	24,
	1,
	'regioname_24',
	24,
	2
);

INSERT INTO region (
	region_id,
	battlefield_id,
	name,
	position,
	type	
) VALUES (
	25,
	1,
	'regioname_25',
	25,
	1
);

INSERT INTO region (
	region_id,
	battlefield_id,
	name,
	position,
	type	
) VALUES (
	26,
	1,
	'regioname_26',
	26,
	4
);

INSERT INTO region (
	region_id,
	battlefield_id,
	name,
	position,
	type	
) VALUES (
	27,
	1,
	'regioname_27',
	27,
	5
);

INSERT INTO region (
	region_id,
	battlefield_id,
	name,
	position,
	type	
) VALUES (
	28,
	1,
	'regioname_28',
	28,
	1
);

INSERT INTO region (
	region_id,
	battlefield_id,
	name,
	position,
	type	
) VALUES (
	29,
	1,
	'regioname_29',
	29,
	4
);

INSERT INTO region (
	region_id,
	battlefield_id,
	name,
	position,
	type	
) VALUES (
	30,
	1,
	'regioname_30',
	30,
	3
);

INSERT INTO region (
	region_id,
	battlefield_id,
	name,
	position,
	type	
) VALUES (
	31,
	1,
	'regioname_31',
	31,
	1
);

INSERT INTO region (
	region_id,
	battlefield_id,
	name,
	position,
	type	
) VALUES (
	32,
	1,
	'regioname_32',
	32,
	2
);

INSERT INTO region (
	region_id,
	battlefield_id,
	name,
	position,
	type	
) VALUES (
	33,
	1,
	'regioname_33',
	33,
	5
);

INSERT INTO region (
	region_id,
	battlefield_id,
	name,
	position,
	type	
) VALUES (
	34,
	1,
	'regioname_34',
	34,
	3
);

INSERT INTO region (
	region_id,
	battlefield_id,
	name,
	position,
	type	
) VALUES (
	35,
	1,
	'regioname_35',
	35,
	5
);

INSERT INTO region (
	region_id,
	battlefield_id,
	name,
	position,
	type	
) VALUES (
	36,
	1,
	'regioname_36',
	36,
	4
);

INSERT INTO region (
	region_id,
	battlefield_id,
	name,
	position,
	type	
) VALUES (
	37,
	1,
	'regioname_37',
	37,
	2
);

INSERT INTO region (
	region_id,
	battlefield_id,
	name,
	position,
	type	
) VALUES (
	38,
	1,
	'regioname_38',
	38,
	1
);

INSERT INTO region (
	region_id,
	battlefield_id,
	name,
	position,
	type	
) VALUES (
	39,
	1,
	'regioname_39',
	39,
	1
);

INSERT INTO region (
	region_id,
	battlefield_id,
	name,
	position,
	type	
) VALUES (
	40,
	1,
	'regioname_40',
	40,
	5
);
