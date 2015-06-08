'''
Created on 2013. 7. 3.

@author: hjyun
'''
import unittest

from common import *
import copy

class ConstructionTest(unittest.TestCase):
	bids = [1000,1001,1002,1003,1004,1005,1006,1007,1008,1030,1031,1032,1033,1034,1035,1036,1037,1038,1009,1010,1060,1061,1062,1063,1064,1065,1066]
	bids_allies = [1000,1001,1002,1003,1004,1005,1006,1007,1008]
	bids_empire = [1030,1031,1032,1033,1034,1035,1036,1037,1038]
	bids_common = [1009,1010,1060,1061,1062,1063,1064,1065,1066]
	bids_hq = [1000,1001,1002,1003,1004,1005,1006,1030,1031,1032,1033,1034,1035,1036]
	bids_unlimited = [1060,1062]
	bids_limited = None 
	
	bids_allies_non_hq = None
	bids_empire_non_hq = None
	HQ_POS = 1
	NON_HQ_POS = None
	user = None
	username = 'test-user'
	password = 'pwd1234'
	registered = False

	@classmethod
	def setUpClass(cls):
		cls.NON_HQ_POS = range(1, 18)
		cls.NON_HQ_POS.remove(cls.HQ_POS)
		
		cls.bids_allies_non_hq = copy.deepcopy(cls.bids_allies)
		cls.bids_allies_non_hq.remove(1000)
		cls.bids_empire_non_hq = copy.deepcopy(cls.bids_empire)
		cls.bids_empire_non_hq.remove(1030)
		cls.bids_limited = [x for x in (set(cls.bids_common) - set(cls.bids_unlimited))]
				
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
	
			r = GET('general/general.php')
			js = expect(None, r)
			cls.general = js['general']
			
	@classmethod
	def tearDownClass(cls):
		if cls.user:
			r = GET('auth/logout.php')
		
	def test_getall(self):
		e = {}
		
		r = GET('build/construction.php', e)
		js = expect('success', r)
		
		assert len(js['constructions']) >= 0 
	
	def test_build(self):
		e = {}
		e['building_id'] = random.choice(self.bids_common)
		e['position'] = random.choice(ConstructionTest.NON_HQ_POS)
		e['op'] = 'clear,build'
		
		r = GET('build/construction.php', e)
		js = expect('built', r)
		assert len(js['constructions']) == 1, pretty(js)
		
	def test_build_out_of_scope(self):
		e = {}
		e['building_id'] = 100000;
		e['position'] = random.choice(ConstructionTest.NON_HQ_POS)
		e['op'] = 'build'
				
		r = GET('build/construction.php', e)
		js = expect('invalid:building_id', r)

		e['building_id'] = random.choice(self.bids_allies)
		e['position'] = 200
		e['op'] = 'build'
				
		r = GET('build/construction.php', e)
		js = expect('invalid:position', r)

		# invalid force
		e['building_id'] = random.choice(self.bids_empire_non_hq)
		e['position'] = random.choice(ConstructionTest.NON_HQ_POS)
		e['op'] = 'build'
				
		r = GET('build/construction.php', e)
		js = expect('invalid:force', r)

			
	def test_build_to_duplicated_position_or_building(self):
		e = {}
		e['building_id'] = random.choice(self.bids_limited)
		e['position'] = random.choice(ConstructionTest.NON_HQ_POS)
		e['op'] = 'clear,build'
		e['ignore'] = 1
				
		r = GET('build/construction.php', e)
		js = expect('built', r)
			
		e['op'] = 'build'
		r = GET('build/construction.php', e)
		js = expect('duplicated:position', r)

		OPOS = e['position'];
		while OPOS == e['position']:
			OPOS = random.choice(ConstructionTest.NON_HQ_POS)
			
		e['position'] = OPOS
		r = GET('build/construction.php', e)
		js = expect('duplicated:limit', r)
		
	def test_remove(self):
		e = {}
		e['building_id'] = random.choice(self.bids_unlimited)
		e['position'] = random.choice(ConstructionTest.NON_HQ_POS)
		e['op'] = 'clear,build'
		
		r = GET('build/construction.php', e)
		js = expect('built', r)
		assert len(js['constructions']) == 1, pretty(js)

		bld = js['constructions'][0]
		e['construction_id'] = bld['construction_id']
		e['op'] = 'remove'
		
		r = GET('build/construction.php', e)
		js = expect('removed', r)
		
		e = {}
		e['building_id'] = random.choice(self.bids_unlimited)
		e['position'] = random.choice(ConstructionTest.NON_HQ_POS)
		e['op'] = 'build'
		
		r = GET('build/construction.php', e)
		js = expect('built', r)
		assert len(js['constructions']) == 1

		# try to remove HQ (should fail)
		r = GET('build/construction.php')
		js = expect('success', r)
		hq = None
		for cons in js['constructions']:
			if cons['position'] == ConstructionTest.HQ_POS:
				hq = cons 

		bld = hq
		e['construction_id'] = bld['construction_id']
		e['op'] = 'remove'
		
		r = GET('build/construction.php', e)
		js = expect('invalid:cannot remove hq', r)
		
	def test_upgrade(self):
		e = {}
		e['op'] = 'clear'
		e['ignore'] = 1
		
 		r = GET('build/construction.php', e)
 		js = expect('success', r)
 		assert len(js['constructions']) == 1, pretty(js)
 
 		bld = js['constructions'][0]
 		e['construction_id'] = bld['construction_id']
 		e['op'] = 'upgrade'

 		for i in xrange(2, 51):  # HQ's maxlevel is 20		
 	 		r = GET('build/construction.php', e)
 		 	js = expect('upgraded', r)
 		 	assert int(js['constructions'][0]['cur_level']) == i, js

		r = GET('build/construction.php', e)
		js = expect('invalid:max_level', r)
		
	def test_reset_bld_cooltime(self):
		e = {}
		e['building_id'] = random.choice(self.bids_common)
		e['position'] = random.choice(ConstructionTest.NON_HQ_POS)
		e['op'] = 'clear,build'
		
 		r = GET('build/construction.php', e)
 		js = expect('built', r)
 		assert len(js['constructions']) == 1, pretty(js)

 		e = {}

 		r = GET('general/general.php', e)
 		js = expect('success', r)		
  		assert js['general']['bld_cool_end_at'] != None, pretty(js)
 
 		e = {}
 		e['op'] = 'reset_bld_cooltime'

 		r = GET('general/general.php', e)
 		js = expect('reset_bld_cooltime', r)
 		
 		assert js['general']['bld_cool_end_at'] == None, pretty(js)

	def test_build_special_hq(self):
		allies_shq_ids = [x for x in (set(ConstructionTest.bids_hq) - set(ConstructionTest.bids_empire))]
		allies_shq_ids.remove(1000)
		
		e = {}
		e['building_id'] = random.choice(allies_shq_ids)
		e['op'] = 'clear,build_shq'
		
 		r = GET('build/construction.php', e)
 		js = expect('success', r)

		shq_bid = e['building_id']
		e['op'] = 'build_shq'

		r = GET('build/construction.php', e)
 		js = expect('same shq bid was requested', r)

		e['building_id'] = 1000  # default HQ
		r = GET('build/construction.php', e)
 		js = expect('not a hq special building_id', r)
	
		e['building_id'] = shq_bid
		while shq_bid == e['building_id']:
			e['building_id'] = random.choice(allies_shq_ids)

 		# change it
 		r = GET('build/construction.php', e)
 		js = expect('success', r)
		
if __name__ == "__main__":
	# import sys;sys.argv = ['', 'Test.testName']
	unittest.main()
