<?php
/* Limbo options */
$config['limbo.production']	= false;
$config['limbo.timezone']	= 'America/Chicago';

/* Filesystem paths */
$config['path.dir']		= '/var/www/limbo.neg9.com/';
$config['path.app']		= $config['path.dir'] . 'app/';
$config['path.section']	= $config['path.dir'] . 'sections/';
$config['path.storage']	= $config['path.app'] . 'storage/';
$config['path.views']	= $config['path.app'] . 'views/';

/* Class paths */
$config['path.class']	= array (
	$config['path.app'] . 'class/'
	);

/* Website related configuration */
$config['web.title']	= 'Limbo';
$config['web.ssl']		= false;
$config['web.root']		= '/';
$config['web.template']	= 'limbo';
$config['web.domains']	= array (
	'limbo.neg9.com',
	);

$config['cache.http']	= false;
$config['cache.manual']	= true;
$config['cache.driver']	= 'files';
$config['cache.path']	= $config['path.storage'] . 'cache/';


$config['web.css'] = array (
	'//ajax.googleapis.com/ajax/libs/jqueryui/1.11.2/themes/smoothness/jquery-ui.css',
	$config['web.root'] . 'css/limbo/common.css',
	$config['web.root'] . 'css/limbo/limbo.css',
	);

$config['web.javascript'] = array (
	'//ajax.googleapis.com/ajax/libs/jquery/1.11.1/jquery.min.js',
	'//ajax.googleapis.com/ajax/libs/jqueryui/1.11.2/jquery-ui.min.js',
	$config['web.root'] . 'script/limbo/pagination.js',
	$config['web.root'] . 'script/limbo/ajaxform.js',
	$config['web.root'] . 'script/limbo/heartbeat.js',
	$config['web.root'] . 'script/limbo/helpers.js',
	$config['web.root'] . 'script/limbo/popup.js',
	$config['web.root'] . 'script/limbo/startup.js',
	);

/* Logging configuration */
$config['log.path']		= $config['path.storage'] . 'logs/';
$config['log.type']		= 'daily';								// daily, weekly, monthly, none
$config['log.level']	= 4;									// 1 - notices, 4 - debug
$config['log.retain']	= 7;									// How many log files to keep (0 = keep all)

/* Admin configuration */
$config['admin.name']	= 'Scott McKee';
$config['admin.email']	= 'closer9@gmail.com';
$config['admin.notify']	= array (
	$config['admin.email'],
	);

/* Email options */
$config['smtp.server']		= 'localhost';
$config['smtp.username']	= '';
$config['smtp.password']	= '';

/* Error handling */
$config['error.mail']		= false;
$config['error.from']		= 'Limbo Error <' . $config['admin.email'] . '>';
$config['error.simple']		= false;
$config['error.content']	= 'error';
$config['error.template']	= 'limbo';

/* Security configuration */
$config['secure.salt']	= "8juhebg27&LIU\nF&#fo3ffF\t\t)(Qf:DShf8h\ne)ls2u^hena-O3UH7g%ws";

/* Setup database information */
$config['database.options'] = array (
	'connections' => array (
		'default' => array ('localhost', 'limbo', 'password', 'limbo_db'),
		),
	'email' => $config['error.mail'],
	);

/* Setup the authentication class */
$config['auth.options'] = array (
	'db_users'		=> 'auth_users',
	//'db_info'		=> 'auth_info',
	//'db_settings'	=> 'auth_settings',
	'db_sessions'	=> 'auth_sessions',
	'db_logins'		=> 'auth_logins',
	//'db_blocked'	=> 'auth_blocked',
	//'db_audit'	=> 'auth_audit',
	//'db_heartbeat'=> 'auth_heartbeat',
	'database'		=> 'default',
	'validate'		=> true
	);

/* Load the config */
config ($config);