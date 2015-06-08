'''
Created on 2013. 7. 2.

@author: hjyun
'''

import requests, random, json, time, logging, socket, os

LOGFORMAT = '%(asctime)-15s %(message)s'

base = 'http://localhost/ew_tb/'
# base = 'http://ew_tb_was_1p:20080/ew_tb_dev/'

if 'nt' not in os.name:
	base = 'http://ew_tb_was_1p:20080/ew_tb_dev/'
else:
 	logging.basicConfig(level=logging.DEBUG, format=LOGFORMAT)

login_params = {'gold': 100000, 'honor': 3000, 'star': 3000, 'activity': 500, 'username':'test-user', 'password':'1'}

param_type = 'json'
param_type = 'post'
param_type = 'get'

s = requests.Session()

def logged_in():
	global s
	if s.cookies.get('PHPSESSID') or s.cookies.get('PHPREDIS_SESSION'):
		return True
	return False 
	
def GET(uri, context={}):
	log = logging.getLogger('GET')
	
# 	ts = str(time.time()).replace('.', '_')
	ts = str(random.random()).replace('.', '_')[-4:]
	if '?' in uri:
		url = base + uri + '&ts=' + ts
	else:
		url = base + uri + '?ts=' + ts
	
	global param_type
	global s
	if 's' in context:
		session = context['s']
	else:
		session = s

	assert '?op=' not in url, url
	assert '&op=' not in url, url
	
	new_context = {}
	for k, v in context.iteritems():
		if k == 's': continue
		
		tv = str(type(v))
		if 'dict' in tv or'list' in tv:
			new_context[k] = json.dumps(v)
		else:
			new_context[k] = str(v)
	context = new_context
				
	if param_type == 'get':
		for k, v in context.iteritems():
			url += '&' + str(k) + '=' + str(v)
	
		log.debug('REQUEST URL: ' + url)
# 		print 'REQUEST URL: ' + url		
		req = session.get(url)
		
	elif param_type == 'post':
		log.debug('REQUEST URL: ' + url)
		req = session.post(url, context)
		
	elif param_type == 'json':				
		body = json.dumps(context)
	
		log.debug('REQUEST URL: ' + url)
		req = session.post(url, body)
		
	return req

def expect(substring, response, code=None):
	log = logging.getLogger('expect')
	assert response.status_code == 200, 'response.status_code: ' + repr(response.status_code)
	try:
		js = json.loads(response.text)
	except Exception as e:
# 		print 'response.text:', response.text
		log.error('response.text: ' + str(response.text))
		raise e
	
	if code and len(code) > 0:
		assert code in js['code'], pretty(js)
	elif substring and len(substring) > 0: 
# 		print 'EXPECTS SUBSTR: ' + substring + '...',
		log.debug('EXPECTS SUBSTR: ' + substring + '...')
		assert substring in js['message'], pretty(js)
# 		print 'DONE'
	return js

def pretty(jsonobj):
	return json.dumps(jsonobj, indent=2)

import bisect
def weighted_choice(choices):
    values, weights = zip(*choices)
    total = 0.0
    cum_weights = []
    for w in weights:
        total += float(w)
        cum_weights.append(total)
    x = random.random() * total
    i = bisect.bisect(cum_weights, x)
    return values[i]

