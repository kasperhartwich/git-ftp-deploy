Git FTP deploy
==
Use this to deploy your git repository to a webserver with only FTP access.

Config file
--
The config file must be placed in `./.git/ftpconfig` of your git repository

Example:

	[master]
	server = 192.168.1.1
	port = 21
	username = myuser
	password = mypass
	remote_path = /www/domain.dk/