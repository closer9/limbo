<?php

$SQL->prepare ('SELECT * FROM `?` WHERE username = ?', array (
	'auth_users',
	'admin'
	));