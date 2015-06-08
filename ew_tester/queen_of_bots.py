#!/usr/bin/env python
# encoding: utf-8

import sys
import os, time

use_celery = False
use_celery = True

try:
	import celery.task
	
	@celery.task(name='bottest_run', serializer='json')
	def bottest_run(seq):
		import random
		seq = random.randint(1, 99999)
		from bot import BotTest
		bot = BotTest()
		bot.numops = 100
		bot.botid = 'bot_%05d' % seq
		return bot.bot_test()
	
	def main_celery():
		from celery import group
		from celery import subtask
		from celery import Task
		from celery import Celery
		from celery.app import control
		
		njobs = 10
		if 'nt' in os.name:
			njobs = 2
		
		if len(sys.argv) > 1 and int(sys.argv[1]) > 0:
			njobs = int(sys.argv[1])
		if len(sys.argv) > 2:
			ncpus = int(sys.argv[2])

		start_time = time.time()
	# 
		print 'creating celery instance...'
		cw = 'redis://ew_tb_was_3p/'
  	 	celery = Celery(backend=cw, broker=cw, config_source='celeryconfig')

		cc = control.Control()
		pong = cc.ping(timeout=3)
		if len(pong) == 0:
			print 'no worker node is available'
			sys.exit(1)
		
		print 'calling jobs... with workers:', len(pong)

		job = group([subtask('bottest_run', args=[i]) for i in xrange(njobs)])
# 		jobs = group([send_task("bottest_run", args=[i]).subtask() for i in xrange(njobs)])
		
		print 'job', njobs
		
 		result = job.apply_async()
# 		result = job()
		print 'result'

		done = 0
		th = int(njobs * 0.01)
		th = max(th, 10)
		total_num = 0
		total_sum = 0.0
		total_avg = 0.0
		for r in result.iterate():
			done += 1
			if done % th == 0:
				print 'processed:', done
			total_num += r['total_num']
			total_sum += r['total_sum']

		results = result.join()
		print 'joining was done'
		
		verbose = True
		verbose = False
		
		ela = time.time() - start_time
		print "Time elapsed: ", ela, "s"
		print 'time per job:', ela / njobs
		if total_num > 0:
			total_avg = total_sum / total_num
		print 'operation total_num:', total_num
		print 'operation total_sum:', total_sum
		print 'operation total_avg:', total_avg
			
		# print results	
except: 
	print 'celery was not found'
	
def main_parallelpython():
	# MAIN BODY #
	import pp
	
	# tuple of all parallel python servers to connect with
	ppservers = ()
	# ppservers = ("10.0.0.1",)
	ppservers = ('ew_tb_was_2p', 'ew_tb_was_3p', 'ew_tb_was_4p',)
	
	njobs = 100

	# also number of workers
	ncpus = 20
 	ncpus = 'autodetect'

	if len(sys.argv) > 1 and int(sys.argv[1]) > 0:
		njobs = int(sys.argv[1])
	if len(sys.argv) > 2:
		ncpus = int(sys.argv[2])
		
	if len(ppservers) > 0 :
		ncpus = 0

    # Creates jobserver with ncpus workers
 	job_server = pp.Server(ncpus, ppservers=ppservers)

	# Creates jobserver with automatically detected number of workers
# 	job_server = pp.Server(ppservers=ppservers)
	
	print "Starting pp with", job_server.get_ncpus(), "workers", njobs, " jobs"
	
	# Submit a job of calulating sum_primes(100) for execution. 
	# sum_primes - the function
	# (100,) - tuple with arguments for sum_primes
	# (isprime,) - tuple with functions on which function sum_primes depends
	# ("math",) - tuple with module names which must be imported before sum_primes execution
	# Execution starts as soon as one of the workers will become available

	
	start_time = time.time()
	
	jobs = [job_server.submit(bottest_run, (i,)) for i in xrange(njobs)]
	verbose = True
	verbose = False
	if verbose:
		for job in jobs:
			result = job()
			# print 'result is', result
	else:
		job_server.wait()
	
	print "Time elapsed: ", time.time() - start_time, "s"
	job_server.print_stats()

if __name__ == "__main__":	
	if use_celery:
		main_celery()
	else:
		main_parallelpython()

