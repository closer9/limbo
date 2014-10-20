<pre>
	<?php
	echo "Current messages:\n";
	
	print_r ($_SESSION);
	
	print_r ($l->flash_get ());
	
	print_r ($l->flash_get ('test'));
	
	unset ($_SESSION['limbo.test']);
	?>
	<a href="index">Go back</a>
</pre>