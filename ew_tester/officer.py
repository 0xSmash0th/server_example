'''
Created on 2013. 7. 2.

@author: hjyun
'''
from common import *
import unittest

def clear_and_hires(qty=1):
	e = {}
	
	# get all
	e['op'] = 'clear'
	r = GET('army/officer.php', e)
	js = expect('success', r)
	assert len(js['officers']) == 3, pretty(js)
	
	officers = []
	candidates = random.sample(js['officers'], qty)
	
	for candidate in candidates:
		oid = candidate['officer_id']
		e['officer_id'] = oid;
		e['op'] = 'hire'
		r = GET('army/officer.php', e)
		js = expect('success', r)
		
		assert len(js['officers']) == 1, pretty(js)
		assert js['officers'][0]['officer_id'] == oid, pretty(js)
		assert js['officers'][0]['status'] == 2, pretty(js)
		officers.append(js['officers'][0])
	
	if len(officers) == 1:
		return officers[0]
	else:
		return officers
		
class OfficerTest(unittest.TestCase):

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

	def clear_and_hires(self, qty=1):
		e = {}
		
		# get all
		e['op'] = 'clear'
		r = GET('army/officer.php', e)
		js = expect('success', r)
		assert len(js['officers']) == 3, pretty(js)
		
		officers = []
		candidates = random.sample(js['officers'], qty)
		
		for candidate in candidates:
			oid = candidate['officer_id']
			e['officer_id'] = oid;
			e['op'] = 'hire'
			r = GET('army/officer.php', e)
			js = expect('success', r)
			
			assert len(js['officers']) == 1, pretty(js)
			assert js['officers'][0]['officer_id'] == oid, pretty(js)
			assert js['officers'][0]['status'] == 2, pretty(js)
			officers.append(js['officers'][0])
		
		if len(officers) == 1:
			return officers[0]
		else:
			return officers
		
	def test_get_all_or_one(self):
		e = {}
		
		# get all
		e['op'] = 'clear,get'
		r = GET('army/officer.php', e)
		js = expect('success', r)
		assert len(js['officers']) == 3, pretty(js)
		
		oid = random.choice(js['officers'])['officer_id']
		e['officer_id'] = oid;
		e['op'] = 'get'
		r = GET('army/officer.php', e)
		js = expect('success', r)
		assert len(js['officers']) == 1, pretty(js)
		assert js['officers'][0]['officer_id'] == oid, pretty(js)

	def test_invalid(self):
		e = {}
		e['officer_id'] = 999993;
		r = GET('army/officer.php', e)
		js = expect('invalid:officer_id', r)
				
	def test_hires_and_reset(self):
		e = {}
		
		# get all
		e['op'] = 'clear'
		r = GET('army/officer.php', e)
		js = expect('success', r)
		assert len(js['officers']) == 3, pretty(js)
		
		oid = random.choice(js['officers'])['officer_id']
		e['officer_id'] = oid;
		e['op'] = 'hire'
		r = GET('army/officer.php', e)
		js = expect('success', r)
		
		assert len(js['officers']) == 1, pretty(js)
		assert js['officers'][0]['officer_id'] == oid, pretty(js)
		assert js['officers'][0]['status'] == 2, pretty(js)

		e = {}
		e['status'] = 1;  # unhired should be 5 always => 10.11 no more!
		r = GET('army/officer.php', e)
		js = expect('success', r)
		assert len(js['officers']) == 2, pretty(js)
		
		# do reset
		old_officers = js['officers']
		e = {}
		e['status'] = 1
		e['op'] = 'reset'
		r = GET('army/officer.php', e)
		js = expect('success', r)
		assert len(js['officers']) == 3, pretty(js)
		new_officers = js['officers']
		ooids = set([o['officer_id'] for o in old_officers])
		noids = set([o['officer_id'] for o in old_officers])
		assert ooids == noids
	
		# do fire
		e['officer_id'] = oid;
		e['op'] = 'fire'
		r = GET('army/officer.php', e)
		js = expect('fired', r)
	
		e['officer_id'] = oid;
		r = GET('army/officer.php', e)
		js = expect('assertion:violation', r)
				
	def test_reset(self):
		e = {}
		
		# get all
		e['op'] = 'clear'
		r = GET('army/officer.php', e)
		js = expect('success', r)
		assert len(js['officers']) == 3, pretty(js)
		
	def DEPRECATED_test_train_unit(self):
		officer = self.clear_and_hires()
		train_time = 1
		
		e = {}
		e['op'] = 'train_unit'
		e['train_time'] = train_time
		e['train_type'] = random.randint(1, 3)
		e['officer_id'] = officer['officer_id']
		
		r = GET('army/officer.php', e)
		js = expect('training unit', r)
		
		assert js['officers'][0]['status'] == 3, pretty(js)
		assert json.loads(js['officers'][0]['status_change_context']), pretty(js)

		logging.debug('waiting training time: ' + str(train_time))
		time.sleep(train_time)

		e = {}
		e['officer_id'] = officer['officer_id']
		r = GET('army/officer.php', e)
		js = expect('success', r)
		
		assert js['officers'][0]['status'] == 2, pretty(js)		
		assert js['officers'][0]['exp_cur'] > 0, pretty(js)

	def DEPRECATED_test_train_abiliy(self):
		officer = self.clear_and_hires()
		
		# run		
		e = {}
		e['op'] = 'train_ability_run'
		e['train_type'] = random.randint(1, 2)
		e['officer_id'] = officer['officer_id']
		
		r = GET('army/officer.php', e)
		js = expect('train_ability_run', r)

		assert json.loads(js['officers'][0]['status_change_context']), pretty(js)		
		assert len(js['officers'][0]['mods']) == 4, pretty(js)

		# discard
		e = {}
		e['officer_id'] = officer['officer_id']
		e['op'] = 'train_ability_discard'
		r = GET('army/officer.php', e)
		js = expect('train_ability_discard', r)
		
		assert js['officers'][0]['status_change_context'] == None, pretty(js)
		officer = js['officers'][0]
		
		# run again
		e = {}
		e['op'] = 'train_ability_run'
		e['train_type'] = random.randint(1, 2)
		e['officer_id'] = officer['officer_id']
		
		r = GET('army/officer.php', e)
		js = expect('train_ability_run', r)
		
		assert len(js['officers'][0]['mods']) == 4, pretty(js)
		assert json.loads(js['officers'][0]['status_change_context']), pretty(js)
		
		mods = js['officers'][0]['mods']
		
		# apply
		e = {}
		e['officer_id'] = officer['officer_id']
		e['op'] = 'train_ability_apply'
		r = GET('army/officer.php', e)
		js = expect('train_ability_apply', r)
		
		assert js['officers'][0]['status_change_context'] == None, pretty(js)
