<pre>	
<?php
$cache = limbo::cache ();

if (($message = $cache->message) == null)
	{
	echo "Setting new message in cache for 30 seconds.\n\n";
	
	$cache->set ('message', 'Hello Cache!', 30);
	
	/**
	 * Alternatively you can use the magic methods:
	 * 
	 * $cache->message = array ('Hello Cache!', 30);
	 */
	}
	else
	{
	echo "Retrieved message from cache.<br>";
	
	echo "Message: {$message}\n\n";
	}

print_r ($cache->info ('message'));

print_r ($cache->stats ());

//$cache->flush ();
?>
</pre>
	