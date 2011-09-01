<?php
/* ==================================================
 *   Retrieve messages from external mailbox
   ================================================== */

$wpload_error = 'Could not retrieve messages because custom WP_PLUGIN_DIR is set.';
require dirname(__FILE__) . '/wp-load.php';

header('Content-Type: text/html; charset=' . get_bloginfo('charset'));
if (! class_exists('Ktai_Entry')) {
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

global $Ktai_Entry;
$mail = new Ktai_Entry_Retrieve($Ktai_Entry);
require dirname(__FILE__) . '/post.php';
require dirname(__FILE__) . '/class-pop3.php';
$count = $mail->connect();
if ($count) {
	$mail->retrieve($count);
}
_e('/* Retrieval completed. */', 'ktai_entry_log');
exit;

/* ==================================================
 *   Ktai_Entry_Retrieve class
   ================================================== */

class Ktai_Entry_Retrieve {
	private $parent;
	private $return_css;
	private $post;
	private $pop3;

// ==================================================
public function __construct($parent) {
	$this->parent = $parent;
	$this->return_css = false;

	if (isset($_SERVER['HTTP_HOST'])) {
		if (isset($_GET['_wpnonce'])) {
			if (! $this->verify_nonce($_GET['_wpnonce'], 'ktai-entry-retrieve')) {
				$this->parent->http_error(400, __('Your request could not be understood by the server due to malformed syntax.', 'ktai_entry_log'));
				// exit;
			}
		} else {
			$this->return_css = true;
			header('Content-Type: text/css; charset=UTF-8');
			if (! Ktai_Entry::elapsed_interval()) {
				_e('/* Retrieval interval does not elapsed. */', 'ktai_entry_log');
				exit;
			}
		}
	}
	update_option('ke_last_checked', time());
	return;
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
		$this->parent->http_error(502, __('The POP3 config is not valid.', 'ktai_entry_log'));
		// exit;
	}

	$format = $this->return_css ? 'text' : 'html';
	$this->post = new Ktai_Entry_Post('pop', $format);
	$this->pop3 = new KE_POP3();
	$this->pop3->ALLOWAPOP = $this->post->get_option('ke_use_apop');
	if (! $this->pop3->connect($server_url, $server_port)) {
		$this->parent->http_error(502, $this->pop3->ERROR);
	}
	if ($this->post->get_option('ke_use_apop')) {
		$count = $this->pop3->apop($server_login, $server_pass);
	} else {
		$count = $this->pop3->login($server_login, $server_pass);
	}

	if (false === $count || $count < 0) {
		$error = $this->pop3->ERROR;
		$this->pop3->quit();
		$this->parent->http_error(502, $error);
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
		$contents = $this->post->parse(str_replace("\r\n", "\n", implode('', $lines)));
		if (is_ke_error($contents)) {
			$this->display(sprintf(__('Error at #%1$d: %2$s', 'ktai_entry_log'), $i, $contents->getMessage()));
			continue;
		}
		$result = $this->post->insert($contents);
		if (is_ke_error($result)) {
			$this->display(sprintf(__('Error at #%1$d: %2$s', 'ktai_entry_log'), $i, $result->getMessage()));
			continue;
		}
		if (! $this->pop3->delete($i)) {
			$error = $this->pop3->ERROR;
			$this->pop3->reset();
			$this->display(sprintf(__('Can\'t delete message #%1$d: %2$s', 'ktai_entry_log'), $i, $error));
			break;
		} else {
			$this->display(sprintf(__('Mission complete, message "%d" deleted.', 'ktai_entry_log'), $i));
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
	$i = ceil(time() / 43200);
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
private function display($message) {
	if (! $this->return_css) {
		$this->parent->display_as_html($message);
	} elseif (defined('KE_DEBUG')) {
		$this->parent->display_as_comment($message);
	}
	$this->parent->logging($message);
	return;
}

// ===== End of class ====================
}
?>