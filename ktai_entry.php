<?php
/*
Plugin Name: Ktai Entry
Plugin URI: http://wordpress.org/extend/plugins/ktai-entry/
Version: 0.9.1.2
Description: Create a new post from a mail message sent by mobile phones.
Author: IKEDA Yuriko
Author URI: http://en.yuriko.net/
Text Domain: ktai_entry 
Domain Path: /languages
*/

/*  Copyright (c) 2008-2011 IKEDA Yuriko

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

if ( defined('WP_INSTALLING') && WP_INSTALLING ) {
	return;
}
// define('KTAI_LOGFILE', 'logs/error.log');
define('KTAI_ENTRY_DEBUG', false);
define('KTAI_LOGFILE_PERM', 0000666);

define('KTAI_POST_TEMPLATE', '<div class="photo {alignment}">{images}</div>
<p>{text}</p>
<div class="clear"> </div>');
define('KTAI_TEMPLATE_TEXT', '{text}');
define('KTAI_TEMPLATE_IMAGES', '{images}');
define('KTAI_TEMPLATE_ALIGNMENT', '{alignment}');
define('KTAI_TEMPLATE_IMAGE_SEPALATOR', " ");

/* ----- Put this style into your style.css -----
.photo img {
	margin:0 4px 2px 4px;
	background:white;
	padding:3px;
	border:1px solid #999;
}
---------- */

if (! defined('WP_LOAD_CONF')) {
	define('WP_LOAD_CONF', 'wp-load-conf.php');
	define('WP_LOAD_PATH_STRING', 'WP-LOAD-PATH:');
}

/* ==================================================
 *   KtaiEntry class
   ================================================== */

