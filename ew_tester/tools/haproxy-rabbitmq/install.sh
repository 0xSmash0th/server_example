
cp -vf rabbitmq-check /usr/bin
cp -vf rabbitmqchk /etc/xinetd.d

grep "^rabbitmqchk" /etc/services >/dev/null || echo "rabbitmqchk    9201/tcp    # RabbitMQ check" >> /etc/services

echo "
Done! Restart xinetd daemon

	service xinetd restart

And test with curl

	curl http://localhost:9201
"
