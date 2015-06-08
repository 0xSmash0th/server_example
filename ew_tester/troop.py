'''
Created on 2013. 7. 2.

@author: hjyun
'''
import unittest

from common import *
import time

class TroopTest(unittest.TestCase):

	user = None
	username = 'test-user'
	password = 'pwd1234'
	registered = False

	train_qty = 50
	units_allies = ["1","2","3","4","5","6","7","8","9","10","11","12","13","14","15","16","17","18","19","20","21","22","23","24","25"]
	units_empire = ["26","27","28","29","30","31","32","33","34","35","36","37","38","39","40","41","42","43","44","45","46","47","48","49","50","51"]
	unit_ids = {"humans":["1","2","3","4","5","6","7","8","9","10","11","12","13","14","26","27","28","29","30","31","32","33","34","35","36","37","38","39"],
			"tanks":["15","16","17","18","19","20","21","22","23","24","25","40","41","42","43","44","45","46","47","48","49","50","51"]}
	
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
			
	def test_get(self):
		e = {}
		e['op'] = 'clear,get'
		r = GET('army/troop.php', e)
		js = expect('success', r)
		assert len(js['troops']) >= 0, pretty(js)
		
	def test_train_list(self):
		e = {}
		e['op'] = 'train_list'
		r = GET('army/troop.php', e)
		js = expect('success', r)
		assert len(js['units']) > 0, pretty(js)
		for unit in js['units']:
			assert unit['type_minor'] > 0, pretty(js)
			assert 0 <= unit['valid'] <= 1, pretty(js)
	
	def pick_major_minor(self):
		major = random.randint(1, 2);		 
		if (major == 1):
			pool = self.unit_ids['humans']
		else:
			pool = self.unit_ids['tanks']
		minors = list(set(pool) - set(self.units_empire))
		minor = random.choice(minors)			

		return [major, minor]
	
	def test_trainings(self):
		
		total_qty = 0
		total_trains = 2
		for i in xrange(total_trains):			
			e = {}
			mm = self.pick_major_minor()
			e['type_major'] = mm[0]
			e['type_minor'] = mm[1]
			e['qty'] = random.randint(10, 100)
			e['training_time'] = random.randint(1, 2)  # supply short training times
			e['ignore_building'] = 1
			e['ignore'] = 1
			e['op'] = 'train'
			if i == 0:
				e['op'] += ',clear'
			
			r = GET('army/troop.php', e)
			js = expect('train started', r)
			assert len(js['troops']) == 1, pretty(js)
			assert js['troops'][0]['training_time'] >= 0, pretty(js)
			
