import random

print 'DELETE FROM building;'

for i in xrange(1, 21):
	print '''
INSERT INTO building (building_id, name, picture, max_level, cost , build_time) VALUES
({i}, 'bldname{i}', 'bldpic{i}', {max_level}, {cost}, {bld_time});
'''.format(**{'i':i, 'max_level': ((i%5)+1)*5, 'cost': random.randint(1000,9999), 'bld_time': random.randint(10,99)})
