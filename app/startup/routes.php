<?php
/*
 * This script contains the various routes for the framework.
 */

limbo::router ()->build ('/limbo-register', function ()
	{
	$_SESSION['limbo-register'][$_GET['id']] = '1';
	
	limbo::json (array ('results' => session_id () . ' - ' . $_GET['id']));
	});

limbo::router ()->build ('/limbo-modal(/@template)', function ($template)
	{
	limbo::render ("modals/{$template}");
	});

limbo::router ()->build ('/limbo-storage/@app/@file', function ($app, $file)
	{
	$type	= (! empty (limbo::request()->request['type'])) ? limbo::request()->request['type'] : '';
	$size	= (! empty (limbo::request()->request['size'])) ? limbo::request()->request['size'] : 0;
	$cache	= (isset (limbo::request()->request['cache'])) ? true : false;
	$attach	= (isset (limbo::request()->request['attach'])) ? true : false;
	
	limbo\util\storage::download ($app, $file, $type, $size, $attach, $cache);
	});

limbo::router ()->build ('/limbo-heartbeat', function ()
	{
	$auth = limbo::ioc ('auth');
	
	if (is_object ($auth))
		{
		$query	= (isset ($_REQUEST['query'])) ? $_REQUEST['query'] : '';
		$active = (isset ($_REQUEST['active'])) ? true : false;
		
		limbo\log::debug ('Heartbeat (' . limbo::ioc ('request')->ip . '): ' . @base64_decode ($query));
		
		limbo::json (array (
			'status'  => ($auth->heartbeat ($query, $active)) ? time () : 0,
			'message' => $auth->errormsg
			));
		}
	});
