'''
Created on 2013. 7. 2.

@author: hjyun
'''
import unittest

from common import *

class QuestTest(unittest.TestCase):

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
	
			r = GET('general/general.php')
			js = expect(None, r)
			cls.general = js['general']

	@classmethod
	def tearDownClass(cls):
		if cls.user:
			r = GET('auth/logout.php')
		
	def test_get_and_reward(self):
		e = {}
		e['op'] = 'clear,get'
		
		# get quest_list
		r = GET('general/general.php', e)
		js = expect('success', r)
		assert len(js['general']['quest_list']) > 0, pretty(js)
		
		assert len(js['general']['quest_list']['accepted']) > 0, pretty(js)
		assert len(js['general']['quest_list']['completed']) > 0, pretty(js)

		for t in ['accepted', 'completed', 'rewarded']:
			del js['general']['quest_list'][t]['_']
		
		# trigger quest completion (upgrades)
		r = GET('build/construction.php', {})
 		js = expect('success', r)
 		assert len(js['constructions']) == 1, pretty(js)
 
 		bld = js['constructions'][0]
 		e['construction_id'] = bld['construction_id']
 		e['op'] = 'upgrade'
 		r = GET('build/construction.php', e)
 		js = expect('upgrade', r)
 				
		e['op'] = 'get'
		r = GET('general/general.php', e)
		js = expect('success', r)

		for t in ['accepted', 'completed', 'rewarded']:
			del js['general']['quest_list'][t]['_']
		
		# get reward		
		e['op'] = 'reward'
		e['quest_id'] = random.choice(js['general']['quest_list']['completed'].keys())
		r = GET('general/quest.php', e)
		js = expect('success', r)

		assert e['quest_id'] in js['general']['quest_list']['rewarded'].keys(), pretty(js)

if __name__ == "__main__":
	# import sys;sys.argv = ['', 'Test.testName']
	unittest.main()
