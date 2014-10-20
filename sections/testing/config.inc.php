<?php
use \limbo\web\web;

update_title ('Testing');

limbo::router ()->build ('request', function () {
	echo '<pre>';
	
	print_r (limbo::request ());
	
	echo '</pre>';
	
	// Load the template and render the page
	limbo::render (config ('web.template'), array (
		'title' 	=> config ('web.title'),
		'content'	=> web::clear_buffer ()
		));
	});