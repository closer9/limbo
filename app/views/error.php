<h4>Looks like you've encountered an error.</h4>

<br />

<div class="box-light">
	<div class="textc bold m10 red" style="font-size: 1.4em;"><?php echo $message?></div>
</div>

<br />

<h5>Other useful information</h5>

<div class="box-light p10">
	<table class="wide">
		<tr>
			<th style="width: 120px;"></th>
			<th></th>
		</tr>
		<tr>
			<td>Error in script:</td>
			<td><?php echo $file?> (<?php echo $line?>)</td>
		</tr>
		<tr>
			<td>Your IP address:</td>
			<td><?php echo $_SERVER["REMOTE_ADDR"]?></td>
		</tr>
		<tr>
			<td>Your session ID:</td>
			<td><?php echo session_id ()?></td>
		</tr>
		<tr>
			<td style="vertical-align: top;">Requested URL:</td>
			<td><?php echo $_SERVER["REQUEST_URI"]?></td>
		</tr>
	</table>
</div>

<?php
if (! config ('limbo.production'))
	{
	?>
		<h4 style="margin-top: 2em;">Non-production Information:</h4>
		
		<div class="pre"><p><b>Backtrace:</b></p><?php debug_print_backtrace (0, 20)?></div>
	<?php
	}
?>