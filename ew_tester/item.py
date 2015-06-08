'''
Created on 2013. 7. 2.

@author: hjyun
'''
import unittest

from common import *

def prepare_items(num=5, clear=True):

	# build lab for item	
	e = {}
	e['building_id'] = 1066
	e['position'] = 10
	e['op'] = 'clear,build'
		
	r = GET('build/construction.php', e)
	js = expect('built', r)
		
	e = {}
	e['op'] = 'make_list'
	r = GET('army/item.php', e)
	js = expect('success', r)
	
	assert 'equips' in js['items'], pretty(js)
	assert 'consumes' in js['items'], pretty(js)
	assert 'combats' in js['items'], pretty(js)
	
	candidates = []
	
	for v in js['items']['combats'].values():
		if 'valid' in v and v['valid'] > 0: candidates.append(v)
# 	for v in js['items']['consumes'].values():
# 		if 'valid' in v and v['valid'] > 0: candidates.append(v)
# 	for v in js['items']['equips'].values():
# 		if 'valid' in v and v['valid'] > 0: candidates.append(v)
	
# 	print 'candidates:', len(candidates)
	num = min([num, len(candidates)])
	minors = random.sample(candidates, num)
	items = []
	for i in xrange(num):
		e = {}
		e['op'] = 'make'
		if i == 0 and clear:
			e['op'] += ',clear'
		
# 		minor = random.choice(candidates)
		minor = minors[i]
		
		e['cost_time'] = 0
		e['type_major'] = minor['type_major']
		e['type_minor'] = minor['id']
		e['qty'] = random.randint(1, 10)
		r = GET('army/item.php', e)
		js = expect('make started', r)
		assert len(js['items']) == 1, pretty(js)
		
		items.append(js['items'][0])
	
	items = []
	e['op'] = 'get'
	r = GET('army/item.php', e)
	js = expect('success', r)
	return js['items']
	
class ItemTest(unittest.TestCase):
	user = None
	username = 'test-user'
	password = 'pwd1234'
	registered = False

	@classmethod
	def setUpClass(cls):
		if not cls.user:
			e = {'username': cls.username, 'password': cls.password}
			e = dict(e.items() + login_params.items())
			
			if not cls.registered:				
				r = GET('auth/register.php', e)
				js = expect(None, r)
				assert 'already exists' in js['message'] or 'registered' in js['message'], pretty(js) 
				cls.registered = True
			
			r = GET('auth/login.php', e)
			js = expect(None, r)
					
			cls.user = js['user']
			cls.constants = js['constants']
	
			r = GET('general/general.php')
			js = expect(None, r)
			cls.general = js['general']

	@classmethod
	def tearDownClass(cls):
		if cls.user:
			r = GET('auth/logout.php')
	
	def test_get_all(self):		
		prepare_items();
		
		e = {}
				
		# get all
		r = GET('army/item.php', e)
		js = expect('success', r)
		assert len(js['items']) > 0, pretty(js)
		
	def test_item_making_and_selling(self):
		
		# build lab for item	
		e = {}
		e['building_id'] = 1066
		e['position'] = 10
		e['op'] = 'clear,build'
			
		r = GET('build/construction.php', e)
		js = expect('built', r)


		e = {}
		e['op'] = 'make_list'
		r = GET('army/item.php', e)
		js = expect('success', r)
		
		assert 'equips' in js['items'], pretty(js)		
		assert 'consumes' in js['items'], pretty(js)
		assert 'combats' in js['items'], pretty(js)
		
		candidates = []	
		
		for v in js['items']['combats'].values():
			if 'valid' in v and v['valid'] > 0: candidates.append(v)