class KtaiEntry {
	private	static $wp_vers = NULL;
	protected static $is_multisite;
	private $plugin_dir;
	private $plugin_url;
	public $textdomain_loaded = false;
	public $encode;
	protected $schedule;
	protected $display_format;
	protected $admin_ids;
	const DOMAIN_PATH = '/languages';
	const INCLUDES_DIR = 'inc';

/* ==================================================
 * @param	none
 * @return	object  $this
 * @since   0.7.0
 */
public function __construct() {
	// ----- Prevent launch of wp-mail.php
	if (preg_match('/^([^?]*)/', $_SERVER['REQUEST_URI'], $path) && basename($path[1], '.php') == 'wp-mail') {
		$this->http_error(403, "You don't have permission to access the URL on this server.");
		// exit;
	}	
	add_action('wp-mail.php', array($this, 'kill_wpmail')); // after WP 2.9

	$this->plugin_dir = basename(dirname(__FILE__));
	$this->plugin_url = plugins_url($this->plugin_dir . '/');
	$this->schedule = new KtaiEntry_Schedule($this);

	require dirname(__FILE__) . '/' . self::INCLUDES_DIR . '/encode.php';
	$this->encode = KtaiMailEncode::factory($this);

	if (is_admin()) {
		register_activation_hook(__FILE__, array($this, 'check_wp_load'));
		register_activation_hook(__FILE__, array($this, 'started'));
		register_deactivation_hook(__FILE__, array($this, 'stopped'));
		if ( $this->is_multisite() ) {
			add_action('activate_sitewide_plugin', array($this, 'check_wp_load'));
			add_action('activate_sitewide_plugin', array($this, 'started_sitewidely'));
			add_action('deactivate_sitewide_plugin', array($this, 'stopped_sitewidely'));
		}
	}
	if ( $this->get_option('ke_notify_publish') ) {
		add_action('publish_phone', array($this, 'notify_publish'));
	}
	add_action('plugins_loaded', array($this, 'load_textdomain'));
}

/* ==================================================
 * @param	string  $key
 * @return	boolean $charset
 * @since   0.8.6
 */
public function get($key) {
	return isset($this->$key) ? $this->$key : NULL;
}

/* ==================================================
 * @param	none
 * @return	none
 * @since   0.8.6
 */
public function load_textdomain() {
	if (! $this->textdomain_loaded) {
		load_plugin_textdomain('ktai_entry', false, $this->get('plugin_dir') . self::DOMAIN_PATH);
//		load_plugin_textdomain('ktai_entry_log', false, $this->get('plugin_dir') . self::DOMAIN_PATH);
		$this->textdomain_loaded = true;
	}
}

/* ==================================================
 * @param	string   $version
 * @param	string   $operator
 * @return	boolean  $result
 * @since	0.8.6
 */
public function check_wp_version($version, $operator = '>=') {
	if ( !isset(self::$wp_vers) ) {
		self::$wp_vers = get_bloginfo('version');
	}
	return version_compare(self::$wp_vers, $version, $operator);
}

/* ==================================================
 * @param	none
 * @return	none
 * @since   0.8.10
 */
public function check_wp_load() {
	$wp_root = dirname(dirname(dirname(dirname(__FILE__)))) . '/';
	if (! file_exists($wp_root . 'wp-load.php') && ! file_exists($wp_root . 'wp-config.php')) {
		$conf = dirname(__FILE__) . '/' . WP_LOAD_CONF;
		if (file_put_contents($conf, "<?php /*\n" . WP_LOAD_PATH_STRING . ABSPATH . "\n*/ ?>", LOCK_EX)) { // <?php /* syntax highiting fix */
			$stat = stat(dirname(__FILE__));
			chmod($conf, 0000666 & $stat['mode']);
		}
	}
}

/* ==================================================
 * @param	none
 * @return	boolean $multisite
 * @since   0.9.0
 */
public function is_multisite() {
	if ( !isset(self::$is_multisite) ) {
		if (function_exists('is_multisite')) {
			self::$is_multisite = is_multisite();
		} else {
			global $wpmu_version;
			self::$is_multisite = isset($wpmu_version) ? $wpmu_version : false;
		}
	}
	return self::$is_multisite;
}

/* ==================================================
 * @param	string  $name
 * @return	mix     $value
 * @since   0.7.0
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
		return 15;
		// break;
	case 'ke_image_alignment':
		return 'none';
		// break;
	case 'ke_thumb_size':
		return 'thumbnail';
		// break;
	case 'ke_post_template':
		return KTAI_POST_TEMPLATE;
		// break;
	default:
		return NULL;
		// break;
	}
}

/* ==================================================
 * @param	none
 * @return	none
 * @since   0.8.7
 */
public function started() {
	$pass = get_option('mailserver_pass');
	$stored = get_option('ke_mailserver_pass_store');
	if ((empty($pass) || $pass == 'password') && $stored && $stored != 'password') {
		update_option('mailserver_pass', $stored);
	}
	delete_option('ke_mailserver_pass_store');
}

/* ==================================================
 * @param	none
 * @return	none
 * @since   0.9.0
 */
public function started_sitewidely() {
	$blogs = get_blog_list(0, 'all', false);
	if (is_array($blogs)) {
		reset($blogs);
		foreach((array) $blogs as $key => $details) {
			switch_to_blog($details['blog_id']);
			$this->started();
			$this->schedule->start();
			restore_current_blog();
		}
	}
}

/* ==================================================
 * @param	none
 * @return	none
 * @since   0.8.6
 */
public function stopped() {
	$pass = get_option('mailserver_pass');
	if ($pass && $pass != 'password') {
		update_option('ke_mailserver_pass_store', $pass);
	}
	update_option('mailserver_pass', 'password');
	delete_option('ke_last_checked');
}

/* ==================================================
 * @param	none
 * @return	none
 * @since   0.9.0
 */
public function stopped_sitewidely() {
	$blogs = get_blog_list(0, 'all', false);
	if (is_array($blogs)) {
		reset($blogs);
		foreach((array) $blogs as $key => $details) {
			switch_to_blog($details['blog_id']);
			$this->stopped();
			$this->schedule->clear();
			restore_current_blog();
		}
	}
}

/* ==================================================
 * @param	none
 * @return	none
 * @since   0.9.0
 */
public function kill_wpmail() {
	$this->http_error(403, "You don't have permission to access the URL on this server.");
	// exit;
}

/* ==================================================
 * @param	none
 * @return	string   $url
 * @since   0.7.0
 */
public function retrieve_url() {
	$url = $this->get('plugin_url') . self::INCLUDES_DIR . '/retrieve.php?_wpnonce=' . $this->get_nonce();
	return $url;
}

/* ==================================================
 * @param	none
 * @return	string  $nonce
 * @since   0.9.0
 */
public function get_nonce() {
	$i = wp_nonce_tick();
	return substr(wp_hash($i . 'ktai-entry-retrieve'), -12, 10);
}

/* ==================================================
 * @param	int      $code
 * @param	string   $message
 * @since   0.8.0
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
 * @since   0.7.0
 */
public function debug_print($message) {
	if (defined('KTAI_ENTRY_DEBUG') && KTAI_ENTRY_DEBUG) {
		if ($this->display_format == 'html') {
			$this->display_as_html($message);
		} elseif ($this->display_format == 'text') {
			$this->display_as_comment($message);
		}
		$this->logging($message);
	}
}

/* ==================================================
 * @param	string   $message
 * @return	none
 * @since   0.8.6
 */
public function log_error($message) {
	if (defined('KTAI_ENTRY_DEBUG') && KTAI_ENTRY_DEBUG) {
		if ($this->display_format == 'html') {
			$this->display_as_html($message);
		} elseif ($this->display_format == 'text') {
			$this->display_as_comment($message);
		}
	}
	$this->logging($message);
}

/* ==================================================
 * @param	string   $message
 * @return	none
 * @since   0.8.1
 */
public function display_as_html($message) {
	echo str_replace("\n", '<br />', wp_specialchars($message)) . '<br />';
}

/* ==================================================
 * @param	string   $message
 * @return	none
 * @since   0.8.1
 */
public function display_as_comment($message) {
	$message = strtr($message, array('*/' => '* /', "\n" => "\n   "));
	if ( 'UTF-8' != strtoupper(get_bloginfo('charset')) ) {
		$message = $this->encode->convert($message, 'UTF-8', get_bloginfo('charset'));
	}
	echo '/* ', $message, " */\n";
}

/* ==================================================
 * @param	string   $message
 * @return	none
 * @since   0.8.0
 */
public function logging($message) {
	if (defined('KTAI_LOGFILE')) {
		$logfile = dirname(__FILE__) . '/' . KTAI_LOGFILE;
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
				@chmod($logfile, $dir_stat['mode'] & KTAI_LOGFILE_PERM);
			}
		}
	}
}

