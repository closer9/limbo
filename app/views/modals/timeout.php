<?php
if (is_object (limbo::ioc ('auth')))
	{
	limbo::ioc ('auth')->logout (true);
	}
?>

<div class="textc" style="margin: 10px 20px 20px 20px;">
	<?php
	if (preg_match ('/session variables failed/i', limbo::request()->get['message']))
		echo 'Your account was signed in from another location.';
		else
		echo 'Your session has expired. Please login again.';
	?>
</div>

<div class="textc">
	<a href="<?php echo $root?>testing/auth/?redirect=<?php echo base64_encode (@limbo::request()->url)?>" class="button"> OK </a>
</div>