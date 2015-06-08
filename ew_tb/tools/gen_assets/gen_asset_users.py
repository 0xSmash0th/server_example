import random
print 'DELETE FROM user;'
print 'DELETE FROM general;'

print '''
INSERT INTO user (user_id, username, password, created_at, login_at) VALUES 
	(1, 'test', '1234', NOW(), NOW());
INSERT INTO general (country, name, user_id) VALUES ('1', 'test', 1);
'''
