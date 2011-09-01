<?php
/*
Plugin Name: Ktai Entry
Plugin URI: http://wppluginsj.sourceforge.jp/ktai_entry/
Description: Make posts from messages sent by mobile phones.
Version: 0.8.11
Author: IKEDA Yuriko
Author URI: http://www.yuriko.net/cat/wordpress/
*/

/*  Copyright (c) 2008-2009 yuriko

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; version 2 of the License.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
*/

// define('KE_LOGFILE', 'logs/error.log');
// define('KE_DEBUG', true);
define('KE_LOGFILE_PERM', 0000666);

define('KE_POST_TEMPLATE', '<div class="photo">{images}</div>
<p>{text}</p>
<div class="photo-end"> </div>');
define('KE_TEMPLATE_TEXT', '{text}');
define('KE_TEMPLATE_IMAGES', '{images}');

/* ----- Put this style into your style.css -----
.photo {
	padding-right:6px;
	float:left;
	line-height:110%;
	font-size:0.85em;
	text-indent:0;
}
.photo img {
	background:white;
	margin:0 4px 4px 0;
	padding:3px;
	border:1px solid #999;
}
.photo-end {
	clear:left;
}
---------- */

if (! defined('WP_LOAD_CONF')) {
	define('WP_LOAD_CONF', 'wp-load-conf.php');
	define('WP_LOAD_PATH_STRING', 'WP-LOAD-PATH:');
}

global $Ktai_Entry;
$Ktai_Entry = new Ktai_Entry();
if (! defined('WP_USE_THEMES')) {
	$KE_Prefs = new Ktai_Entry_PrefPane();
	add_action('admin_menu',  array($KE_Prefs, 'add_page'));
}

/* ==================================================
 *   Ktai_Entry class
   ================================================== */

class Ktai_Entry {
	private	$wp_vers;
	private $plugin_dir;
	private $plugin_url;
	private $nonce = -1;
	protected $disp_format;

// ==================================================
public function __construct() {
	$this->wp_vers = NULL;
	$this->set_plugin_dir();
	$this->load_textdomain('ktai_entry', 'lang');
//	$this->load_textdomain('ktai_entry_log', 'lang');
	if (defined('WP_USE_THEMES')) {
		if ($this->elapsed_interval() 
		|| (defined('WP_CACHE') && WP_CACHE && apply_filters('retrieve_interval/ktai_entry.php', self::get_option('ke_retrieve_interval')) > 0)) {
			add_action('wp_head', array($this, 'add_check_messages'));
		}
	} elseif (defined('WP_ADMIN') && WP_ADMIN || preg_match('!wp-admin/plugins(\.php)?($|\?)!', $_SERVER['REQUEST_URI'])) {
		register_activation_hook(__FILE__, array($this, 'check_wp_load'));
		register_activation_hook(__FILE__, array($this, 'started'));
		register_deactivation_hook(__FILE__, array($this, 'stopped'));
	}
	// ----- Prevent launch of wp-mail.php
	if (preg_match('/^([^?]*)/', $_SERVER['REQUEST_URI'], $path) && basename($path[1], '.php') == 'wp-mail') {
		$this->http_error(403, "You don't have permission to access the URL on this server.");
		// exit;
	}	
}

/* ==================================================
 * @param	none
 * @return	none
 */
private function set_plugin_dir() {
	$this->plugin_dir = basename(dirname(__FILE__));
	if (function_exists('plugins_url')) {
		$this->plugin_url = plugins_url($this->plugin_dir . '/');
	} else {
		$this->plugin_url = get_bloginfo('wpurl') . '/' 
		. (defined('PLUGINDIR') ? PLUGINDIR . '/': 'wp-content/plugins/') 
		. $this->plugin_dir . '/';
	}
}

/* ==================================================
 * @param	string   $domain
 * @param	string   $subdir
 * @return	none
 */
private function load_textdomain($domain, $subdir = '') {
	$lang_dir = $this->get('plugin_dir') . ($subdir ? '/' . $subdir : '');
	if ($this->check_wp_version(2.6)) {
		load_plugin_textdomain($domain, false, $lang_dir);
	} else {
		$plugin_path = defined('PLUGINDIR') ? PLUGINDIR . '/': 'wp-content/plugins/';
		load_plugin_textdomain($domain, $plugin_path . $lang_dir);
	}
}

/* ==================================================
 * @param	string   $version
 * @param	string   $operator
 * @return	boolean  $result
 */
public function check_wp_version($version, $operator = '>=') {
	if (! $this->wp_vers) {
		$this->wp_vers = get_bloginfo('version');
		if (! is_numeric($this->wp_vers)) {
			$this->wp_vers = preg_replace('/[^.0-9]/', '', $this->wp_vers);  // strip 'ME'
		}
	}
	return version_compare($this->wp_vers, $version, $operator);
}

/* ==================================================
 * @param	none
 * @return	none
 */
public function check_wp_load() {
	$wp_root = dirname(dirname(dirname(dirname(__FILE__)))) . '/';
	if (! file_exists($wp_root . 'wp-load.php') && ! file_exists($wp_root . 'wp-config.php') && function_exists('plugins_url')) {
		$conf = dirname(__FILE__) . '/' . WP_LOAD_CONF;
		if (file_put_contents($conf, "<?php /*\n" . WP_LOAD_PATH_STRING . ABSPATH . "\n*/ ?>", LOCK_EX)) {
			$stat = stat(dirname(__FILE__));
			chmod($conf, 0000666 & $stat['mode']);
		}
	}
}

/* ==================================================
 * @param	string  $key
 * @return	boolean $charset
 */
public function get($key) {
	return isset($this->$key) ? $this->$key : NULL;
}

/* ==================================================
 * @param	string  $name
 * @return	mix     $value
 */
public function get_option($name, $return_default = false) {
	if (! $return_default) {
		$value = get_option($name);
		if ($value) {
			return $value;
		}
	}
	// default values 
	switch ($name) {
	case 'ke_retrieve_interval':
		return ($value === false) ? 15 : 0;
	case 'ke_thumb_size':
		if (function_exists('wp_get_attachment_link')) {
			return 'thumbnail';
		} else {
			return '160';
		}
	case 'ke_post_template':
		return KE_POST_TEMPLATE;
	default:
		return NULL;
	}
}

/* ==================================================
 * @param	none
 * @return	boolean  $elapsed
 */
static function elapsed_interval() {
	$last_checked = get_option('ke_last_checked');
	$interval     = apply_filters('retrieve_interval/ktai_entry.php', self::get_option('ke_retrieve_interval'));
	if ($interval < 1 || $last_checked < 0 || $interval * 60 > (time() - $last_checked)) {
		return false;
	}
	return true;
}

/* ==================================================
 * @param	none
 * @return	none
 */
public function add_check_messages() {
?>
<link rel="stylesheet" href="<?php echo attribute_escape($this->get('plugin_url')); ?>retrieve.php" type="text/css" />
<?php 
}

/* ==================================================
 * @param	none
 * @return	string   $url
 */
public function retrieve_url() {
	$i = ceil(time() / 43200);
	$nonce = substr(wp_hash($i . 'ktai-entry-retrieve'), -12, 10) ;
	$url = $this->get('plugin_url') . 'retrieve.php?_wpnonce=' . $nonce;
	return $url;
}

/* ==================================================
 * @param	int      $code
 * @param	string   $message
 */
public function http_error($code, $message) {
	$title = array(
		400 => 'Bad Request',
		401 => 'Unauthorized',
		402 => 'Payment Required',
		403 => 'Forbidden',
		404 => 'Not Found',
		405 => 'Method Not Allowed',
		406 => 'Not Acceptable',
		407 => 'Proxy Authentication Required',
		408 => 'Request Timeout',
		422 => 'Unprocessable Entity',
		500 => 'Internal Server Error',
		501 => 'Not Implemented',
		502 => 'Bad Gateway',
		503 => 'Service Unavailable',
		504 => 'Gateway Timeout',
	);
	$code = intval($code);
	if (! isset($title[$code])) {
		$code = 500;
	}
	$this->logging("{$title[$code]}: $message");
	$message = htmlspecialchars($message, ENT_QUOTES);
	header('Content-Type: text/html; charset=' . get_bloginfo('charset'));
	header("HTTP/1.0 $code " . $title[$code]);
	echo <<<E__O__T
<!DOCTYPE HTML PUBLIC "-//IETF//DTD HTML 2.0//EN">
<HTML><HEAD>
<TITLE>$code $title[$code]</TITLE>
</HEAD><BODY>
<H1>$title[$code]</H1>
$message
</BODY></HTML>
E__O__T;
// ?><?php /* syntax highiting fix */
	exit;
}


/* ==================================================
 * @param	string   $message
 * @return	none
 */
public function debug_print($message) {
	if (defined('KE_DEBUG') && KE_DEBUG) {
		if ($this->disp_format == 'html') {
			$this->display_as_html($message);
		} elseif ($this->disp_format == 'text') {
			$this->display_as_comment($message);
		}
		$this->logging($message);
	}
}

/* ==================================================
 * @param	string   $message
 * @return	none
 */
public function log_error($message) {
	if (defined('KE_DEBUG') && KE_DEBUG) {
		if ($this->disp_format == 'html') {
			$this->display_as_html($message);
		} elseif ($this->disp_format == 'text') {
			$this->display_as_comment($message);
		}
	}
	$this->logging($message);
}

/* ==================================================
 * @param	string   $message
 * @return	none
 */
public function display_as_html($message) {
	echo str_replace("\n", '<br />', wp_specialchars($message)) . '<br />';
}

/* ==================================================
 * @param	string   $message
 * @return	none
 */
public function display_as_comment($message) {
	$message = strtr($message, array('*/' => '* /', "\n" => "\n   "));
	echo "/* " . mb_convert_encoding($message, 'UTF-8', get_bloginfo('charset')) . " */\n";
}

/* ==================================================
 * @param	int      $post_id
 * @return	none
 */
public function notify_publish($post_id) {
	if (! $post_id) {
		return $post_id;
	}
	$admin = $this->get_admin_user();
	$post = get_post($post_id);
	$poster = new WP_User($post->post_author);
	$blogname = get_option('blogname');
	$message = __('New post on your blog.', 'ktai_entry') . "\r\n";
	$message .= sprintf(__('Title: %s', 'ktai_entry'), $post->post_title) . "\r\n";
	$message .= sprintf(__('Author: %s', 'ktai_entry'), $poster->display_name) . "\r\n\r\n";
	$message .= __('You can see the post here:', 'ktai_entry') . "\r\n";
	$message .= get_permalink($post->ID) . "\r\n";
	$message .= sprintf(__('Edit it: %s', 'ktai_entry'), get_option('siteurl') . "/wp-admin/post.php?action=edit&post=$post->ID") . "\r\n";
	$subject = sprintf(__('[%1$s] New Post: "%2$s"', 'ktai_entry'), $blogname, $post->post_title);
	$wp_email = 'wordpress@' . preg_replace('#^www\.#', '', strtolower($_SERVER['SERVER_NAME']));
	$from = "From: \"$blogname\" <$wp_email>";
	$headers = "$from\n"
	. "MIME-Version: 1.0\n"
	. "Content-Type: text/plain; charset=ISO-2022-JP\n";
	if (function_exists('mb_language')) {
		mb_language('Japanese');
		mb_internal_encoding(get_bloginfo('charset'));
		mb_send_mail($admin->user_email, $subject, $message, $headers);
	} else {
		wp_mail($admin->user_email, $subject, $message, $headers);
	}
	return $post_id;
}

/* ==================================================
 * @param	int     $user_id
 * @return	object  $user
 */
private function get_admin_user($user_id = 0) {
	$user_id = abs(intval($user_id));
	if (! $user_id) {
		global $admin_id;
		if (! $admin_id) { // check cache
			global $wpdb;
			$admin_id = $wpdb->get_var("SELECT user_id FROM `$wpdb->usermeta` WHERE meta_key = '{$wpdb->prefix}user_level' AND meta_value = 10 ORDER BY user_id ASC LIMIT 1");
		}
		$user_id = $admin_id;
	}
	return new WP_User($user_id);
}

/* ==================================================
 * @param	string   $message
 * @return	none
 */
public function logging($message) {
	if (defined('KE_LOGFILE')) {
		$logfile = dirname(__FILE__) . '/' . KE_LOGFILE;
		$existed = file_exists($logfile);
		$fh = @ fopen($logfile, 'a');
		if ($fh) {
			flock($fh, LOCK_EX);
			foreach (preg_split('/[\r\n]+/', $message) as $m) {
				fwrite($fh, date('Y-m-d H:i:s ') . "$m\n");
			}
			flock($fh, LOCK_UN);
			fclose($fh);
			if (! $existed) {
				$dir_stat = stat(dirname($logfile));
				@chmod($logfile, $dir_stat['mode'] & KE_LOGFILE_PERM);
			}
		}
	}
}

// ==================================================
public function stopped() {
	$pass = get_option('mailserver_pass');
	if ($pass && $pass != 'password') {
		update_option('ke_mailserver_pass_store', $pass);
	}
	update_option('mailserver_pass', 'password');
}

// ==================================================
public function started() {
	$pass = get_option('mailserver_pass');
	$stored = get_option('ke_mailserver_pass_store');
	if ((empty($pass) || $pass == 'password') && $stored && $stored != 'password') {
		update_option('mailserver_pass', $stored);
	}
	delete_option('ke_mailserver_pass_store');
	delete_option('ke_last_checked');
}

// ===== End of class ====================
}

/* ==================================================
 *   Ktai_Entry_PrefPane class
   ================================================== */

class Ktai_Entry_PrefPane extends Ktai_Entry {
	private $nonce = -1;

/* ==================================================
 * @param	none
 * @return	none
 */
public function add_page() {
	add_options_page('Ktai Entry Configuration', __('Email Post', 'ktai_entry'), 'manage_options', basename(__FILE__), array($this, 'option_page'));
	if ( !function_exists('wp_nonce_field') ) {
		$this->nonce = -1;
	} else {
		$this->nonce = 'ktai-entry-config';
	}
}

/* ==================================================
 * @param	none
 * @return	none
 */
public function option_page() {
	global $user_identity, $wpmu_version;
	$is_wpmu = isset($wpmu_version);

	if (isset($_POST['update_option'])) {
		check_admin_referer($this->nonce);
		$this->upate_options();
	}
	if (isset($_POST['delete_option'])) {
		check_admin_referer($this->nonce);
		$this->delete_options();
	}
	$retrieve_interval = intval($this->get_option('ke_retrieve_interval'));
	$retrive_selection = array(
		0  => __('Never', 'ktai_entry'),
		5  => __('5 min', 'ktai_entry'),
		10 => __('10 min', 'ktai_entry'),
		15 => __('15 min', 'ktai_entry'),
		20 => __('20 min', 'ktai_entry'),
		30 => __('30 min', 'ktai_entry'),
		60 => __('1 hour', 'ktai_entry'),
		120 => __('2 hour', 'ktai_entry'),
		);
	$use_apop          = $this->get_option('ke_use_apop');
	$posting_addr      = $this->get_option('ke_posting_addr');
	$thumb_max_size = $this->get_option('ke_thumb_size');
	$post_template = $this->get_option('ke_post_template');
?>
<div class="wrap">
<h2><?php _e('Ktai Entry Options', 'ktai_entry'); ?></h2>
<?php if (! $is_wpmu) { ?>
<p><?php _e('Note: To configure POP3 mail server, go <a href="options-writing.php">Writing Options</a>.', 'ktai_entry'); ?></p>
<?php } ?>
<form method="post">
<?php $this->make_nonce_field($this->nonce); ?>
<table class="optiontable form-table"><tbody>
<tr>
<?php if ($is_wpmu) { ?>
<th scope="row"><?php _e('Mail Server', 'ktai_entry') ?></th>
<td><input type="text" name="mailserver_url" id="mailserver_url" value="<?php form_option('mailserver_url'); ?>" size="40" />
<label for="mailserver_port"><?php _e('Port', 'ktai_entry') ?></label>
<input type="text" name="mailserver_port" id="mailserver_port" value="<?php form_option('mailserver_port'); ?>" size="6" />
</td>
</tr><tr>
<th scope="row"><?php _e('Login Name', 'ktai_entry') ?></th>
<td><input type="text" name="mailserver_login" id="mailserver_login" value="<?php form_option('mailserver_login'); ?>" size="40" /></td>
</tr><tr>
<th scope="row"><?php _e('Password', 'ktai_entry') ?></th>
<td>
<input type="text" name="mailserver_pass" id="mailserver_pass" value="<?php form_option('mailserver_pass'); ?>" size="40" />
</td>
</tr><tr>
<?php } ?>
<th scope="row"><?php _e('Server Option', 'ktai_entry'); ?></th>
<td><label><input type="checkbox"<?php if ($use_apop) {echo ' checked="checked"';} ?>name="use_apop" id="use_apop" /> <?php _e('Use APOP', 'ktai_entry');  ?></label></td>
</tr><tr>
<th scope="row"><label for="retrieve_interval"><?php _e('POP3 retrieve interval', 'ktai_entry'); ?></label></th>
<td><select name="retrieve_interval" id="retrieve_interval">
<?php
	$selected = false;
	foreach ($retrive_selection as $m => $n) {
		if (intval($m) == $retrieve_interval) {
			$sel_html = ' selected="selected"';
			$selected = true;
		} else {
			$sel_html = '';
		}
		echo '<option value="' . intval($m) . '"' . $sel_html . '>' . $n . "</option>\n";
	}
	if (! $selected) {
		echo '<option value="' . $retrieve_interval . '" selected="selected">' . $retrieve_interval . __('min', 'ktai_entry') . "</option>\n";
	}
?>
</select> <?php 
	$url = $this->retrieve_url();
	printf(__('<a href="%s">Retrieve messages now</a>.', 'ktai_entry'), $url, $url); ?></td>
</tr><tr>
<th scope="row"><label for="posting_addr"><?php _e('Posting mail address (option)', 'ktai_entry'); ?></label></th>
<td><input type="text" value="<?php echo attribute_escape($posting_addr); ?>" name="posting_addr" id="posting_addr" size="64" /><br />
<small><?php _e('Reject all mail whose recipients (To: fields) are not this address. DO NOTE write sender addresses.', 'ktai_entry');  ?></small></td>
</tr><tr>
<?php if (function_exists('wp_get_attachment_link')) { ?>
<th scope="row"><label for="thumb_size"><?php _e('Image size of inserting into post', 'ktai_entry'); ?></label></th>
<td>
	<label><input type="radio" name="thumb_size" value="thumbnail"<?php checked($thumb_max_size, 'thumbnail'); ?> /> <?php _e('Thumbnail'); ?></label><br />
	<label><input type="radio" name="thumb_size" value="medium"<?php checked($thumb_max_size, 'medium'); ?> /> <?php _e('Medium'); ?></label>
</td>
<?php } else { ?>
<th scope="row"><label for="thumb_size"><?php _e('Max size of the image thumbnail', 'ktai_entry'); ?></label></th>
<td><input type="text" value="<?php echo intval($thumb_max_size); ?>" name="thumb_size" id="thumb_size" /></td>
<?php } ?>
</tr><tr>
<th scope="row"><label for="post_template"><?php _e('Post template if attachment images', 'ktai_entry'); ?></label></th>
<td><textarea name="post_template" id="post_template" cols="64" rows="5" /><?php echo attribute_escape($post_template); ?></textarea><br />
<small><?php _e('{text}: Body text, {images}: Space separated image elements', 'ktai_entry');  ?></small></td>
</tr>
</tbody></table>
<p class="submit">
<input type="hidden" name="action" value="update" />
<input type="submit" name="update_option" class="button-primary" value="<?php 
if ($this->check_wp_version('2.5', '>=')) {
	_e('Save Changes');
} elseif ($this->check_wp_version('2.1', '>=')) {
	_e('Update Options &raquo;');
} else {
	echo __('Update Options') . " &raquo;";
} ?>" />
</p>
<hr />
<h3 id="delete_options"><?php _e('Delete Options', 'ktai_entry'); ?></h3>
<p class="submit">
<input type="submit" name="delete_option" value="<?php _e('Delete option values and revert them to default &raquo;', 'ktai_entry'); ?>" onclick="return confirm('<?php _e('Do you really delete option values and revert them to default?', 'ktai_entry'); ?>')" />
</p>
</form>
</div>
<?php
} 

/* ==================================================
 * @param	mix   $action
 * @return	none
 */
private function make_nonce_field($action = -1) {
	if ( !function_exists('wp_nonce_field') ) {
		return;
	} else {
		return wp_nonce_field($action);
	}
}

/* ==================================================
 * @param	none
 * @return	none
 */
private function upate_options() {
	global $wpmu_version;
	$is_wpmu = isset($wpmu_version);
	
	if ($is_wpmu) {
		if (isset($_POST['mailserver_url'])) {
			update_option('mailserver_url', stripslashes($_POST['mailserver_url']));
		}
	
		if (isset($_POST['mailserver_port'])) {
			update_option('mailserver_port', intval($_POST['mailserver_port']));
		}
	
		if (isset($_POST['mailserver_login'])) {
			update_option('mailserver_login', stripslashes($_POST['mailserver_login']));
		}
	
		if (isset($_POST['mailserver_pass'])) {
			update_option('mailserver_pass', stripslashes($_POST['mailserver_pass']));
		}
	}
	
	if (isset($_POST['use_apop'])) {
		update_option('ke_use_apop', true);
	} else {
		update_option('ke_use_apop', false);
	}

	if (isset($_POST['retrieve_interval']) && false !== $_POST['retrieve_interval']) {
		if (is_numeric($_POST['retrieve_interval']) && ($interval = intval($_POST['retrieve_interval'])) >= 0 ) {
			update_option('ke_retrieve_interval', $interval);
		}
		if ($interval == 0) {
			delete_option('ke_last_checked');
		}
	}

	if (isset($_POST['posting_addr'])) {
		update_option('ke_posting_addr', stripslashes($_POST['posting_addr']));
	} else {
		delete_option('ke_posting_addr');
	}

	if (isset($_POST['thumb_size'])) {
		if (function_exists('wp_get_attachment_link')) {
			if ($_POST['thumb_size'] == 'thumbnail' || $_POST['thumb_size'] == 'medium') {
				update_option('ke_thumb_size', stripslashes($_POST['thumb_size']));
			}
		} elseif (is_numeric($_POST['thumb_size']) && intval($_POST['thumb_size']) > 9) {
			update_option('ke_thumb_size', intval($_POST['thumb_size']));
		}
	}
	if (isset($_POST['post_template'])) {
		update_option('ke_post_template', stripslashes(str_replace("\r\n", "\n", $_POST['post_template'])));
	} else {
		delete_option('ke_post_template');
	}
?>
<div class="updated fade"><p><strong><?php _e('Options saved.'); ?></strong></p></div>
<?php
	return;
}

// ==================================================
public function delete_options() {
	delete_option('ke_last_checked');
	delete_option('ke_posting_addr');
	delete_option('ke_use_apop');
	delete_option('ke_retrieve_interval');
	delete_option('ke_thumb_size');
	delete_option('ke_post_template');
	delete_option('ke_mailserver_pass_store');
	update_option('mailserver_url', 'mail.example.com');
	update_option('mailserver_port', 110);
	update_option('mailserver_login', 'login@example.com');
	update_option('mailserver_pass', 'password');
?>
<div class="updated fade"><p><strong><?php _e('Options Deleted.', 'ktai_style'); ?></strong></p></div>
<?php
	return;
}

// ===== End of class ====================
}
?>