# 		for key in ['attack', 'defense', 'health', 'speed']:
# 			assert js['officers'][0][key] == (mods[key] + officer[key]), pretty(js)

	def test_heal(self):
		hired_officer = self.clear_and_hires()
		
		e = {}
		e['op'] = 'heal'
		e['officer_id'] = hired_officer['officer_id']
		e['heal_time'] = random.randint(1, 4)
		e['ignore'] = 1 # ignore building dep
		
		r = GET('army/officer.php', e)
		js = expect('officer:heal', r)
		
		assert js['officers'][0]['status'] == 4, pretty(js)
		officer = js['officers'][0]
		
		assert officer['status_change_context']['heal_time'], pretty(js)
		
		logging.debug('waiting healing time: ' + str(officer['status_change_context']['heal_time']))
		time.sleep(officer['status_change_context']['heal_time'])
		
		e = {}
		e['officer_id'] = hired_officer['officer_id']
		
		r = GET('army/officer.php', e)
		js = expect('success', r)
		assert js['officers'][0]['status'] == hired_officer['status'], pretty(js)

	def test_haste_for_heal(self):
		hired_officer = self.clear_and_hires()
		
		e = {}
		e['op'] = 'heal'
		e['heal_time'] = 100  # force long heal time
		e['officer_id'] = hired_officer['officer_id']
		e['ignore'] = 1 # ignore building dep
		
		r = GET('army/officer.php', e)
		js = expect('officer:heal', r)
		
		assert js['officers'][0]['status'] == 4, pretty(js)
		officer = js['officers'][0]
		
		assert officer['status_change_context']['heal_time'], pretty(js)

		logging.debug('we have heal_time: ' + str(officer['status_change_context']['heal_time']))
		
		e = {}
		e['op'] = 'haste'
		e['officer_id'] = hired_officer['officer_id']
		
		r = GET('army/officer.php', e)
		js = expect('officer:haste', r)
		
		assert js['officers'][0]['status'] == 2, pretty(js)
		
	def test_lead_and_unlead(self):
		hired_officer = self.clear_and_hires()

		e = {}
		e['op'] = 'lead'
		e['officer_id'] = hired_officer['officer_id']
		
		r = GET('army/officer.php', e)
		js = expect('officer:lead', r)
				
		e = {}
		r = GET('general/general.php', e)
		js = expect('success', r)
		
		assert 'leading_officer_id' in js['general'], pretty(js)
		assert js['general']['leading_officer_id'] == hired_officer['officer_id'], pretty(js)
		
		e = {}
		e['op'] = 'unlead'
		
		r = GET('army/officer.php', e)
		js = expect('officer:unlead', r)
		
		e = {}
		r = GET('general/general.php', e)
		js = expect('success', r)
		
		assert js['general']['leading_officer_id'] == None, pretty(js)

	def test_promote(self):
		# get all
		e = {}
		e['op'] = 'clear'
		e['officer_for_promote'] = 1
		r = GET('army/officer.php', e)
		js = expect('success', r)
		assert len(js['officers']) == 3, pretty(js)

		# hire all
		for candidate in js['officers']:
			oid = candidate['officer_id']
			e['officer_id'] = oid;
			e['op'] = 'hire'
			r = GET('army/officer.php', e)
			js = expect('success', r)
			
		e = {}
		e['op'] = 'promote'
		e['officer_id'] = js['officers'][0]['officer_id']		
		r = GET('army/officer.php', e)
		js = expect('not enough max_level', r)
		
		e['ignore'] = 1
		r = GET('army/officer.php', e)
		js = expect('officer:promote', r)
		