/* ==================================================
 * @param	int      $post_id
 * @return	none
 * @since   0.8.10
 */
public function notify_publish($post_id) {
	if (! $post_id) {
		return $post_id;
	}
	$notify_list = $this->get_option('ke_notify_publish');
	if (empty($notify_list)) { // backward compatible; only hooking the action
		$admin_ids = $this->get_admin_users();
		if (! $admin_ids) {
			return $post_id;
		}
		$notify_ids = array_slice($admin_ids, 0, 1);
	} else {
		$notify_ids = $this->extract_notify_admins($notify_list);
	}
	$post = get_post($post_id);
	$poster = new WP_User($post->post_author);
	$blogname = get_bloginfo('name');
	$message = __('New post on your blog.', 'ktai_entry') . "\r\n";
	$message .= sprintf(__('Title: %s', 'ktai_entry'), $post->post_title) . "\r\n";
	$message .= sprintf(__('Author: %s', 'ktai_entry'), $poster->display_name) . "\r\n\r\n";
	$message .= __('You can see the post here:', 'ktai_entry') . "\r\n";
	$message .= get_permalink($post->ID) . "\r\n";
	$message .= sprintf(__('Edit it: %s', 'ktai_entry'), admin_url("post.php?action=edit&post=$post->ID")) . "\r\n";
	$subject = sprintf(__('[%1$s] New Post: "%2$s"', 'ktai_entry'), $blogname, $post->post_title);
	$wp_email = 'wordpress@' . preg_replace('#^www\.#', '', strtolower($_SERVER['SERVER_NAME']));
	$from = "From: \"$blogname\" <$wp_email>";
	$headers = "$from\n";
	$subject = apply_filters('ktai_notify_subject', $subject, $blogname, $post, $poster);
	$message = apply_filters('ktai_notify_message', $message, $blogname, $post, $poster);
	foreach ($notify_ids as $i) {
		$admin = $this->get_admin_user($i);
		if ($admin) {
			wp_mail($admin->user_email, $subject, $message, $headers);
		}
	}
	return $post_id;
}

