<pre>
	<?php
	$l->flash_set ('test', 'Hello!');
	
	echo "Current messages:\n";
	
	print_r ($l->flash_get ('test'));
	
	$_SESSION['limbo.test'] = 'BoO!';
	?>
	<a href="dest">Continue</a>
</pre>
