<?php
/* ==================================================
 *   Read a message from MTAs
   ================================================== */
/*
	This script is called from .forward/.qmail/.procamail
	In your .forward+XXXXX/.qmail-XXXXX etc:
	| /usr/bin/php /PATH/TO/WP/wp-content/plugins/ktai_entry/inc/inject.php

	If you are using WordPress MU, specify "blog id" with -blog option
	| /usr/bin/php /PATH/TO/WP/wp-content/plugins/ktai_entry/inc/inject.php -blog 2
	
 */

define('KE_BLOGID_OPTION', '-blog');
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

global $blog_id;
$blog_id = 0;
if ($argc >= 3 && $argv[1] == KE_BLOGID_OPTION) {
	$blog_id = intval($argv[2]);
}

if ( !defined('ABSPATH') ) {
	global $wpload_error;
	$wpload_error = 'Could not read messages because custom WP_PLUGIN_DIR is set.';
	$wpload_status = QMAIL_DELIVERY_FAILED_PERMANENTLY;
	require dirname(dirname(__FILE__)) . '/wp-load.php';
}
if ( !class_exists('KtaiEntry') ) {
	echo "The plugin is not activated.\n";
	exit(QMAIL_DELIVERY_FAILED_PERMANENTLY);
}
global $Ktai_Entry;
require dirname(__FILE__) . '/post.php';

$message = '';
while ($line = fgets(STDIN, 1024)) {
	$message .= $line;
}
if (strlen($message) <= 3) {
	$message = file_get_contents('php://stdin');
}
if (strlen($message) <= 3) {
	$error = new KE_Error('The Message is too short.', QMAIL_DELIVERY_FAILED_PERMANENTLY);
	do_action('ktai_inject_too_short', $error);
	ke_inject_error($error);
	// exit;
}

if (isset($_ENV['SENDER'])) {
	$sender = $_ENV['SENDER'];
} elseif (isset($_ENV['SMTPMAILFROM'])) {
	$sender = $_ENV['SMTPMAILFROM'];
} else {
	$sender = __('Unknown envelope sender', 'ktai_entry_log');
}
$Ktai_Entry->debug_print(sprintf(__("***************************\n" . 'Received a %1$d-byte-message from %2$s', 'ktai_entry'), strlen($message), $sender));

$mailpost = new KtaiEntry_Post('mta', NULL);
$result = $mailpost->parse($message);
if (is_ke_error($result)) {
	do_action('ktai_inject_parse_error', $result);
	ke_inject_error($result);
	// exit;
}
$result = $mailpost->insert();
if (is_ke_error($result)) {
	do_action('ktai_inject_insert_error', $result);
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
	echo $message . "\n";
	$code = ($e->getCode() < 0) ? QMAIL_DELIVERY_FAILED_PERMANENTLY : QMAIL_DELIVERY_SUCCESSFUL;
	exit($code);
}
?>