/* ==================================================
 * @param	string  $list
 * @return	array	$notify_ids
 * @since   0.9.0
 */
protected function extract_notify_admins($list) {
	$admin_ids = $this->get_admin_users();
	$notify_ids = array();
	foreach ((array) explode(',', $list) as $i) {
		$id = intval($i);
		if ($id < 1 || ! in_array($id, $admin_ids)) {
			continue;
		}
		$notify_ids[] = $id;
	}
	return array_unique($notify_ids);
}

/* ==================================================
 * @param	none
 * @return	array	$user_ids
 * @since   0.9.0
 */
protected function get_admin_users() {
	if ( !isset($this->admin_id) || !is_array($this->admin_ids) ) {
		global $wpdb;
		$this->admin_ids = $wpdb->get_col("SELECT user_id FROM `{$wpdb->usermeta}` WHERE meta_key = '{$wpdb->prefix}user_level' AND meta_value = 10 ORDER BY user_id ASC");
	}
	return $this->admin_ids;
}

/* ==================================================
 * @param	int		$user_id
 * @return	object  $user
 * @since   0.8.10
 */
private function get_admin_user($user_id = 0) {
	$user_id = abs(intval($user_id));
	$admin_ids = $this->get_admin_users();
	$user = null;
	if (in_array($user_id, $admin_ids)) {
		$user = new WP_User($user_id);	
	}
	return $user;
}

// ===== End of class ====================
}

/* ==================================================
 *   KtaiEntry_Schedule class
   ================================================== */

class KtaiEntry_Schedule {
	private $base;
	static private $schedules;
	const HOOK_NAME = 'ktai_entry-retrieve';

/* ==================================================
 * @since   0.9.0
 */
public function __construct($base) {
	$this->base = $base;
	$this->schedules = array();
	add_filter('cron_schedules', array($this, 'custom_schedules'));
	add_action(self::HOOK_NAME, array($this, 'do_schedule'));
	if (is_admin()) {
		register_activation_hook(__FILE__, array($this, 'start'));
		register_deactivation_hook(__FILE__, array($this, 'clear'));
	}
}

/* ==================================================
 * @param	array    $schedules
 * @return	boolean  $elapsed
 * @since   0.9.0
 */
public function custom_schedules($schedules) {
	if (empty($this->schedules)) {
		$this->base->load_textdomain();
		$this->schedules['per2min'] = array('interval' => 120, 'display' => __('Once per two minutes', 'ktai_entry'));
		$this->schedules['per5min'] = array('interval' => 300, 'display' => __('Once per five minutes', 'ktai_entry'));
		$this->schedules['per10min'] = array('interval' => 600, 'display' => __('Once per ten minutes', 'ktai_entry'));
		$this->schedules['per15min'] = array('interval' => 900, 'display' => __('Once per fifteen minutes', 'ktai_entry'));
		$this->schedules['per20min'] = array('interval' => 1200, 'display' => __('Once per twenty minutes', 'ktai_entry'));
		$this->schedules['per30min'] = array('interval' => 1800, 'display' => __('Once per half an hour', 'ktai_entry'));
		$this->schedules['per60min'] = array('interval' => 3600, 'display' => __('Once an hour', 'ktai_entry'));
	}
	return array_merge( $schedules, $this->schedules );
}

/* ==================================================
 * @param	none
 * @return	none
 * @since   0.9.0
 */
public function start() {
	$interval = intval($this->base->get_option('ke_retrieve_interval'));
	if ($interval > 0) {
		$recurrence = 'per' . $interval . 'min';
		wp_schedule_event(time(), $recurrence, self::HOOK_NAME);
	}
}

/* ==================================================
 * @param	none
 * @return	none
 * @since   0.9.0
 */
public function clear() {
	wp_clear_scheduled_hook(self::HOOK_NAME);
}

/* ==================================================
 * @param	none
 * @return	none
 * @since   0.9.0
 */
public function do_schedule() {
	$_GET['_wpnonce'] = $this->base->get_nonce();
	require_once dirname(__FILE__) . '/' . KtaiEntry::INCLUDES_DIR . '/retrieve.php';
}

// ===== End of class ====================
}

