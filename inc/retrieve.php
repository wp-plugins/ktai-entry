<?php
/* ==================================================
 *   Retrieve messages from external mailbox
   ================================================== */

/*
	If you want to use cron, write crontabl below:
	2,17,32,47 * * * * /usr/bin/php /PATH/TO/WP/wp-content/plugins/ktai_entry/inc/retrieve.php
 */

if ( !defined('ABSPATH') ) {
	global $wpload_error;
	$wpload_error = 'Could not retrieve messages because custom WP_PLUGIN_DIR is set.';
	require dirname(dirname(__FILE__)) . '/wp-load.php';
}
header('Content-Type: text/html; charset=' . get_bloginfo('charset'));
if ( !class_exists('KtaiEntry') ) {
	header("HTTP/1.0 501 Not Implemented");
?>
<!DOCTYPE HTML PUBLIC "-//IETF//DTD HTML 2.0//EN">
<HTML><HEAD>
<TITLE>501 Not Implemented</TITLE>
</HEAD><BODY>
<H1>Not Implemented</H1>
The plugin is not activated.
</BODY></HTML>
<?php
	exit;
}

/* ==================================================
 *   KtaiEntry_Retrieve class
   ================================================== */

class KtaiEntry_Retrieve extends KtaiEntry {
	private $post;
	private $pop3;

// ==================================================
public function __construct() {
	if ( isset($_SERVER['HTTP_HOST']) ) {
		if ( isset($_GET['_wpnonce']) ) {
			if ( !$this->verify_nonce($_GET['_wpnonce'], 'ktai-entry-retrieve')) {
				$this->http_error(400, __('Your request could not be understood by the server due to malformed syntax.', 'ktai_entry_log'));
				// exit;
			}
			// Go to retireve process.
		} elseif ( !$this->elapsed_interval() ) {
			$this->display_as_html('Retrieval interval does not elapsed.', 'ktai_entry_log');
			exit;
		}
	}
	update_option('ke_last_checked', time());
	return;
}

/* ==================================================
 * @param	none
 * @return	boolean  $elapsed
 */
private function elapsed_interval() {
	$last_checked = get_option('ke_last_checked');
	$interval     = apply_filters('ke_retrieve_interval', $this->get_option('ke_retrieve_interval'));
	if ($interval <= 0 || $last_checked < 0 || $interval * 60 > (time() - $last_checked)) {
		return false;
	}
	return true;
}

// ==================================================
public function connect() {
	$server_url   = get_option('mailserver_url');
	$server_port  = get_option('mailserver_port');
	$server_login = get_option('mailserver_login');
	$server_pass  = get_option('mailserver_pass');

	// Do nothing if default value
	if (empty($server_url) || $server_url == 'mail.example.com'
		|| $server_port <= 0
		|| empty($server_login) || $server_login == 'login@example.com'
		|| $server_pass == 'password'
		) {
		$this->http_error(502, __('The POP3 config is not valid.', 'ktai_entry_log'));
		// exit;
	}

	require dirname(__FILE__) . '/post.php';
	require dirname(__FILE__) . '/class-pop3.php';

	$format = $this->return_css ? 'text' : 'html';
	$this->post = new KtaiEntry_Post('pop', $format);
	$this->pop3 = new KtaiEntry_POP3();
	$this->pop3->ALLOWAPOP = $this->get_option('ke_use_apop');
	if (! $this->pop3->connect($server_url, $server_port)) {
		$this->http_error(502, $this->pop3->ERROR);
	}
	if ($this->get_option('ke_use_apop')) {
		$count = $this->pop3->apop($server_login, $server_pass);
	} else {
		$count = $this->pop3->login($server_login, $server_pass);
	}

	if (false === $count || $count < 0) {
		$error = $this->pop3->ERROR;
		$this->pop3->quit();
		$this->http_error(502, $error);
		// exit;
	} elseif (0 == $count) {
		$this->pop3->quit();
		$this->display(__("There doesn't seem to be any new mail.", 'ktai_entry_log'));
		return $count;
	}
	$this->display(sprintf(__("***************************\nThere is %d message(s).", 'ktai_entry_log'), $count));
	return $count;
}

// ==================================================
public function retrieve($count) {
	for ($i=1; $i <= $count; $i++) :
		$lines = $this->pop3->get($i);
		$contents = $this->post->parse(str_replace("\r\n", "\n", implode('', (array) $lines)));
		if (is_ke_error($contents)) {
			$message = sprintf(__('Error at #%1$d: %2$s', 'ktai_entry_log'), $i, $contents->getMessage());
			do_action('ktai_retrieve_parse_error', $message, $contents);
			$this->display($message);
			continue;
		}
		$result = $this->post->insert($contents);
		if (is_ke_error($result)) {
			$message = sprintf(__('Error at #%1$d: %2$s', 'ktai_entry_log'), $i, $result->getMessage());
			do_action('ktai_retrieve_insert_error', $message, $contents);
			$this->display($message);
			continue;
		}
		if (! $this->pop3->delete($i)) {
			$error = $this->pop3->ERROR;
			$this->pop3->reset();
			$message = sprintf(__('Can\'t delete message #%1$d: %2$s', 'ktai_entry_log'), $i, $error);
			do_action('ktai_retrieve_delete_error', $message, $i);
			$this->display($message);
			break;
		} else {
			$message = sprintf(__('Mission complete, message "%d" deleted.', 'ktai_entry_log'), $i);
			do_action('ktai_retrieve_complete', $message, $contents);
			$this->display($message);
		}
	endfor;

	$this->pop3->quit();
	return;
}

/* ==================================================
 * @param	string     $nonce
 * @param	string|int $action
 * @return	boolean    $result
 * based on wp_verify_nonce at wp-includes/pluggable.php at WP 2.5
 */
private function verify_nonce($nonce, $action = -1) {
	$i = wp_nonce_tick();
	// Nonce generated 0-12 hours ago
	if ( substr(wp_hash($i . $action), -12, 10) == $nonce )
		return 1;
	// Nonce generated 12-24 hours ago
	if ( substr(wp_hash(($i - 1) . $action), -12, 10) == $nonce )
		return 2;
	// Invalid nonce
	return false;
}

/* ==================================================
 * @param	string   $message
 * @return	none
 */
public function display($message) {
	$this->display_as_html($message);
	$this->logging($message);
	return;
}

// ===== End of class ====================
}

global $Ktai_Entry;
$mail = new KtaiEntry_Retrieve($Ktai_Entry);
$count = $mail->connect();
if ($count) {
	$mail->retrieve($count);
}
$mail->display(__('Retrieval completed.', 'ktai_entry_log'));
?>