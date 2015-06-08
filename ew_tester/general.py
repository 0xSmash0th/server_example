# -*- coding: utf-8 -*- 

'''
Created on 2013. 7. 2.

@author: hjyun
'''
import unittest

from common import *

class GeneralTest(unittest.TestCase):
	
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
	
	def test_get(self):
		e = {}
		e['op'] = 'clear,get'
		r = GET('general/general.php', e)
		js = expect(None, r)
# 		print js
		assert js['general']['user_id'] == self.user['user_id']
		assert js['general']['country'] in [1, 2]
		assert len(js['general']['name']) > 0  
	
	def test_edit(self):
		newname = 'new test! name__' + str(random.random())
#    		newname = '?��? test!?�름__' + str(random.random())
		newpicture = 'new_pic' + str(random.random())[:5]		
		
		edits = {}
		edits['name'] = newname
		edits['picture'] = newpicture
		edits['op'] = 'edit'
		
		r = GET('general/general.php', edits)
		js = expect(None, r)
# 		print js
		
		assert js['general']['name'] == newname
		assert js['general']['picture'] == newpicture

	def test_collect_tax(self):
		e = {}
		e['building_id'] = random.randint(1, 20)
		e['position'] = 10
		e['ignore'] = 1
		e['op'] = 'clear,get'
				
		r = GET('general/general.php', e)
		js = expect('success', r)

		logging.debug('waiting tax could be collectable...')
# 		time.sleep(1) # initially one time is collectable
		
		e['op'] = 'collect_tax'
		r = GET('general/general.php', e)
		js = expect('collect_tax', r)
		
		assert 0 <= js['general']['tax_collectable_count'] <= 4, pretty(js)

		r = GET('general/general.php', e)
		js = expect('not collectable', r)
		
		# run extra collect		
		e['op'] = 'extra_collect_tax'
		r = GET('general/general.php', e)
		js = expect('extra_collect_tax', r)
		
		assert 0 <= js['general']['tax_collectable_count'] <= 0, pretty(js)
	
	def test_skill_levelup_and_reset(self):
		e = {}
		e['op'] = 'clear,skills_levelup'
		e['ignore_points_total'] = 1
		e['skill_id'] = 101
		
		r = GET('general/general.php', e)
		js = expect('success', r)
		assert js['general']['skills']['101']['level'] == 1, pretty(js)

		e['op'] = 'skills_levelup'
		r = GET('general/general.php', e)
		js = expect('success', r)
		
		assert js['general']['skills']['101']['level'] == 2, pretty(js)

		for i in xrange(4):
			r = GET('general/general.php', e)
			js = expect('success', r)  # 3~6
				
		r = GET('general/general.php', e)
		js = expect('reached at max_level', r)  # expects error

		# # tests reset
		e['op'] = 'skills_reset'
		r = GET('general/general.php', e)
		js = expect('success', r)
	
		e['op'] = 'get'
		r = GET('general/general.php', e)
		js = expect('success', r)
		assert js['general']['skills'], pretty(js)
	
		e['op'] = 'skills_levelup'
		r = GET('general/general.php', e)
		js = expect('success', r)
		assert js['general']['skills']['101']['level'] == 1, pretty(js)
	
	def test_badge_equip(self):
		e = {}
		e['op'] = 'clear,badge_acquire'
 		e['ignore_badge_cooltime'] = 1
 		e['acquire'] = 'random'
 		
		r = GET('general/general.php', e)
		js = expect('success', r)
		
		badge_list = js['general']['badge_list']
		del badge_list['_']
		
		selected_badges = random.sample(badge_list.keys(), 2)
# 		selected_badges = ['5'] 
		
# 		print selected_badges
		e['op'] = 'badge_equip'
		e['badge_id'] = selected_badges[0]
		
		r = GET('general/general.php', e)
		js = expect('success', r)
		
		# already equipped
		r = GET('general/general.php', e)
		js = expect('equals to badge_equipped_id', r)
		
		# equip another
		e['badge_id'] = selected_badges[1]
		r = GET('general/general.php', e)
		js = expect('success', r)

		# badge_id not in badge_list (dev will supply all badges)
# 		e['badge_id'] = 10
# 		r = GET('general/general.php', e)
# 		js = expect('dont have that badge', r)
		
		# equip 1 again with cooltime
 		del e['ignore_badge_cooltime']
		e['badge_id'] = selected_badges[0]
		r = GET('general/general.php', e)
		js = expect('badge equip cooltime error: should wait', r)
						
	def test_tutorials(self):
		e = {}
		e['op'] = 'tutorial_reset' 		
		r = GET('general/general.php', e)
		js = expect('success', r)
		
		tutorial_list = js['general']['tutorial_list']
		assert len(tutorial_list) > 0, pretty(js)

		tid = random.choice(tutorial_list);
		
		e['op'] = 'tutorial_finish'
		e['tutorial_id'] = tid
		r = GET('general/general.php', e)
		js = expect('success', r)
		
		tutorial_list = js['general']['tutorial_list']
		assert tid not in tutorial_list, pretty(js)					

		e['op'] = 'tutorial_reset' 		
		r = GET('general/general.php', e)
		js = expect('success', r)
		tutorial_list = js['general']['tutorial_list']
		assert tid in tutorial_list, pretty(js)
		
if __name__ == "__main__":
	# import sys;sys.argv = ['', 'Test.testName']
	unittest.main()
