'''
Created on 2013. 7. 2.

@author: hjyun
'''
import unittest

from common import *

class ShopTest(unittest.TestCase):

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
		
	def test_get_and_buy_some(self):
		# clear items
		e = {}
		e['op'] = 'clear,get'
		r = GET('army/item.php', e)
		js = expect('success', r)
		
		e = {}
		e['op'] = 'clear,get'
		
		# get product_list
		r = GET('general/shop.php', e)
		js = expect('success', r)
		assert len(js['products']) > 0, pretty(js)
		products = js['products']
		
		# buy gold, honor, star
		for et in ['gold', 'honor', 'star']:
			for product in random.sample(products[et + 's'], 3):
				e['op'] = 'buy'
				e['ignore'] = 1
				e['product_id'] = product['product_id']
				r = GET('general/shop.php', e)
				js = expect('success', r)

		# buy items
# 		print pretty(products['items'])
		qty_cur = 0
		qty_max = 99		
		pid = random.choice(products['items'])['product_id']
		while (qty_cur + 10) <= qty_max:
			e = {}
			e['op'] = 'buy'
			# e['ignore'] = 1
			e['product_id'] = pid
			r = GET('general/shop.php', e)
			js = expect('success', r)
			
			qty_cur += 10

		# should fail
		r = GET('general/shop.php', e)
		js = expect('overall storage slot exceeds limit_owned', r)
		

if __name__ == "__main__":
	# import sys;sys.argv = ['', 'Test.testName']
	unittest.main()
