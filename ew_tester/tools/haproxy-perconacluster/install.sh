
cp -vf clustercheck /usr/bin
cp -vf mysqlchk /etc/xinetd.d

grep "^mysqlchk" /etc/services >/dev/null || echo "mysqlchk    9200/tcp    # mysql check" >> /etc/services

echo "
Done! Restart xinetd daemon

	service xinetd restart

And test with curl

	curl http://localhost:9200
"
