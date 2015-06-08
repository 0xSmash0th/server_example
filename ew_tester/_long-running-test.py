
import os

if __name__ == "__main__":
	output = 'test-output.log'
	os.system('rm -f ' + output)
	
	while 1:
		if os.system('python -m unittest discover -p "*.py" | tee -a ' + output) != 0:
			break
		
	input('failed: press any key to continue')
