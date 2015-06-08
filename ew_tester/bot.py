'''
Created on 2013. 7. 19.

@author: hjyun
'''
import unittest

from common import *  # @UnusedWildImport
import datetime
import logging
import os
from test.test_coercion import candidates

class BotTest:
	user = None
	general = None
	username = 'user_' + str(time.time()) + str(random.randint(1000, 9999))
	password = 'pwd1234'
	session = None
	numops = 100
	botid = 'bot_' + str(time.time()) + str(random.randint(1000, 9999))
	
	bids = [1000,1001,1002,1003,1004,1005,1006,1007,1008,1030,1031,1032,1033,1034,1035,1036,1037,1038,1009,1010,1060,1061,1062,1063,1064,1065,1066]
	bids_allies = [1000,1001,1002,1003,1004,1005,1006,1007,1008]
	bids_empire = [1030,1031,1032,1033,1034,1035,1036,1037,1038]
	bids_common = [1009,1010,1060,1061,1062,1063,1064,1065,1066]
	bids_hq = [1000,1001,1002,1003,1004,1005,1006,1030,1031,1032,1033,1034,1035,1036]
	bids_unlimited = [1060,1062]
	
	def e(self):
		return {'s':self.session}

	def user_register_or_login(self):
		self.session = requests.Session()

		e = {'username': self.username, 'password': self.password}
		e['s'] = self.session
		r = GET('auth/register.php', e)
		js = expect(None, r)
		if 'already exists' not in js['message']:
			r = GET('auth/logout.php', e)
			js = expect(None, r)

		r = GET('auth/login.php', e)
		js = expect(None, r)
		
		if 'user' not in js:
			self.log.error('js: ' + str(js))
	
		self.user = js['user']
	
		r = GET('general/general.php', e)
		js = expect(None, r)
		self.general = js['general']

		self.log.info('logged in : ' + self.username)

	def user_logout(self):
		self.log.info('logged out: ' + self.username)
		if self.user:
			r = GET('auth/logout.php', {'s':self.session})
		self.session = requests.Session()

	def general_get(self):
		pass

	def general_edit(self):
		e = self.e()
		r = GET('general/general.php', e)
		js = expect(None, r, 'ok')
		self.general = js['general']
		
		oldname = self.general['name']
		e['name'] = self.general['name'][:-3] + str(random.randint(100, 999))
		while oldname == e['name']:
			e['name'] = self.general['name'][:-3] + str(random.randint(100, 999))
			
# 		e['picture'] = self.general['picture'][:-3] + str(random.randint(100,999))
		# e['region_id'] = random.randint(1, 10)
		
		e['op'] = 'edit'
		r = GET('general/general.php', e)
		js = expect(None, r, 'ok')
		
	
	def general_collect_tax(self):
		e = self.e()
		e['building_id'] = random.randint(1, 20)
		e['position'] = 10
		e['op'] = 'get'
				
		r = GET('general/general.php', e)
		js = expect('success', r)

		logging.debug('waiting tax could be collectable...')
# 		time.sleep(1) # initially one time is collectable
		
		# logging.warning('tax_collectable_count: ' + str(js['general']['tax_collectable_count']))
		if js['general']['refresh_tax_timer_after'] <= 1:
			time.sleep(1.1)
			r = GET('general/general.php', e)
			js = expect('success', r)

		if js['general']['tax_collectable_count'] > 0:
			e['op'] = 'collect_tax'
			r = GET('general/general.php', e)
			js = expect('collect_tax', r)
			# js = expect(None, r)
		else:
			e['op'] = 'extra_collect_tax'
			r = GET('general/general.php', e)
			js = expect('extra_collect_tax', r) 
			# js = expect(None, r) # suppress [not collectable, tax_collectable_count > 0]
		
