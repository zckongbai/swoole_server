<?php

$config = array(
		'server'	=>	array(
				'charset'	=>	'UTF-8',

			),

		'redis'	=>	array(
				'redis_id'	=>	'master',
				'host'	=>	'127.0.0.1',
				'port'	=>	'6379',
				'password'	=>	'',
				'pconnect'	=>	true,
				'database'	=>	'',
				'timeout'	=>	0.5,
				'error_log'	=>	LOGPATH . '/redis_error.log',
			),


	);

return $config;