/* ==================================================
 *   KtaiEntry_Config class
   ================================================== */

class KtaiEntry_Config extends KtaiEntry {
	private $intervals;
	private $sizes;
	private $alignments;
	const OPTION_GROUP = 'ktai_entry';
	const RETRIEVE_NEVER = -1;

public function __construct() {
	add_action('admin_menu',  array($this, 'add_menu'));
	add_filter('plugin_action_links', array($this, 'add_link'), 10, 2);
	if (function_exists('register_uninstall_hook')) {
		register_uninstall_hook(__FILE__, array($this, 'delete_options'));
	}
	return parent::__construct();
}

/* ==================================================
 * @param	none
 * @return	none
 * @since   0.9.0
 */
public function add_menu() {
	add_options_page(__('Ktai Entry Configuration', 'ktai_entry'), __('Post by Email', 'ktai_entry'), 'manage_options', plugin_basename(__FILE__), array($this, 'options_page'));
	add_action('admin_init', array($this, 'register_settings'));
}

/* ==================================================
 * @param	none
 * @return	none
 * @since   0.9.0
 */
public function add_link($links, $file) {
	if ( $file == plugin_basename(__FILE__) ) {
		array_unshift($links, '<a href="' . admin_url('options-general.php?page=' . plugin_basename(__FILE__)) . '">' . __('Settings') . '</a>');
	}
	return $links;
}

/* ==================================================
 * @param	none
 * @return	none
 * @since	0.9.0
 */
function register_settings() {
	register_setting(self::OPTION_GROUP, 'ke_retrieve_interval');
	register_setting(self::OPTION_GROUP, 'ke_use_apop');
	register_setting(self::OPTION_GROUP, 'ke_posting_addr');
	register_setting(self::OPTION_GROUP, 'ke_image_alignment');
	register_setting(self::OPTION_GROUP, 'ke_thumb_size');
	register_setting(self::OPTION_GROUP, 'ke_post_template');
	register_setting(self::OPTION_GROUP, 'ke_notify_publish');
	register_setting(self::OPTION_GROUP, 'ke_mailserver_pass_store');
}

/* ==================================================
 * @param	none
 * @return	none
 * @since   0.7.0
 */
public function options_page() {
	global $user_identity;
	
	$this->intervals = array(
		self::RETRIEVE_NEVER => __('Never', 'ktai_entry'),
		2  => __('2 min', 'ktai_entry'),
		5  => __('5 min', 'ktai_entry'),
		10 => __('10 min', 'ktai_entry'),
		15 => __('15 min', 'ktai_entry'),
		20 => __('20 min', 'ktai_entry'),
		30 => __('30 min', 'ktai_entry'),
		60 => __('1 hour', 'ktai_entry'),
		);
	$this->sizes = array(
		'thumbnail' => __('Thumbnail'), 
		'medium'    => __('Medium'),
		'large'     => __('Large'), 
		'full'      => __('Full Size'),
	);
	if ($this->check_wp_version(2.7, '<')) {
		unset($this->sizes['large']);
	}
	$this->alignments = array(
		'none'   => __('None'),
		'left'   => __('Left'),
		'center' => __('Center'),
		'right'  => __('Right'),
	);

	if (isset($_POST['update_option'])) {
		check_admin_referer(self::OPTION_GROUP . '-options');
		$this->upate_options();
		?>
<div class="updated fade"><p><strong><?php _e('Options saved.'); ?></strong></p></div>
<?php
	}
	if (isset($_POST['delete_option'])) {
		check_admin_referer(self::OPTION_GROUP . '-options');
		$this->delete_options();
		?>
<div class="updated fade"><p><strong><?php _e('Options Deleted.', 'ktai_entry'); ?></strong></p></div>
<?php
	}
	$retrieve_interval = intval($this->get_option('ke_retrieve_interval'));
	$use_apop        = $this->get_option('ke_use_apop');
	$posting_addr    = $this->get_option('ke_posting_addr');
	$image_alignment = $this->get_option('ke_image_alignment');
	$thumb_max_size  = $this->get_option('ke_thumb_size');
	$post_template   = $this->get_option('ke_post_template');
	$notify_ids      = $this->extract_notify_admins($this->get_option('ke_notify_publish'));
?>
<div class="wrap">
<h2><?php _e('Ktai Entry Configuration', 'ktai_entry'); ?></h2>
<?php if ( !$this->is_multisite() ) { ?>
<p><?php _e('Note: To configure POP3 mail server, go <a href="options-writing.php">Writing Options</a>.', 'ktai_entry'); ?></p>
<?php } ?>
<form name="form" method="post" action="">
<?php if (function_exists('settings_fields')) {
	settings_fields(self::OPTION_GROUP);
} else { ?>
	<input type="hidden" name="action" value="update" />
	<?php wp_nonce_field(self::OPTION_GROUP . '-options');
} ?>
<table class="form-table"><tbody>
<tr>
<?php if ( $this->is_multisite() ) { ?>
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
<td><label><input type="checkbox" name="ke_use_apop" id="ke_use_apop"<?php checked($use_apop, true); ?> /> <?php _e('Use APOP', 'ktai_entry');  ?></label></td>
</tr><tr>
<th scope="row"><label for="ke_retrieve_interval"><?php _e('POP3 retrieve interval', 'ktai_entry'); ?></label></th>
<td><select name="ke_retrieve_interval" id="ke_retrieve_interval">
<?php
	$selected = false;
	foreach ($this->intervals as $m => $n) {
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
	printf(__('<a href="%s">Retrieve messages now</a>.', 'ktai_entry'), $url); ?></td>
</tr><tr>
<th scope="row"><label for="ke_posting_addr"><?php _e('Posting mail address (option)', 'ktai_entry'); ?></label></th>
<td><input type="text" name="ke_posting_addr" id="ke_posting_addr" value="<?php echo attribute_escape($posting_addr); ?>" size="64" /><br />
<small><?php _e('Reject all mail whose recipients (To: fields) are not this address. DO NOTE write sender addresses.', 'ktai_entry');  ?></small></td>
</tr><tr>
<th scope="row"><label for="ke_image_alignment"><?php _e('Image position of inserting into post', 'ktai_entry'); ?></label></th>
<td>
	<?php foreach ($this->alignments as $alignment => $desc) {
		?><label><input type="radio" name="ke_image_alignment" value="<?php echo attribute_escape($alignment); ?>"<?php checked($image_alignment, $alignment); ?> /> <?php echo wp_specialchars($desc); ?></label>&nbsp;&nbsp;&nbsp;<?php 
	} ?>
</td>
</tr><tr>
<th scope="row"><label for="ke_thumb_size"><?php _e('Image size of inserting into post', 'ktai_entry'); ?></label></th>
<td>
	<?php foreach ($this->sizes as $size => $desc) {
		?><label><input type="radio" name="ke_thumb_size" value="<?php echo attribute_escape($size); ?>"<?php checked($thumb_max_size, $size); ?> /> <?php echo wp_specialchars($desc); ?></label>&nbsp;&nbsp;&nbsp;<?php 
	} ?>
</td>
</tr><tr>
<th scope="row"><label for="ke_post_template"><?php _e('Post template if attachment images', 'ktai_entry'); ?></label></th>
<td><textarea name="ke_post_template" id="ke_post_template" cols="64" rows="5" /><?php echo attribute_escape($post_template); ?></textarea><br />
<small><?php _e('{text}: Body text, {images}: sequence of images, {alignment}: Alignment for images', 'ktai_entry');  ?></small></td>
</tr><tr>
<th scope="row"><label for="ke_notify_publish"><?php _e('Notify publish to admin', 'ktai_entry'); ?></label></th>
<td><?php 
	$admin_ids = $this->get_admin_users();
	foreach((array) $admin_ids as $a) {
		$user = new WP_User($a);
		if ($user) {
			?><label><input type="checkbox" name="ke_notify_publish[]" id="ke_notify_publish_<?php echo intval($a); ?>" value="<?php echo intval($a); ?>" <?php checked(in_array($a, $notify_ids), true); ?>/><?php printf('%1$s (%2$s)', attribute_escape($user->user_login), attribute_escape($user->display_name)); ?></label><br /><?php 
		}
	}
?></td>
</tr>
</tbody></table>
<p class="submit">
<input type="submit" name="update_option" class="button-primary" value="<?php _e('Save Changes'); ?>" />
</p>
</form>
<hr />
<h3 id="delete_options"><?php _e('Delete Options', 'ktai_entry'); ?></h3>
<form method="post" action="" />
<p class="submit">
<input type="submit" name="delete_option" value="<?php _e('Delete option values and revert them to default &raquo;', 'ktai_entry'); ?>" onclick="return confirm('<?php _e('Do you really delete option values and revert them to default?', 'ktai_entry'); ?>')" />
</p>
</form>
</div>
<?php
} 

/* ==================================================
 * @param	none
 * @return	none
 * @since   0.7.0
 */
private function upate_options() {
	if ( $this->is_multisite() ) {
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
	
	update_option('ke_use_apop', isset($_POST['ke_use_apop']));

	if (isset($_POST['ke_retrieve_interval']) && false !== $_POST['ke_retrieve_interval'] && is_numeric($_POST['ke_retrieve_interval'])) {
		$interval = intval($_POST['ke_retrieve_interval']);
		update_option('ke_retrieve_interval', $interval);
		$this->schedule->clear();
		if ($interval > 0) {
			$this->schedule->start();
		}
	}

	if (isset($_POST['ke_posting_addr'])) {
		$posting_addr = stripslashes($_POST['ke_posting_addr']);
		if (is_email($posting_addr)) {
			update_option('ke_posting_addr', $posting_addr);
		}
	} else {
		delete_option('ke_posting_addr');
	}

	if (isset($_POST['ke_image_alignment'])) {
		$image_alignment = stripslashes($_POST['ke_image_alignment']);
		if (isset($this->alignments[$image_alignment])) {
			update_option('ke_image_alignment', $image_alignment);
		}
	}

	if (isset($_POST['ke_thumb_size'])) {
		$thumb_size = stripslashes($_POST['ke_thumb_size']);
		if (isset($this->sizes[$thumb_size])) {
			update_option('ke_thumb_size', $thumb_size);
		}
	}

	if (isset($_POST['ke_post_template'])) {
		update_option('ke_post_template', stripslashes(str_replace("\r\n", "\n", $_POST['ke_post_template'])));
	} else {
		delete_option('ke_post_template');
	}

	if (isset($_POST['ke_notify_publish']) && is_array($_POST['ke_notify_publish']) && count($_POST['ke_notify_publish']) >= 1 ) {
		$notify_users = implode(',', $_POST['ke_notify_publish']);
		$notify_ids = $this->extract_notify_admins($notify_users);
		update_option('ke_notify_publish', implode(',', $notify_ids) );
	} else {
		delete_option('ke_notify_publish');
	}

	return;
}

/* ==================================================
 * @param	none
 * @return	none
 * @since   0.8.6
 */
public function delete_options() {
	delete_option('ke_last_checked');
	delete_option('ke_posting_addr');
	delete_option('ke_use_apop');
	delete_option('ke_retrieve_interval');
	delete_option('ke_image_alignment');
	delete_option('ke_thumb_size');
	delete_option('ke_post_template');
	delete_option('ke_mailserver_pass_store');
	update_option('mailserver_url', 'mail.example.com');
	update_option('mailserver_port', 110);
	update_option('mailserver_login', 'login@example.com');
	update_option('mailserver_pass', 'password');
	return;
}

// ===== End of class ====================
}

/* ==================================================
 *   KE_Error class
   ================================================== */

function is_ke_error($thing) {
	return (is_object($thing) && is_a($thing, 'KE_Error'));
}

class KE_Error extends Exception {

public function setCode($code) {
	$this->code = $code;
}

// ===== End of class ====================
}

global $Ktai_Entry;
if (is_admin()) {
	$Ktai_Entry = new KtaiEntry_Config();
} else {
	$Ktai_Entry = new KtaiEntry();
}
?>