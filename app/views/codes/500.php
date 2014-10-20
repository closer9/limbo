<h2>Internal server error (500)</h2>

<p class="bold" style="margin-top: 1em;">
	This is just a generic error code, but something seriously bad just happened!
</p>

<p style="margin-top: 1em;">
	This error message means that the webserver tried to do a task and failed, badly. If your just passing by
	and triggered this error don't worry, the website probably just has a bug. If you can,
	<a href="mailto: <?php echo config ('admin.email')?>">send us an e-mail</a> so we can fix the problem.
</p>

<h4 style="margin-top: 2em;">Want more details?</h4>

<ul>
	<li>Requested page: <span class="italic"><?php echo limbo::request()->url?></span></li>
	<li>Hostname: <span class="italic"><a href="http://<?php echo limbo::request()->server?>/"></a></span></li>
</ul>

<?php
if (! config ('limbo.production'))
	{
	?>
		<h4 style="margin-top: 2em;">Lets get technical about this:</h4>
		
		<p class="pre"><b>Request:</b><br><?php print_r (limbo::request ())?></p>
		<p class="pre"><b>Router:</b><br><?php print_r (limbo::router ()->get_routes ())?></p>
	<?php
	}
?>