# 			print js
			logging.debug('waiting training_time: ' + str(js['troops'][0]['training_time']) + ' seconds')
			time.sleep(js['troops'][0]['training_time'])
			
			total_qty += e['qty']

		trained_qty = 0
		e = {}
		r = GET('army/troop.php', e)
		js = expect('success', r)
		for troop in js['troops']:
			if troop['status'] == 2:
				trained_qty += troop['qty']
		assert trained_qty == total_qty, pretty(js)

		# do not allow simultaneous type_minor
		e = {}
		mm = self.pick_major_minor()
		e['type_major'] = mm[0]
		e['type_minor'] = mm[1]
		e['qty'] = random.randint(10, 100)
		e['training_time'] = 5
		e['ignore_building'] = 1
		e['ignore'] = 1
		e['op'] = 'train,clear'
		
		r = GET('army/troop.php', e)
		js = expect('train started', r)
		assert len(js['troops']) == 1, pretty(js)
		assert js['troops'][0]['training_time'] >= 0, pretty(js)
		
		e['op'] = 'train'
		r = GET('army/troop.php', e)
		js = expect('already training', r)
				
	def test_training_population_limit(self):
		
		e = {}
		e['op'] = 'clear,gift'
		e['acl'] = 'operator'
		e['gift_all_units'] = TroopTest.constants['TROOP_POPULATION_LIMIT']
		r = GET('army/troop.php', e)
		js = expect('success', r)
		assert len(js['troops']) > 0, pretty(js)
		
		total_trains = 1
		for i in xrange(total_trains):			
			e = {}
			mm = self.pick_major_minor()
			e['type_major'] = mm[0]
			e['type_minor'] = mm[1]
			e['qty'] = random.randint(10, 100)
			e['training_time'] = random.randint(1, 2)  # supply short training times
			e['ignore_building'] = 1
			e['ignore'] = 1
			e['op'] = 'train'
			
			r = GET('army/troop.php', e)
			js = expect('troop population reached at limit', r)
		
	def test_train_haste(self):
		e = {}
		e['op'] = 'clear,get'
		e['ignore_check_star'] = 1
		e['ignore'] = 1
		r = GET('army/troop.php', e)
		js = expect('success', r)
		assert len(js['troops']) >= 0, pretty(js)
		
		# # make trained troops
		training_time = 200
		e = {}
		e['ignore_check_star'] = 1
		e['ignore'] = 1
		mm = self.pick_major_minor()
		e['type_major'] = mm[0]
		e['type_minor'] = mm[1]
		e['qty'] = self.train_qty
		e['training_time'] = training_time  # supply long training time
		e['ignore_building'] = 1
		e['ignore'] = 1
		e['op'] = 'train'
			
		r = GET('army/troop.php', e)
		js = expect('train started', r)
		assert len(js['troops']) == 1, pretty(js)
		troop = js['troops'][0]
		assert troop['training_time'] == training_time, pretty(js)
		
		e = {}
		e['ignore_check_star'] = 1
		e['ignore'] = 1
		e['troop_id'] = troop['troop_id']
		e['op'] = 'train_haste'	

		r = GET('army/troop.php', e)
		js = expect('train_haste', r)
		assert js['troops'][0]['status'] == 2, pretty(js)
			
	def prepare_band(self):
		e = {}
		e['op'] = 'clear,get'
		r = GET('army/troop.php', e)
		js = expect('success', r)
		assert len(js['troops']) >= 0, pretty(js)
		
		# # make trained troops
		e = {}
		mm = self.pick_major_minor()
		e['type_major'] = mm[0]
		e['type_minor'] = mm[1]
		e['training_time'] = random.randint(1, 2)  # supply short training times
		e['ignore_building'] = 1
		e['ignore'] = 1
		e['ignore_command'] = 1
		e['qty'] = self.train_qty
		e['op'] = 'train'
			
		r = GET('army/troop.php', e)
		js = expect('train started', r)
		assert len(js['troops']) == 1, pretty(js)
		troop = js['troops'][0]	
		
		# # make officer and hire
		e = {}
		e['op'] = 'clear'
		r = GET('army/officer.php', e)
		js = expect('success', r)
		assert len(js['officers']) == 3, pretty(js)
		
		officer = js['officers'][0]
		e = {}
		e['op'] = 'hire'
		e['officer_id'] = officer['officer_id']
		r = GET('army/officer.php', e)
		js = expect('success', r)

		return [troop, officer]
		
	def test_banding_entire(self):
		[troop, officer] = self.prepare_band()

		logging.debug('waiting training_time: ' + str(troop['training_time']) + ' seconds')
		time.sleep(troop['training_time'])
						
		# # band entire
		e = {}
		e['bands'] = [{
					'qty': int(troop['qty'] * 0.5),
					'officer_id': officer['officer_id'],
					'slot': 1,
					'troop_id': troop['troop_id'],
					}]
		e['op'] = 'band'
		
		r = GET('army/troop.php', e)
		js = expect('banded', r)
		assert js['troops'][0]['slot'] == 1, pretty(js)
		banded_troop = js['troops'][0]
		
		# get troops by officer_id
		e = {}
		e['op'] = 'get'
		e['officer_id'] = officer['officer_id']
		
		r = GET('army/troop.php', e)
		js = expect('success', r)
		assert len(js['troops']) >= 0, pretty(js)
				
		# # disband
		e = {}
		e['troop_id'] = banded_troop['troop_id']
		e['op'] = 'disband'
		
		r = GET('army/troop.php', e)
		js = expect('disbanded', r)
		
		r = GET('army/troop.php')
		js = expect('success', r)
		assert js['troops'][0]['status'] == 2, pretty(js)
	
	def test_banding_partial(self):
		[troop, officer] = self.prepare_band()
		
		logging.debug('waiting training_time: ' + str(troop['training_time']) + ' seconds') 
		time.sleep(troop['training_time'])
		
		partial_bandings = 4
		for i in xrange(partial_bandings):
			# # band partial
			e = {}
			e['bands'] = json.dumps([{
			'qty': random.randint(troop['qty'] * 0.2, troop['qty'] * 0.4),
			'officer_id': officer['officer_id'],
			'slot': 1,
			'troop_id': troop['troop_id'],
			}])

			e['op'] = 'band'
			
			r = GET('army/troop.php', e)
			js = expect('banded', r)
			assert js['troops'][0]['slot'] == 1, pretty(js)
			banded_troop = js['troops'][0]
			
			r = GET('army/troop.php', {})
			js = expect('success', r)
			assert len(js['troops']) == 2, pretty(js)
			
			# # disband
			e = {}
			e['troop_id'] = banded_troop['troop_id']
			e['op'] = 'disband'
			
			r = GET('army/troop.php', e)
			js = expect('disbanded', r)
			
			r = GET('army/troop.php')
			js = expect('success', r)
			assert len(js['troops']) == 1, pretty(js)
			assert js['troops'][0]['status'] == 2, pretty(js)

	def test_banding_partial_with_fullapi(self):
		[troop, officer] = self.prepare_band()
		
		logging.debug('waiting training_time: ' + str(troop['training_time']) + ' seconds') 
		time.sleep(troop['training_time'])
				
		partial_bandings = 4
		for i in xrange(partial_bandings):
			# split qty into some			 
			qtys = []
			for j in xrange(5):
				qtys.append(random.randint(1, self.train_qty / 5))
			if random.random() < 0.5:
				qtys.append(self.train_qty - sum(qtys))

			bands = []
			for qty in qtys:
				d = {'qty': qty, 'officer_id': officer['officer_id'], 'slot': len(bands) + 1,
					'type_major': troop['type_major'], 'type_minor': troop['type_minor']}
				bands.append(d)
			# # band partial
			e = {}
			e['bands'] = json.dumps(bands)
			e['op'] = 'band_full'
			e['ignore_command'] = 1
			
			r = GET('army/troop.php', e)
			js = expect('banded', r)
			assert len(js['troops']) == len(qtys), pretty(js)
			
	def test_banding_excepts(self):
		[troop, officer] = self.prepare_band()

		logging.debug('waiting training_time: ' + str(troop['training_time']) + ' seconds')
		time.sleep(troop['training_time'])
						
		# # band half
		e = {}
		bands = [{
					'qty': troop['qty'] / 2,
					'officer_id': officer['officer_id'],
					'slot': 1,
					'troop_id': troop['troop_id'],
					}]
		e['bands'] = json.dumps(bands)
		e['op'] = 'band'
		
		r = GET('army/troop.php', e)
		js = expect('banded', r)
		assert js['troops'][0]['slot'] == 1, pretty(js)
		banded_troop = js['troops'][0]
		
		# # band duplicated slot
		r = GET('army/troop.php', e)
		js = expect('invalid:slot:duplicated', r)

		# # band more than qty
		bands[0]['qty'] += 1
		e['bands'] = json.dumps(bands)
		r = GET('army/troop.php', e)
		js = expect('invalid:source:troop', r)
		
		# fire officer and check that disband was correctly done
		e = {}
		e['op'] = 'fire'
		e['officer_id'] = officer['officer_id']
		r = GET('army/officer.php', e)
		js = expect('officer:fired', r)
		
		# afterward, check troop.officer was back again
		e = {}
		e['op'] = 'get'
		r = GET('army/troop.php', e)
		js = expect('success', r)
		assert len(js['troops']) == 1, pretty(js)
		assert js['troops'][0]['qty'] == troop['qty'], pretty(js)
				
	def test_banding_auto(self):
		import officer
		officer = officer.clear_and_hires()
		
		e = {}
		e['op'] = 'clear,gift'
		e['acl'] = 'operator'
		e['gift_all_units'] = 1
		r = GET('army/troop.php', e)
		js = expect('success', r)
		assert len(js['troops']) > 0, pretty(js)

		# run auto band, should fail by lackage of troops
		e = {}
		e['op'] = 'band_auto'
		e['officer_id'] = officer['officer_id']		
		r = GET('army/troop.php', e)
		js = expect('not enough candidate troops commands', r)

		e = {}
		e['op'] = 'gift'
		e['acl'] = 'operator'
		e['gift_all_units'] = 10
		r = GET('army/troop.php', e)
		js = expect('success', r)
		assert len(js['troops']) > 0, pretty(js)

		e['op'] = 'band_auto'
		e['officer_id'] = officer['officer_id']		
		r = GET('army/troop.php', e)
		js = expect('banded', r)
								
if __name__ == "__main__":
	# import sys;sys.argv = ['', 'Test.testName']
	unittest.main()