# 		for v in js['items']['consumes'].values():
# 			if 'valid' in v and v['valid'] > 0: candidates.append(v)
# 		for v in js['items']['equips'].values():
# 			if 'valid' in v and v['valid'] > 0: candidates.append(v)

		minor = random.choice(candidates)
		
		e = {}
		e['op'] = 'make,clear'
		
		e['cost_time'] = 100
		e['type_major'] = minor['type_major']
		e['type_minor'] = minor['id']
		e['qty'] = random.randint(1, 10)
		r = GET('army/item.php', e)
		js = expect('make started', r)
		assert len(js['items']) == 1, pretty(js)
		item = js['items'][0]
		
		# should fail, already making
		e['op'] = 'make'
		r = GET('army/item.php', e)
		js = expect('already', r)
		
		# haste
		e['op'] = 'make_haste'
		e['item_id'] = item['item_id']
		r = GET('army/item.php', e)
		js = expect('make_haste', r)
		
		e['op'] = 'make'
		e['cost_time'] = 2
		r = GET('army/item.php', e)
		js = expect('make started', r)
		item = js['items'][0]
		
		logging.debug('waiting making_time: ' + str(item['cost_time']) + ' seconds')
		time.sleep(item['cost_time'])
		
		# get base item
		e = {}
		e['type_major'] = minor['type_major']
		e['type_minor'] = minor['id']
		r = GET('army/item.php', e)
		js = expect('success', r)
		base_item = js['items'][0]
		
		# verify
		e['op'] = 'get'
		e['item_id'] = base_item['item_id']
		r = GET('army/item.php', e)
		js = expect('success', r)
		item = js['items'][0]
		
		assert item['status'] == 3, pretty(js)
		
		# sell it
		e['op'] = 'sell'
		e['ignore'] = 1  # ignore in-sellables
		e['item_id'] = item['item_id']
		e['qty'] = item['qty']
		r = GET('army/item.php', e)
		js = expect('success', r)
		
		e['op'] = 'get'
		e['item_id'] = item['item_id']
		r = GET('army/item.php', e)
		js = expect('invalid:item_id', r)  # as I sold them all!
		
		
	def test_expand_storage_slot_cap(self):
		e = {}
		e['op'] = 'get,clear'
		e = dict(e.items() + login_params.items())
		
		slot_max = ItemTest.constants['ITEM_STORAGE_SLOT_MAX']  # get this from server
		
		r = GET('general/general.php', e)
		js = expect('success', r)
		
		general = js['general']
		
		for i in xrange(general['item_storage_slot_cap'], slot_max):
			e['op'] = 'expand_storage_slot_cap'
			r = GET('army/item.php', e)
			js = expect('success', r)
	
		r = GET('army/item.php', e)
		js = expect('item_storage_slot_cap at max', r)		
			
	def test_consume(self):
		import officer
		officer = officer.clear_and_hires()
		
		e = {}
		e['op'] = 'make_list,clear'
		r = GET('army/item.php', e)
		js = expect('success', r)
		
		assert 'equips' in js['items'], pretty(js)		
		assert 'consumes' in js['items'], pretty(js)
		assert 'combats' in js['items'], pretty(js)
		
		candidates = []	
		
# 		for v in js['items']['combats'].values(): if 'valid' in v: candidates.append(v)
		for v in js['items']['consumes'].values():
			if 'valid' in v: candidates.append(v)
# 		for v in js['items']['equips'].values(): if 'valid' in v: candidates.append(v)

		e = {}
		for candidate in candidates:
			e['op'] = 'gift'
			e['acl'] = 'operator'
			e['type_major'] = candidate['type_major']
			e['type_minor'] = candidate['id']
			e['qty'] = random.randint(20, 40)
			r = GET('army/item.php', e)
			js = expect('success', r)
			assert len(js['items']) == 1, pretty(js)

		e = {}
		r = GET('army/item.php', e)
		js = expect('success', r)
		items = js['items']
		
# 		print pretty(items)
		
		# use half of items
		for item in items:
			if item['type_major'] != 3: continue
			
			e['ignore'] = 1
			e['op'] = 'consume'
			e['item_id'] = item['item_id']
			e['qty'] = item['qty'] / 2
			if item['type_minor'] == 331101:
				e['officer_id'] = officer['officer_id']
				
			r = GET('army/item.php', e)
			js = expect('consumed', r)
			
			item['qty'] -= e['qty']
			
		# use remaining of items
		for item in items:
			if item['type_major'] != 3: continue
			
			e['ignore'] = 1
			e['op'] = 'consume'
			e['item_id'] = item['item_id']
			e['qty'] = item['qty']
			if item['type_minor'] == 331101:
				e['officer_id'] = officer['officer_id']
				
			r = GET('army/item.php', e)
			js = expect('consumed', r)

		# all consumable items shound not be found
		e = {}
		r = GET('army/item.php', e)
		js = expect('success', r)
		items = js['items']
		for item in items:
			assert item['type_major'] != 3, pretty(js)


if __name__ == "__main__":
	# import sys;sys.argv = ['', 'Test.testName']
	unittest.main()
