<?php
/*
 * This script contains the various routes for the framework.
 */

limbo::router ()->build ('/limbo-register', function () {
	$_SESSION['limbo-register'][$_GET['id']] = '1';
	
	limbo::json (array ('results' => session_id () . ' - ' . $_GET['id']));
	});

limbo::router ()->build ('/limbo-modal(/@template)', function ($template) {
	limbo::render ("modals/{$template}");
	});

limbo::router ()->build ('/limbo-heartbeat', function () {
	$auth = limbo::ioc ('auth');
	
	if (is_object ($auth))
		{
		limbo\log::debug ('Heartbeat (' . limbo::ioc ('request')->ip . '): ' . @base64_decode ($_REQUEST['query']));
		
		limbo::json (array (
			'status'  => ($auth->heartbeat (@$_REQUEST['query'])) ? time () : 0,
			'message' => $auth->errormsg
			));
		}
	});