'''
Created on 2013. 7. 2.

@author: hjyun
'''
import unittest
import requests
import time, json
import random

from common import *

class AuthTest(unittest.TestCase):
	user = None
	username = 'test-user'
	password = 'pwd1234'
	registered = False

	@classmethod
	def setUpClass(cls):
		return 
		if not cls.user:
			e = {'username': cls.username, 'password': cls.password}
			e = dict(e.items() + login_params.items())
			
			if not cls.registered:				
				r = GET('auth/register.php', e)
				js = expect(None, r)
				if 'already exists' in js['message']:
					cls.registered = True
					r = GET('auth/login.php', e)
					js = expect(None, r)
					
				cls.user = js['user']
	
			r = GET('general/general.php')
			js = expect(None, r)
			cls.general = js['general']

	@classmethod
	def tearDownClass(cls):
		return 
		if cls.user:
			r = GET('auth/logout.php')

	def test_auth(self):
		user = 'test-user'
		user = 'test_' + str(random.random())		
		passwd = 'pwd1234'
		
		e = {}
		e['username'] = user
		e['password'] = passwd
		r = GET('auth/register.php', e)
		expect('registered', r)
		
		r = GET('auth/logout.php')
		expect('not logged-in', r)
				
		r = GET('auth/login.php', e)
		expect('success', r)

		r = GET('auth/login.php', e)
		expect('already logged in', r)

		r = GET('auth/logout.php')
		expect('logged out', r)
		
if __name__ == "__main__":
	# import sys;sys.argv = ['', 'Test.testName']
	unittest.main()
