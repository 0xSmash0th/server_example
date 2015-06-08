'''
Created on 2013. 7. 2.

@author: hjyun
'''
import unittest

from common import *

class MailTest(unittest.TestCase):
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

		e['op'] = 'clear,get'
		e['type'] = 3
		r = GET('general/mail.php', e)
		js = expect('success', r)
		assert len(js['mails']) == 0, pretty(js)
	
		# send some messages to self
		qty = 5
		for i in xrange(qty):
			e['recv_name'] = MailTest.user['username']
			e['op'] = 'send'
			e['title'] = 'test title ' + str(random.randint(0, 100))
			e['body'] = 'test message body at ' + str(time.time())
			r = GET('general/mail.php', e)
			js = expect('sent', r)

		e['op'] = 'get'
		e['type'] = 3
		r = GET('general/mail.php', e)
		js = expect('success', r)
		assert len(js['mails']) == qty, pretty(js)

		# check some
		checkeds = random.sample(js['mails'], 2)
		for c in checkeds:
			e['op'] = 'detail'
			e['mail_id'] = c['mail_id']
			r = GET('general/mail.php', e)
			js = expect('success', r)

		e['op'] = 'get'
		e['type'] = 3
		r = GET('general/mail.php', e)
		js = expect('success', r)
		checked = 0;
		for mail in js['mails']:
			if mail['checked']: checked += 1			
		assert checked == 2, pretty(js)
		
		# archive one
		archived = random.sample(js['mails'], 1)
		e['op'] = 'archive'
		e['mail_ids'] = [c['mail_id']]
		r = GET('general/mail.php', e)
		js = expect('success', r)

		e['op'] = 'get'
		e['archived'] = 1
		r = GET('general/mail.php', e)
		js = expect('success', r)
		assert len(js['mails']) == 1, pretty(js)		

		# send some system messages to self
		qty = 3
		for i in xrange(qty):
			e['recv_name'] = MailTest.user['username']
			e['op'] = 'send'
			e['acl'] = 'operator'
			e['title'] = '[system] title ' + str(random.randint(0, 100))
			e['body'] = '[system] test message body at ' + str(time.time())
			e['gifts'] = {'items':[], 'gold':random.randint(1000, 2000), 'honor':random.randint(100, 200)} 
			r = GET('general/mail.php', e)
			js = expect('sent', r)		
			
		e = {}
		e['op'] = 'get'
		e['type'] = 2
		r = GET('general/mail.php', e)
		js = expect('success', r)
		assert len(js['mails']) == qty, pretty(js)
		
		# test delete
		e['op'] = 'delete'
		e['mail_ids'] = [ mail['mail_id'] for mail in js['mails'] ] 
		r = GET('general/mail.php', e)
		js = expect('success', r)
		
		e = {}
		e['op'] = 'get'
		e['type'] = 2
		r = GET('general/mail.php', e)
		js = expect('success', r)
		assert len(js['mails']) == 0, pretty(js)
		
	def test_with_gifts(self):		
		e = {}

		e['op'] = 'clear,get'
		r = GET('army/item.php', e)
		js = expect('success', r)
		
		e['op'] = 'clear,get'
		e['type'] = 2
		r = GET('general/mail.php', e)
		js = expect('success', r)
		assert len(js['mails']) == 0, pretty(js)
	
		# send a system messages to self with gifts
		e['recv_name'] = MailTest.user['username']
		e['op'] = 'send'
		e['acl'] = 'operator'
		e['title'] = 'test title ' + str(random.randint(0, 100))
		e['body'] = 'test message body at ' + str(time.time())
		e['gifts'] = {}
		e['gifts']['gold'] = random.randint(1, 100);
		e['gifts']['honor'] = random.randint(1, 100);
		items = []
		items.append({'type_major':1, 'type_minor':212001, 'qty':random.randint(10, 30)})
		items.append({'type_major':1, 'type_minor':212002, 'qty':random.randint(10, 30)})
		items.append({'type_major':3, 'type_minor':331101, 'qty':random.randint(10, 30)})
		items.append({'type_major':3, 'type_minor':331201, 'qty':random.randint(10, 30)})
		e['gifts']['items'] = items;		
		
		r = GET('general/mail.php', e)
		js = expect('sent', r)

		e['op'] = 'get'
		e['type'] = 2
		r = GET('general/mail.php', e)
		js = expect('success', r)
		assert len(js['mails']) == 1, pretty(js)

		mail = js['mails'][0]
		
		# check some
		e['op'] = 'detail'
		e['mail_id'] = mail['mail_id']
		r = GET('general/mail.php', e)
		js = expect('success', r)
		mail = js['mails'][0]

		# acquire some items
		item = random.choice(mail['gifts_detail']['items'])
		e['op'] = 'acquire'
		e['acquire_id'] = item['item_id']
		r = GET('general/mail.php', e)
		js = expect('success', r)

		e['op'] = 'detail'
		e['mail_id'] = mail['mail_id']
		r = GET('general/mail.php', e)
		js = expect('success', r)
		mail2 = js['mails'][0]
		
		assert len(mail['gifts_detail']['items']) - 1 == len(mail2['gifts_detail']['items']), pretty(js)		
		assert len(mail2['gifts_detail']['acquireds']['items']) == 1, pretty(js)
		assert mail2['gifts_detail']['acquireds']['done'] == 0, pretty(js)
		
		# acquire all remainings
		e['op'] = 'acquire'
		e['acquire_id'] = 'all'
		r = GET('general/mail.php', e)
		js = expect('success', r)
		
		e['op'] = 'detail'
		e['mail_id'] = mail['mail_id']
		r = GET('general/mail.php', e)
		js = expect('success', r)
		mail = js['mails'][0]
		
		assert len(mail['gifts_detail']['items']) == 0, pretty(js)		
		assert len(mail['gifts_detail'].keys()) == 2, pretty(js)
		assert mail['gifts_detail']['acquireds']['done'] == 1, pretty(js)
		
	def test_invalid_params(self):		
		e = {}
		
		# invalid recv_name
		e['op'] = 'send'
		e['recv_name'] = 'no-such-receiver-name'
		e['title'] = 'test title ' + str(random.randint(0, 100))
		e['body'] = 'test message body at ' + str(time.time())
		r = GET('general/mail.php', e)
		js = expect('invalid:recv_name', r)
		
		# missing title or body
		e['op'] = 'send'
		e['recv_name'] = MailTest.user['username']
		e['title'] = ''
		e['body'] = 'test message body at ' + str(time.time())
		r = GET('general/mail.php', e)
		js = expect('invalid:title', r)

		e['op'] = 'send'
		e['recv_name'] = MailTest.user['username']
		e['title'] = 'test title ' + str(random.randint(0, 100))
		e['body'] = ''
		r = GET('general/mail.php', e)
		js = expect('invalid:body', r)		
		
		
if __name__ == "__main__":
	# import sys;sys.argv = ['', 'Test.testName']
	unittest.main()
