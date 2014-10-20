<?php
limbo::router()->build ('/testing/router/(@test1(/@test2))', function ($test1, $test2) {
	echo "Test1: {$test1}<br>";
	echo "Test2: {$test2}";
	
	limbo::render (config ('web.template'), array (
		'title' 	=> config ('web.title'),
		'content'	=> \limbo\web\web::clear_buffer ()
		));
	});