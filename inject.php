<?php
/* ==================================================
 *   Read a message from MTAs
   ================================================== */

define('QMAIL_DELIVERY_SUCCESSFUL', 0);
define('QMAIL_DELIVERY_SUCCESSFUL_IGNORE_FURTHER', 99);
define('QMAIL_DELIVERY_FAILED_PERMANENTLY', 100);
define('QMAIL_DELIVERY_FAILED_TRY_AGAIN', 111);

if (isset($_SERVER['HTTP_HOST'])) {
	header("HTTP/1.0 403 Forbidden");
?>
<!DOCTYPE HTML PUBLIC "-//IETF//DTD HTML 2.0//EN">
<HTML><HEAD>
<TITLE>403 Forbidden</TITLE>
</HEAD><BODY>
<H1>Forbidden</H1>
You don't have permission to access the URL on this server.
</BODY></HTML>
<?php
	exit;
}

$wpload_error = 'Could not read messages because custom WP_PLUGIN_DIR is set.';
$wpload_status = QMAIL_DELIVERY_FAILED_PERMANENTLY;
require dirname(__FILE__) . '/wp-load.php';
if (! class_exists('Ktai_Entry')) {
	echo 'The plugin is not activated.';
	exit(QMAIL_DELIVERY_FAILED_PERMANENTLY);
}
global $Ktai_Entry;
require dirname(__FILE__) . '/post.php';

$message = '';
while ($line = fgets(STDIN, 1024)) {
	$message .= $line;
}
if (strlen($message) <= 3) {
	$error = new KE_Error('The Message is too short.', QMAIL_DELIVERY_FAILED_PERMANENTLY);
	ke_inject_error($error);
	// exit;
}

if (isset($_ENV['SENDER'])) {
	$sender = $_ENV['SENDER'];
} elseif (isset($_ENV['SMTPMAILFROM'])) {
	$sender = $_ENV['SMTPMAILFROM'];
} else {
	$sender = __('Unknown', 'ktai_entry_log');
}
$Ktai_Entry->debug_print(sprintf(__("***************************\n" . 'Received a %1$d-byte-message from %2$s', 'ktai_entry'), strlen($message), $sender));

$post = new Ktai_Entry_Post('mta', NULL);
$contents = $post->parse($message);
if (is_ke_error($contents)) {
	ke_inject_error($contents);
	// exit;
}
$result = $post->insert($contents);
if (is_ke_error($result)) {
	ke_inject_error($result);
	// exit;
}
exit (QMAIL_DELIVERY_SUCCESSFUL);

/* ==================================================
 * @param	object     $e
 * @return	int        $code
 */
function ke_inject_error($e) {
	global $Ktai_Entry;
	$message = $e->getMessage();
	$Ktai_Entry->logging($message);
	echo "$message\n";
	$code = ($e->getCode() < 0) ? QMAIL_DELIVERY_FAILED_PERMANENTLY : QMAIL_DELIVERY_SUCCESSFUL;
	exit($code);
}
?>