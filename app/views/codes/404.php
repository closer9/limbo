<h2>Document not found (404)</h2>

<p class="bold" style="margin-top: 1em;">
	Oops! Looks like the page you're looking for is not available. You probably clicked an old link.
</p>

<p style="margin-top: 1em;">
	This usually happens when we've moved a file and you clicked on an out of date link somewhere. Hopefully
	you clicked a link from a search engine. If you think this isn't just a fluke, be a pal and
	<a href="mailto: <?php echo config ('admin.email')?>">let us know</a>.
</p>

<ul style="margin-top: 1em;">
	<li>Requested page: <span class="italic"><?php echo limbo::request ()->url?></span></li>
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