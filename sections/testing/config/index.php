<pre><?php
	limbo::config()->set ('limbo.test', array (
		'var1' => 4,
		'var2' => 'hi',
		'var3' => 'bye',
		));

	limbo::config()->set (array (
		'limbo.yum' => 'bacon',
		'limbo.hah' => '',
		'limbo.mmm' => 'cheese',
		));

	limbo::config('limbo')->load ();
	
	print_r (config('limbo.test'));
	?>
</pre>
