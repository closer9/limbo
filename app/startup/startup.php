<?php
// Are we using the database
if (config ('database.options'))
	{
	limbo::ioc ('sql', function () {
		return new \limbo\mysql (config ('database.options'), 'default');
		});
	
	register ('SQL', limbo::ioc ('sql'));
	
	// Are we using authentication
	if (config ('auth.options'))
		{
		limbo::ioc ('auth', function ($c) {
			$auth = new \limbo\auth (config ('auth.options'), $c['sql']);
		
			if ($reason = $auth->blocked_check ($auth->address))
				{
				throw new limbo\error ("Access denied. Reason: {$reason}", array (
					'simple' 	=> true,
					'response' 	=> 401
					));
				}
			
			return $auth;
			});
		
		register ('AUTH', limbo::ioc ('auth'));
		}
	}

limbo::ioc ('smtp', function () {
	return new limbo\util\smtp ();
	});

$path	= limbo::request ()->path;
$root 	= config ('web.root');

register (array (
	'smtp'		=> limbo::ioc ('smtp'),
	'path'		=> $path,
	'root'		=> $root
));
