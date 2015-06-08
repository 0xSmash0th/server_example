'''
Created on 2013. 7. 2.

@author: hjyun
'''
import unittest

from common import *

class TileTest(unittest.TestCase):

	user = None
	username = 'test-user'
	password = 'pwd1234'
	registered = False

	tiles_forts = ["B04","C08","E04","F01","F07","F13","G11","I07","J10"]
	tiles_normal = ["A10","A11","A12","B03","B05","B06","B07","B08","B10","B13","C03",
				"C05","C12","C14","D02","D05","D07","D09","E02","E06","E07","E08","E10",
				"E11","E13","F03","F05","F06","F08","F09","F11","G02","G04","G05","G07",
				"G08","G09","G13","H05","H07","H09","H12","I01","I03","I10","I12","J01",
				"J04","J06","J07","J08","J09","J11","K03","K04","K05"]
	tiles_allies = ["B03","C03","D02","E02","F03","F05","F06","G02","G04","G05","G07","G08","G09","H05","H07","H09",
				"I01","I03","I10","J01","J04","J06","J07","J08","J09","K03","K04","K05"]
	tiles_empire = ["A10","A11","A12","B05","B06","B07","B08","B10","B13","C05","C12","C14","D05","D07","D09","E06",
				"E07","E08","E10","E11","E13","F08","F09","F11","G13","H12","I12","J11"]

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
		
	def test_get_all(self):
		e = {}
		
		# get all
		r = GET('battlefield/tile.php', e)
		js = expect('success', r)
		assert len(js['tiles']) > 1, pretty(js)

		# get a tile info
		pos = random.choice(js['tiles'])['position']
				
		e = {'position': pos}
		r = GET('battlefield/tile.php', e)
		js = expect('success', r)
		assert len(js['tiles']) == 1, pretty(js)
		assert pos == js['tiles'][0]['position'], pretty(js)
		
	def test_invalid(self):
		e = {'position': 'not-available'}
		r = GET('battlefield/tile.php', e)
		js = expect('invalid:position', r)

if __name__ == "__main__":
	# import sys;sys.argv = ['', 'Test.testName']
	unittest.main()