# 		assert 0 <= js['general']['tax_collectable_count'] <= 4, pretty(js)

	def general_collect_extra_tax(self):
		assert False, 'Not Reached, general_collect_tax implements this'
	
	def building_get(self):
		e = self.e()
		e['op'] = 'get'
		
		r = GET('build/construction.php', e)
		js = expect('success', r)
	
	def building_build(self):
		e = self.e()
		e['op'] = 'get'
		
		r = GET('build/construction.php', e)
		js = expect('success', r)
		
		built_pos = set()
		built_ids_limited_candidates = set(self.bids_allies + self.bids_common) - set(self.bids_unlimited)
		for v in js['constructions']:
			built_pos.add(v['position'])
			if v['building_id'] in built_ids_limited_candidates:
				built_ids_limited_candidates.remove(v['building_id'])
			
		empty_pos = set(xrange(1, 31)) - built_pos
		empty_pos = [e for e in empty_pos]
		if len(empty_pos) == 0:
			return
		
		if len(built_ids_limited_candidates) > 0:
			bid = random.choice([e for e in built_ids_limited_candidates])
		else:
			bid = random.choice(self.bids_unlimited)
			
		e = self.e()
		e['building_id'] = bid
		e['position'] = random.choice(empty_pos)
		e['op'] = 'build'
		
		r = GET('build/construction.php', e)
		js = expect(None, r)
				
	def building_upgrade(self):
		e = self.e()
		e['op'] = 'get'
		
		r = GET('build/construction.php', e)
		js = expect('success', r)
		
		candidates = []		
		for v in js['constructions']:
			max_level = 10
			if v['building_id'] in self.bids_hq:
				max_level = 20
			if v['cur_level'] < max_level:
				candidates.append(v)

		if len(candidates) == 0:
			return 
						
		e['construction_id'] = random.choice(candidates)['construction_id']
		e['op'] = 'upgrade'
		
		r = GET('build/construction.php', e)
		js = expect(None, r)  # do not check success

	def building_remove(self):
		e = self.e()
		e['op'] = 'get'
		
		r = GET('build/construction.php', e)
		js = expect('success', r)
		
		candidates = []		
		for v in js['constructions']:
			if v['building_id'] not in self.bids_hq:
				candidates.append(v)	

		if len(candidates) == 0:
			return 
							
		e['construction_id'] = random.choice(candidates)['construction_id']
		e['op'] = 'remove'
		
		r = GET('build/construction.php', e)
		js = expect(None, r)  # dont check HQ remove error
		
		
	def officer_list(self):
		e = self.e()
		r = GET('army/officer.php', e)
		js = expect(None, r, 'ok')

	def officer_list_reset(self):
		e = self.e()
		e['op'] = 'reset'
		r = GET('army/officer.php', e)
		js = expect(None, r, 'ok')
	
	def item_make(self):
		e = self.e()
		e['op'] = 'make'			
		e['item_type_major'] = random.randint(1, 3)
		e['item_type_minor'] = random.randint(1, 100)
		r = GET('army/item.php', e)
		js = expect('make', r, 'ok')
		
	def bot_test(self):
		self.username = self.botid
		
		logfn = 'outputs/output_' + self.botid + '.txt'
		try:
			# os.system('rm -rf outputs')
			os.mkdir('outputs')
		except:
			pass

		if 'nt' in os.name:
			logging.getLogger().setLevel(level=logging.INFO)
		else:
			logging.basicConfig(filename=logfn, filemode='w+', level=logging.ERROR)
			
		self.log = logging.getLogger('bot')
				
		optimes = {}
		tries = []
		op = None
		try:
			ops = [
				('general_edit', 1),
				('officer_list', 30),
				('officer_list_reset', 1), 				
				('item_make', 10),
				('building_get', 30),
				('building_build', 10),
				('building_upgrade', 10),
				('building_remove', 1),
				('general_collect_tax', 5),
				]
			
			for i in xrange(self.numops):
				op = weighted_choice(ops)

				if not self.user:
					op = 'user_register_or_login'

				success = False
				runs = 0
				tb = time.time()
				while not success:
					try:
						self.log.debug('op[%04d]: %s' % (i, op))
						
						runs += 1
						eval('self.' + op + '()')


						success = True
					except Exception as ee:
						stree = repr(ee)
						if 'response.status_code: 500' in stree or 'ConnectionError' in stree:
							self.log.debug('retrying op[%04d]: %s' % (i, op))
							continue

						raise ee

				te = time.time()

				tries.append(runs)
				
				if op not in optimes:
					optimes[op] = {'ops':[]}			
				optimes[op]['ops'].append(te - tb)

				self.log.info('op[%04d][%3d][%010.6fs]: %s' % (i, runs, te - tb, op))

		except Exception as e:
			self.log.error(repr(e))
			self.log.error('op: ' + repr(op))
					
		self.user_logout()

		summary = {'sum': sum(tries), 'avg': 0.0}
		if len(tries) > 0:
			summary['avg'] = summary['sum'] / float(len(tries))
		
		total_sum = 0.0
		total_num = 0
		for k, v in optimes.iteritems():
			if len(v['ops']) > 0:
				v['sum'] = sum(v['ops'])
				v['num'] = len(v['ops'])
				v['avg'] = v['sum'] / float(v['num'])
				
				total_sum += v['sum']
				total_num += v['num']
			del v['ops']
			
		summary['optimes'] = optimes
		summary['total_sum'] = total_sum
		summary['total_num'] = total_num
		summary['total_avg'] = 0.0
		if total_num > 0:
			summary['total_avg'] = total_sum / total_num

		self.log.info('summary: ' + pretty(summary))

		# return 'ok'
		return summary


	@classmethod
	def run(cls):
		BotTest().bot_test()

if __name__ == "__main__":
	BotTest().bot_test()


