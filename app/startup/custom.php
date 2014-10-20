<?php
/*
 * This is a custom startup file. If you have custom routes or other startup related
 * tasks, this is the place to put them.
 */

limbo::router ()->build ('/info', function () {
	phpinfo ();
	});