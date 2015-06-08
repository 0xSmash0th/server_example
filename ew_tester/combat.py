'''
Created on 2013. 7. 2.

@author: hjyun
'''
import unittest

from common import *

class CombatTest(unittest.TestCase):
	user = None
	username = 'test-user'
	password = 'pwd1234'
	registered = False

	# tiles_allies, tiles_empire are not valid since battlefield building (as of 10.08)	
	tiles_dforts = ['H01', 'H03', 'I05', 'K06', 'A09', 'C10', 'D11', 'D13']
	tiles_forts = ["B04", "C08", "E04", "F01", "F07", "F13", "G11", "I07", "J10"]
	tiles_normal = ["A10", "A11", "A12", "B03", "B05", "B06", "B07", "B08", "B10", "B13", "C03",
				"C05", "C12", "C14", "D02", "D05", "D07", "D09", "E02", "E06", "E07", "E08", "E10",
				"E11", "E13", "F03", "F05", "F06", "F08", "F09", "F11", "G02", "G04", "G05", "G07",
				"G08", "G09", "G13", "H05", "H07", "H09", "H12", "I01", "I03", "I10", "I12", "J01",
				"J04", "J06", "J07", "J08", "J09", "J11", "K03", "K04", "K05"]
	tiles_allies = ["B03", "C03", "D02", "E02", "F03", "F05", "F06", "G02", "G04", "G05", "G07", "G08", "G09", "H05", "H07", "H09",
				"I01", "I03", "I10", "J01", "J04", "J06", "J07", "J08", "J09", "K03", "K04", "K05"]
	tiles_empire = ["A10", "A11", "A12", "B05", "B06", "B07", "B08", "B10", "B13", "C05", "C12", "C14", "D05", "D07", "D09", "E06",
				"E07", "E08", "E10", "E11", "E13", "F08", "F09", "F11", "G13", "H12", "I12", "J11"]
	
	TILE_SAFEZONE_ALLIES = ['I01', 'I03', 'J01', 'J04', 'K03', 'K04', 'K05', 'H01', 'H03', 'I05', 'K06', 'J02']
	TILE_SAFEZONE_EMPIRE = ['A10', 'A11', 'A12', 'B10', 'B13', 'C12', 'C14', 'A09', 'C10', 'D11', 'D13', 'B12']

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

	def prepare_band(self):
		e = {}
		e['op'] = 'clear,get'
		r = GET('army/troop.php', e)
		js = expect('success', r)
		assert len(js['troops']) >= 0, pretty(js)
		
		# # make trained troops
		e = {}
		e['type_major'] = random.randint(1, 3)
		e['type_minor'] = random.randint(1, 10)
		e['training_time'] = random.randint(1, 2)  # supply short training times
		e['ignore_building'] = 1
		e['ignore_command'] = 1
		e['qty'] = 20
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
		assert len(js['officers']) == CombatTest.constants['OFFICER_UNHIRED_MIN'], pretty(js)
		
		officer = js['officers'][0]
		e = {}
		e['op'] = 'hire'
		e['officer_id'] = officer['officer_id']
		r = GET('army/officer.php', e)
		js = expect('success', r)

		return [troop, officer]
	
	def prepare_officer_and_troops(self):
		[troop, officer] = self.prepare_band()

		logging.debug('waiting training_time: ' + str(troop['training_time']) + ' seconds')
		time.sleep(troop['training_time'])
						
		# # band entire
		e = {}
		e['bands'] = [{
					'qty': troop['qty'],
					'officer_id': officer['officer_id'],
					'slot': 1,
					'troop_id': troop['troop_id'],
					}]
		e['op'] = 'band'
		
		r = GET('army/troop.php', e)
		js = expect('banded', r)
		assert js['troops'][0]['slot'] == 1, pretty(js)
		banded_troop = js['troops'][0]
		
		return [officer, banded_troop] 
	
	def test_combat(self):
		querys = '''
			delete from combat where general_id = %s AND status = 1;
			update general set running_combat_id = NULL where general_id = %s;
			''' % (CombatTest.general['general_id'], CombatTest.general['general_id'])
		
		e = {}
		e['op'] = 'run'
		e['querys'] = querys	
		r = GET('admin/god_api.php', e)
		js = expect(None, r)
		
		[officer, troop] = self.prepare_officer_and_troops()

		# set leading_officer_id
		e = {}
		e['op'] = 'lead'
		e['officer_id'] = officer['officer_id']
		
		r = GET('army/officer.php', e)
		js = expect('officer:lead', r)

		# get tiles (this is for pvp)
		e = {}
		r = GET('battlefield/tile.php', e)
		js = expect('success', r)
		dispute_positions = []
		occupy_allies = []
		occupy_empire = []
		for tile in js['tiles']:
			if tile['occupy_force'] == 1 and tile['connected'] == 1 and tile['position'] not in self.tiles_dforts:
				occupy_allies.append(tile['position'])
			if tile['occupy_force'] == 2 and tile['connected'] == 2 and tile['position'] not in self.tiles_dforts:
				occupy_empire.append(tile['position'])
			if tile['dispute'] > 0:
				dispute_positions.append(tile['position']) 
# 		print dispute_positions
		
		# begin combat
		e = {}
		e['op'] = 'clear,begin'
		e['tile_position'] = random.choice(occupy_allies)
						
		r = GET('battlefield/combat.php', e)
		js = expect('success', r)
		
		assert len(js['combats']) == 1, pretty(js)
		combat = js['combats'][0]

		# will fail, only one combat is possible 
		e['op'] = 'begin'
						
		r = GET('battlefield/combat.php', e)
		js = expect('previous running combat was found', r)
		
		assert js['combats'][0]['combat_id'] == combat['combat_id'], pretty(js)
		
		#
		# run combat
		#
		officer = combat['brief']['ourforce']['officer']
		troops = combat['brief']['ourforce']['troops']
		
		new_troops = []		
		for t in troops:
			if random.random() < 0.3:
				t['new_qty'] = 0
			else:
				t['new_qty'] = int(random.random() * t['qty']) 
			new_troops.append(t)
		
		result = {'ourforce': {}}
		result['ourforce']['officer'] = officer
		result['ourforce']['troops'] = new_troops

		# opponent
		officer = combat['brief']['opponent']['officer']
		troops = combat['brief']['opponent']['troops']
		
		new_troops = []		
		for t in troops:
			t['new_qty'] = 0 
			new_troops.append(t)
		
		result['opponent'] = {}
		result['opponent']['officer'] = officer
		result['opponent']['troops'] = new_troops
				
		# submit result
		e['op'] = 'submit'
		e['combat_id'] = combat['combat_id']
		e['result'] = json.dumps(result)

		r = GET('battlefield/combat.php', e)
		js = expect('success', r)
				
if __name__ == "__main__":
	# import sys;sys.argv = ['', 'Test.testName']
	unittest.main()
