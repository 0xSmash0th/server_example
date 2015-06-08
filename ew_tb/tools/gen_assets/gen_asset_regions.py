import random
print 'DELETE FROM region;'
print 'DELETE FROM battlefield;'

print '''
INSERT INTO battlefield (battlefield_id, name, picture) VALUES
	(1, 'battlefield1', 'battlefield_background_1');'''
for i in xrange(1, 41):
	print '''
INSERT INTO region (
	region_id,
	battlefield_id,
	name,
	position,
	type	
) VALUES (
	{i},
	1,
	'regioname_{i}',
	{i},
	{type}
);'''.format(**{'i':i, 'type':random.randint(1, 5)})
