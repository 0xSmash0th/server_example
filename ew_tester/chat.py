'''
Created on 2013. 7. 2.

@author: hjyun
'''
import unittest

from common import *

class ChatTest(unittest.TestCase):
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
	
	def test_get_and_send(self):		
		e = {}
				
		# get my force
		e['op'] = 'get'
		r = GET('general/chat.php', e)
		js = expect('success', r)
		assert len(js['chats']) >= 0, pretty(js)
				
		# get neutral
		e['recv_force'] = 3
		r = GET('general/chat.php', e)
		js = expect('success', r)
		assert len(js['chats']) >= 0, pretty(js)
	
		# send some messages
		e['ignore'] = 1  # ignore send cooltime
		for i in xrange(10):
			e['recv_force'] = random.choice([3, ChatTest.general['country']]);
			e['op'] = 'send'
			e['body'] = 'test message at ' + str(time.time())
			r = GET('general/chat.php', e)
			js = expect('sent', r)

		e['op'] = 'get'
		del e['recv_force']
		# get my force
		r = GET('general/chat.php', e)
		js = expect('success', r)
		assert len(js['chats']) >= 0 and len(js['chats']) <= 50, pretty(js)
				
		# get neutral
		e['op'] = 'get'
		e['recv_force'] = 3
		r = GET('general/chat.php', e)
		js = expect('success', r)
		assert len(js['chats']) >= 0 and len(js['chats']) <= 50, pretty(js)

# 		print pretty(js)
		# check notices (string)
		#assert 'notice_system' in js['notices'], pretty(js)
		#assert 'notice_event' in js['notices'], pretty(js)
				
		# test bad words
		####################################
		############
		
		# badword list
		e['op'] = 'chat_badword_list'
		e['insure_sample'] = 1
		r = GET('general/chat.php', e)
		js = expect('success', r)
		assert 'chat_badword_list' in js, pretty(js);
		badwords = js['chat_badword_list']
		assert len(badwords) > 0, pretty(js)
		
		badword = random.choice(badwords)
		ubadword = badword.encode('utf-8')
		
		e['op'] = 'send'
		e['recv_force'] = random.choice([3, ChatTest.general['country']]);		
		e['body'] = 'test message ['+str(ubadword)+'] at ' + str(time.time())
		r = GET('general/chat.php', e)
		js = expect('sent', r)

		# get neutral and check that badwords are not found
		e['op'] = 'get'
		e['recv_force'] = 3
		r = GET('general/chat.php', e)
		js = expect('success', r)
		for chat in js['chats']:
			ubody = chat['body'].encode('utf-8')
			assert ubadword not in str(ubody), pretty(chat)

		# test long body
		e['op'] = 'send'
		e['body'] = 'test message [bad words] at ' + str(time.time())
		e['body'] *= 10
		r = GET('general/chat.php', e)
		js = expect('message length exceeded limit', r)

	def DEPR_test_notice(self):		
		e = {}
		
		for i in xrange(3):
			e['op'] = 'notice'
			e['acl'] = 'operator'
			e['body'] = 'notice set at ' + str(time.time())
			e['notice_type'] = random.choice([1, 2])
				
			r = GET('general/chat.php', e)
			js = expect('notice set', r)
			
			if e['notice_type'] == 1:
				assert js['notices']['notice_system'] == e['body'], pretty(js)
			else:
				assert js['notices']['notice_event'] == e['body'], pretty(js)
		
	def test_ban(self):		
		e = {}

		# clear ban list
		e['op'] = 'ban_list'
		r = GET('general/chat.php', e)
		js = expect('success', r)

		assert 'chat_ban_list' in js, pretty(js)
		
		gids = []
		for w in js['chat_ban_list']:
			if w['general_id'] not in gids:
				gids.append(w['general_id'])
			
		if len(gids) > 0:
			e['op'] = 'ban_del'
			e['ban_gids'] = gids
			r = GET('general/chat.php', e)
			js = expect('success', r)

			assert len(js['chat_ban_list']) == 0, pretty(js)
		
		# ban self
		e['op'] = 'ban_add'
		e['ban_gid'] = ChatTest.general['general_id']
		r = GET('general/chat.php', e)
		js = expect('success', r)

		assert len(js['chat_ban_list']) == 1, pretty(js)

		# send message
		e['recv_force'] = 3;
		e['op'] = 'send'
		e['ignore'] = 1
		e['body'] = 'test message at ' + str(time.time())
		r = GET('general/chat.php', e)
		js = expect('sent', r)
				
		# get neutral
		e['recv_force'] = 3
		e['op'] = 'get'
		r = GET('general/chat.php', e)
		js = expect('success', r)
		
		for chat in js['chats']:
			assert chat['general_id'] != ChatTest.general['general_id'], pretty(js)
		
		# unban
		e['op'] = 'ban_del'
		e['ban_gids'] = [ChatTest.general['general_id']]
		r = GET('general/chat.php', e)
		js = expect('success', r)

		assert len(js['chat_ban_list']) == 0, pretty(js)

		# get neutral
		e['recv_force'] = 3
		e['op'] = 'get'
		r = GET('general/chat.php', e)
		js = expect('success', r)
		assert len(js['chats']) > 0, pretty(js)
				
if __name__ == "__main__":
	# import sys;sys.argv = ['', 'Test.testName']
	unittest.main()
