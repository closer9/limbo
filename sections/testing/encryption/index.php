<?php
$text 	= 'Hello world!';
$key	= 'lolkijuyhgtrfderfgtyhbnjh76ytgfr';

$encrypted = limbo\util\security::encrypt ($text, $key);
$decrypted = limbo\util\security::decrypt ($encrypted, $key);

echo "Text: {$text}<br>";
echo "Enc: {$encrypted}<br>";
echo "Dec: {$decrypted}<br>";