# 		assert len(js['officers']) == 1, pretty(js)

	def test_trains(self):
		hired_officer = self.clear_and_hires(1)

		e = {}
		e['op'] = 'train'
		e['officer_id'] = hired_officer['officer_id']
		
		for i in xrange(5):
			e['ability'] = random.randint(1, 4);
			e['special_train'] = 0;		
			r = GET('army/officer.php', e)
	 		js = expect('officer:train', r)
	 		assert 'new_rank' in js['officers'][0], pretty(js)
	 		assert 'old_rank' in js['officers'][0], pretty(js)
	
			e['ability'] = random.randint(1, 4);
			e['special_train'] = 1;
			r = GET('army/officer.php', e)
	 		js = expect('officer:train', r)
	 		assert 'new_rank' in js['officers'][0], pretty(js)
	 		assert 'old_rank' in js['officers'][0], pretty(js)

	def test_expand_hired_slot_max(self):
		e = {}
		e['op'] = 'get,clear'
		e = dict(e.items() + login_params.items())
		
		slot_max = OfficerTest.constants['OFFICER_HIRED_MAX']  # get this from server
		
		r = GET('general/general.php', e)
		js = expect('success', r)
		
		general = js['general']
		
		for i in xrange(general['officer_hired_max'], slot_max):
			e['op'] = 'expand_hired_slot_max'
			r = GET('army/officer.php', e)
			js = expect('success', r)
	
		r = GET('army/officer.php', e)
		js = expect('officer_hired_max at max', r)
		
	def DEPRECATED_test_haste_for_train_unit(self):
		hired_officer = self.clear_and_hires()
		train_time = 8
		
		e = {}
		e['op'] = 'train_unit'
		e['train_time'] = train_time
		e['train_type'] = random.randint(1, 3)
		e['officer_id'] = hired_officer['officer_id']
		
		r = GET('army/officer.php', e)
		js = expect('training unit', r)
		
		assert js['officers'][0]['status'] == 3, pretty(js)
		ejs = json.loads(js['officers'][0]['status_change_context'])
		assert ejs, pretty(js)

		logging.debug('we have training time: ' + str(train_time))

		e = {}
		e['op'] = 'haste'
		e['officer_id'] = hired_officer['officer_id']
		
		r = GET('army/officer.php', e)
		js = expect('officer:haste', r)
		
		assert js['officers'][0]['status'] == 2, pretty(js)
		assert js['officers'][0]['exp_cur'] > 0, pretty(js)	

	def DEPRECATED_test_cancel_for_train_unit(self):
		hired_officer = self.clear_and_hires()
		train_time = 8
		
		e = {}
		e['op'] = 'train_unit'
		e['train_time'] = train_time
		e['train_type'] = random.randint(1, 3)
		e['officer_id'] = hired_officer['officer_id']
		
		r = GET('army/officer.php', e)
		js = expect('training unit', r)
		
		assert js['officers'][0]['status'] == 3, pretty(js)
		ejs = json.loads(js['officers'][0]['status_change_context'])
		assert ejs, pretty(js)

		logging.debug('we have training time: ' + str(train_time))

		e = {}
		e['op'] = 'train_unit_cancel'
		e['officer_id'] = hired_officer['officer_id']
		
		r = GET('army/officer.php', e)
		js = expect('officer:train_unit_cancel', r)
		
		assert js['officers'][0]['status'] == 2, pretty(js)
		
	def test_arms(self):
		hired_officer = self.clear_and_hires()
		
		import item
		items = item.prepare_items(3, True)
		
		#print 'items:', items
		num = max([len(items), len(items) / 2])
		armed = []
		for i in xrange(num):
			item = random.choice(items)
			e = {}
			e['op'] = 'item_arm'
			e['item_id'] = item['item_id']
			e['officer_id'] = hired_officer['officer_id'] 
			
			r = GET('army/officer.php', e)
			js = expect('item_arm', r)
			
	# 		ejs = json.loads(js['officers'][0]['equipments'])
			assert js, pretty(js)
			assert len(js['officers'][0]['equipments']) == i + 1, pretty(js)
			assert item['item_id'] in js['officers'][0]['equipments'], pretty(js)
			
			armed.append(item)
			items.remove(item)

		to_disarm = random.choice(armed) 
		e = {}
		e['op'] = 'item_disarm'
		e['officer_id'] = hired_officer['officer_id']
		e['item_id'] = to_disarm['item_id']
		
		r = GET('army/officer.php', e)
		js = expect('item_disarm', r)
		
		assert js, pretty(js)
		assert str(to_disarm['item_id']) not in js['officers'][0]['equipments'], pretty(js)
			
if __name__ == "__main__":
	# import sys;sys.argv = ['', 'Test.testName']
	unittest.main()
