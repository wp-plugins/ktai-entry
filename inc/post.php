<?php
if ( !class_exists('KtaiEntry')) {
	exit;
}
// ----- Settings -------------------------
define('KTAI_STATUS_DRAFT',        'DRAFT');
define('KTAI_STATUS_PENDING',      'PENDING');
define('KTAI_STATUS_PRIVATE',      'PRIVATE');
define('KTAI_SET_POSTDATE',        'DATE:');
define('KTAI_SET_POSTSLUG',        'SLUG:');
define('KTAI_SET_CATEGORY',        'CAT:');
define('KTAI_ADD_CATEGORY',        'CAT+');
define('KTAI_CHANGE_CATEGORY',     'CAT>');
define('KTAI_ADD_CHANGE_CATEGORY', 'CAT+>');
define('KTAI_SET_TAGS',            'TAG:');
define('KTAI_ROTATE_IMAGE',        'ROT:');
define('KTAI_DELIM_STR',           '-- ');
define('KTAI_DEFAULT_POSTNAME',    'His');
define('KTAI_DEFAULT_FILENAME',    'Ymd_His');
define('KTAI_INLINE_IMAGE_CLASS',  'decoration-image');

/* ==================================================
 *   KtaiEntry_Post class
   ================================================== */

class KtaiEntry_Post {
	private $base;
	private $type;
	private $alignment_classes;
	private $operator;
	public $contents;
	public $attachments;
	const IMAGE_PERM = 0000666;
	const SUCCESS = false;
	const UNKNOWN_FATAL_ERROR = -1;
	const INVALID_RECIPIENT_ADDRESS = -2;
	const NO_SENDER_ADDRESS = -3;
	const NOT_REGISTERED_ADDRESS = -4;
	const ALREADY_POSTED = -5;
	const NOT_ALLOWED_TO_POST = -6;
	const COULDNT_POST = -7;
	const FAILED_SAVE_IMAGE = -8;
	const UNKNOWN_NOTICE = 1;
	const FAILED_UPDATE_POST = 2;

/* ==================================================
 * @param	string   $type
 * @return	object   $this
 */
public function __construct ($type, $format = 'html') {
	global $Ktai_Entry;
	$this->base = $Ktai_Entry;
	$this->type = $type;
	$this->alignment_classes = array(
		'none'   => 'alignnone',
		'left'   => 'alignleft',
		'center' => 'aligncenter',
		'right'  => 'alignright',
	);
	
	$level = error_reporting(E_ALL ^ E_WARNING ^ E_NOTICE);
	require_once ABSPATH . 'wp-admin/includes/admin.php';
//	if (! include_once 'Mail/mimeDecode.php') { // try to use PEAR in the server.
		require dirname(__FILE__) . '/Mail_mimeDecode.php'; // use local version
//	}
	error_reporting($level);

	add_filter('wp_create_thumbnail', create_function(
		'$path', 
		'$stat = stat(dirname($path));
		 chmod($path, $stat["mode"] & ' . self::IMAGE_PERM .');
		 return $path;'
	), 11);

	global $allowedposttags, $allowedtags;
	if (! defined('CUSTOM_TAGS')) {
		define('CUSTOM_TAGS', true);
	}
	if (! CUSTOM_TAGS) {
		$allowedposttags = array (
			'address' => array (), 
			'a' => array (
				'href' => array (), 'title' => array (), 'rel' => array (), 
				'rev' => array (), 'name' => array ()
				), 
			'abbr' => array ('title' => array ()), 
			'acronym' => array ('title' => array ()),
			'b' => array (),
			'big' => array (), 
			'blockquote' => array ('cite' => array ()), 
			'br' => array ('class' => array ()), 
			'button' => array (
				'disabled' => array (), 'name' => array (), 'type' => array (), 
				'value' => array ()
				), 
			'caption' => array ('align' => array ()), 
			'code' => array (), 
			'col' => array (
				'align' => array (), 'char' => array (), 'charoff' => array (), 
				'span' => array (), 'valign' => array (), 'width' => array ()
				), 
			'del' => array ('datetime' => array ()), 
			'dd' => array (), 
			'div' => array (
				'align' => array (), 'class' => array()
				), 
			'dl' => array (), 
			'dt' => array (), 
			'em' => array (), 
			'fieldset' => array (), 
			'font' => array (
				'color' => array (), 'size' => array ()
				), 
			'form' => array (
				'action' => array ('type' => 'uri'), 'accept' => array (), 
				'accept-charset' => array (), 'enctype' => array (), 
				'method' => array (), 'name' => array (), 'target' => array ()
				), 
			'h1' => array ('align' => array ()), 
			'h2' => array ('align' => array ()), 
			'h3' => array ('align' => array ()), 
			'h4' => array ('align' => array ()), 
			'h5' => array ('align' => array ()), 
			'h6' => array ('align' => array ()), 
			'hr' => array (
				'align' => array (), 'color' => array(), 'noshade' => array (), 
				'size' => array (), 'width' => array ()
				), 
			'i' => array (), 
			'img' => array (
				'alt' => array (), 'align' => array (), 'border' => array (), 
				'class' => array(), 'copyright' => array(), 'height' => array (), 
				'hspace' => array (), 'localsrc' => array (), 
				'longdesc' => array (), 'vspace' => array (), 
				'src' => array ('type' => 'uri'), 'title' => array(), 
				'vspace' => array(), 'width' => array ()
				), 
			'input' => array(
				'accesskey' => array(), 'checked' => array(), 'emptyok' => array(),
				'format' => array(), 'istyle' => array(), 'localsrc' => array(),
				'maxlength' => array(), 'mode' => array(), 'name' => array(), 
				'size' => array(), 'type' => array(), 'value' => array(),
				),
			'ins' => array (
				'datetime' => array(), 'cite' => array('type' => 'uri')
				),
			'kbd' => array (), 
			'label' => array ('for' => array ()), 
			'legend' => array ('align' => array ()), 
			'li' => array (), 
			'ol' => array(
				'start' => array(), 'type' => array()
				),
			'option' => array(
				'value' => array(), 'selected' => array()
				),
			'p' => array (
				'align' => array (), 'class' => array()
				), 
			'param' => array(
				'name' => array(), 'value' => array(), 'valuetype' => array()
				),
			'pre' => array ('width' => array ()), 
			'q' => array('cite' => array('type' => 'uri')),
			's' => array (), 
			'select' => array(
				'name' => array(), 'size' => array(), 'multiple' => array()
				),
			'strike' => array (), 
			'strong' => array (), 
			'sub' => array (), 
			'sup' => array (), 
			'table' => array (
				'align' => array (), 'bgcolor' => array (), 'border' => array (), 
				'cellpadding' => array (), 'cellspacing' => array (), 
				'rules' => array (), 'summary' => array (), 'width' => array ()
				), 
			'tbody' => array (
				'align' => array (), 'char' => array (), 'charoff' => array (), 
				'valign' => array ()), 
			'td' => array (
				'abbr' => array (), 'align' => array (), 'axis' => array (), 
				'bgcolor' => array (), 'char' => array (), 'charoff' => array (), 
				'colspan' => array (), 'headers' => array (), 'height' => array (), 
				'nowrap' => array (), 'rowspan' => array (), 'scope' => array (), 
				'valign' => array (), 'width' => array ()
				), 
			'textarea' => array (
				'cols' => array (), 'rows' => array (), 'disabled' => array (), 
				'name' => array (), 'readonly' => array ()
				), 
			'tfoot' => array (
				'align' => array (), 'char' => array (), 'charoff' => array (), 
				'valign' => array ()
				), 
			'th' => array (
				'abbr' => array (), 'align' => array (), 'axis' => array (), 
				'bgcolor' => array (), 'char' => array (), 'charoff' => array (), 
				'colspan' => array (), 'headers' => array (), 'height' => array (), 
				'nowrap' => array (), 'rowspan' => array (), 'scope' => array (), 
				'valign' => array (), 'width' => array ()
				), 
			'thead' => array (
				'align' => array (), 'char' => array (), 'charoff' => array (), 
				'valign' => array ()
				), 
			'title' => array (), 
			'tr' => array (
				'align' => array (), 'bgcolor' => array (), 'char' => array (), 
				'charoff' => array (), 'valign' => array ()
				), 
			'tt' => array (), 
			'u' => array (), 
			'ul' => array (), 
			'var' => array () 
		);

		$allowedtags = array (
			'a' => array ('href' => array (), 'title' => array ()),
			'abbr' => array ('title' => array ()),
			'acronym' => array ('title' => array ()),
			'b' => array (),
			'blockquote' => array ('cite' => array ()),
			//	'br' => array(),
			'code' => array (),
			//	'del' => array('datetime' => array()),
			'div' => array ('align' => array (), 'class' => array()), 
			//	'dd' => array(),
			//	'dl' => array(),
			//	'dt' => array(),
			'em' => array (),
			'i' => array (),
			'img' => array (
				'alt' => array (), 'align' => array (), 'border' => array (), 
				'class' => array(), 'height' => array (), 'hspace' => array (), 
				'localsrc' => array (), 'longdesc' => array (), 'vspace' => array (), 
				'src' => array (), 'title' => array (),  'width' => array ()),
			//	'ins' => array('datetime' => array(), 'cite' => array()),
			//	'li' => array(),
			//	'ol' => array(),
			'p' => array ('align' => array (), 'class' => array()), 
			//	'q' => array(),
 			'strike' => array (),
 			'strong' => array (),
			//	'sub' => array(),
			//	'sup' => array(),
			//	'u' => array(),
			//	'ul' => array(),
		);
	}
}

/* ==================================================
 * @param	string   $input
 * @return	object   $result
 */
public function parse($input) {
	global $allowedposttags, $allowedtags;

	try {
		$structure = $this->decode_message($input);
		if (PEAR::isError($structure)) {
			throw new KE_Error(sprintf('Invalid MIME structure: %s', $structure->getMessage()), self::NO_SENDER_ADDRESS);
		}
		$recipients = $this->read_recipients($structure);
		$posting_addr = $this->base->get_option('ke_posting_addr');
		if ( is_email($posting_addr) ) {
			if ( !in_array($posting_addr, $recipients) ) {
				throw new KE_Error('Invalid recipient address.', self::INVALID_RECIPIENT_ADDRESS);
			}
		}
		$from = $this->read_sender($structure);
		if ( !$from || preg_match('/^MAILER-DAEMON@/i', $from) ) {
			throw new KE_Error('No sender address found.', self::NO_SENDER_ADDRESS);
		}
		$post_author = $this->validate_address($from);
		if (! $post_author) {
			throw new KE_Error("Sender address is not registered: $from", self::NOT_REGISTERED_ADDRESS);
		}
		$post_time_gmt = @strtotime(trim($structure->headers['date']));
		if ($post_time_gmt <= 0) {
			throw new KE_Error('There is no Date: field.');
		} elseif ($this->check_duplication_by_time($post_time_gmt)) {
			throw new KE_Error(sprintf('The mail at "%s" was already posted.', $structure->headers['date']), self::ALREADY_POSTED);
		}
		$this->select_operator($from, $recipients);
		$this->contents = $this->base->encode->get_mime_parts($structure);

		$this->base->debug_print(sprintf(__('Text %1$d bytes, Attachment %2$d part(s)', 'ktai_entry_log'), strlen($this->contents->text), count($this->contents->images)));
		$this->contents->from            = $from;
		$this->contents->post_author     = $post_author;
		$this->contents->post_time_gmt   = $post_time_gmt;
		$post_title = xmlrpc_getposttitle($this->contents->text);
		if (! $post_title) {
			$subject = $this->base->encode->decode_header($structure->headers['subject'], $structure->ctype_parameters, 'subject');
			$post_title = trim(str_replace(get_option('subjectprefix'), '', $subject));
		}
		$this->contents->post_title = $post_title;
		return false;

	} catch (KE_Error $e) {
		return $e;
	}
}

/* ==================================================
 * @param	none
 * @return	int      $status
 * based on wp-mail.php of WordPress 2.0.5
 */
public function insert() {
	try {
		$post = get_default_post_to_edit();
		$this->chop_signature();
		$status = $this->decide_status();
		if (! $status) {
			throw new KE_Error('You are not allowed to post.', self::NOT_ALLOWED_TO_POST);
		}
		if (count($this->contents->images)) {
			$post_status  = 'draft';
		} else {
			$post_status  = $status;
		}
		list($post_time_gmt, $image_num, $date_string) = $this->decide_postdate();
		if ($post_time_gmt >= 86400) {
			$post_time = $post_time_gmt + get_option('gmt_offset') * 3600;
			if ($this->check_duplication_by_time($post_time_gmt)) {
				throw new KE_Error(sprintf('There is a post for specified date "%s".', $date_string), self::ALREADY_POSTED);
			}
		} else {
			$post_time_gmt = $this->contents->post_time_gmt;
			$post_time     = $post_time_gmt + get_option('gmt_offset') * 3600;
		}
		$post_date_gmt  = gmdate('Y-m-d H:i:s', $post_time_gmt);
		$post_date      = gmdate('Y-m-d H:i:s', $post_time);
		$post_category  = $this->decide_category();
		$tags_input     = $this->decide_keywords();
		$rotations      = $this->decide_rotations();
		$post_title     = $this->contents->post_title;
		$post_author    = $this->contents->post_author;
		$post_name = $post_name_assign = $this->decide_postname();
		if (empty($post_name)) {
			$post_name = gmdate(KTAI_DEFAULT_POSTNAME, $post_time);
		}
		$comment_status = $post->comment_status;
		$ping_status    = $post->ping_status;
		$post_content   = apply_filters('phone_content', $this->contents->text);
		$post_data = compact('post_title', 'post_name', 'post_date', 'post_date_gmt', 'post_author', 'post_category', 'tags_input', 'post_status', 'comment_status', 'ping_status', 'post_content');
		if ( $post_data['post_status'] == 'publish' && strlen($post_data['post_content']) >= 1 ) {
			$dup_post = $this->check_duplication_by_content($post_data['post_content']);
			if ($dup_post) {
				throw new KE_Error(sprintf('There is a post #%d with the same content.', $dup_post), self::ALREADY_POSTED);
			}
		}
		if (defined('KTAI_ENTRY_DEBUG') && KTAI_ENTRY_DEBUG) {
			$poster_info = get_userdata($post_author);
			$log  =  sprintf(__('Author  : %1$s (ID: %2$d)', 'ktai_entry_log'), $poster_info->user_nicename, $post_data['post_author']) . "\n";
			$log .= __('Date    : ', 'ktai_entry_log') . $post_data['post_date'] . "\n";
			$log .= __('Date GMT: ', 'ktai_entry_log') . $post_data['post_date_gmt'] . "\n";
			$log .= __('Title   : ', 'ktai_entry_log') . $post_data['post_title'] . "\n";
			$log .= __('+-- Content -------------------', 'ktai_entry_log') . "\n";			
			$log .= preg_replace('/^/m', '|', $post_data['post_content']);
			$log .= "\n+------------------------------";
			$this->base->debug_print($log);
		}
	
		$post_data_quoted = add_magic_quotes($post_data);
		$post_ID = wp_insert_post($post_data_quoted);
		if (! $post_ID || is_wp_error($post_ID)) {
			throw new KE_Error("We couldn't post, for whatever reason.", self::COULDNT_POST);
		}
		$post_data['ID'] = $post_ID;
		$this->base->debug_print(sprintf(__('Inserted a post with ID: %1$d, status: %2$s', 'ktai_entry_log'), $post_ID, $post_status));

		if (count($this->contents->images)) {
			$this->attachments = $this->upload_images($rotations, $post_ID, $post_time);
			$post_data['post_status'] = $status;
			if ($this->attachments) {
				if ($image_num) {
					$this->postdate_from_image($post_data, $image_num, $post_name_assign);
				}
				$post_content = $this->images_to_html($this->contents->text);
				$post_data['post_content'] = apply_filters('phone_content', $post_content);
				$dup_post = $this->check_duplication_by_content($post_data['post_content']);
				if ($dup_post) {
					$this->delete_post($post_data['ID'], array_keys( (array) $this->attachments));
					throw new KE_Error(sprintf('There is a post #%d with the same content.', $dup_post), self::ALREADY_POSTED);
				}
				if (defined('KTAI_ENTRY_DEBUG') && KTAI_ENTRY_DEBUG) {
					$log =    "+-- Content w/images ----------\n";			
					$log .= preg_replace('/^/m', '|', $post_data['post_content']);
					$log .= "\n+------------------------------";
					$this->base->debug_print($log);
				}
			}
			$post_data_quoted = add_magic_quotes($post_data);
			$result = wp_update_post($post_data_quoted);
			if (! $result || is_wp_error($result)) {
				throw new KE_Error(sprintf('Failed updating the new post #%1$d with %2$d image(s).', $post_data['ID'], count($this->attachments)), self::FAILED_UPDATE_POST);
			}
			$this->base->debug_print(sprintf(__('Updated the new post to status "%1$s" with %2$d image(s).', 'ktai_entry_log'),  $status, count($this->attachments)));
		}

		if ($post_data['post_status'] == 'publish') {
			do_action('publish_phone', $post_data['ID']);
		}
		return self::SUCCESS;

	} catch (KE_Error $e) {
		return $e;
	}
}

/* ==================================================
 * @param	string   $message
 * @return	object   $structure
 */
private function decode_message($message) {
	if (preg_match('!^Content-Type: multipart/mixed;.*?boundary="?(.*?)"?$!ims', $message, $boundary, PREG_OFFSET_CAPTURE) && preg_match("/'/", $boundary[1][0])) {
		$new_boundary = preg_replace('/[\'"]/', '_',  $boundary[1][0]); // fix for EPOC Email (Nokia build-in)
		$message = substr_replace($message, $new_boundary, $boundary[1][1], strlen($new_boundary));
		$message = preg_replace('/^--' . preg_quote($boundary[1][0], '/') . '(--)?$/m', '--' . $new_boundary . '$1', $message);
	}
	$params['include_bodies'] = true;
	$params['decode_bodies']  = true;
	$params['decode_headers'] = false;
	$params['input'] = $message;
	$structure = Mail_mimeDecode::decode($params);
	return $structure;
}

/* ==================================================
 * @param	string   $field
 * @return	array    $addresses
 */
private function pickup_rfc2822_address($field) {
	$addresses = array();
	// ----- save quoted text -----
	$quoted = array();
	while (preg_match('/(?<!\\\\)("[^\\\\"]*?(\\\\.[^\\\\"]*?)*")/', $field, $q, PREG_OFFSET_CAPTURE)) {
		$field = substr_replace($field, "\376\376\376" . count($quoted) . "\376\376\376", $q[1][1], strlen($q[1][0]));
		$quoted[] = $q[1][0];
		if (count($quoted) > 9999) { // infinity loop check
			break;
		}
	}
	// ---- remove comments -----
	do {
		$orig_field = $field;
		$field = preg_replace('/(?<!\\\\)\([^\\\\()]*?(\\\\.[^\\\\()]*?)*\)/', '', $field, -1);
	} while (strcmp($orig_field, $field) !== 0);
	// ----- remove group name -----
	$field = preg_replace('/[-\w ]+:([^;]*);/', '$1', $field);
	// ----- split into each address -----
	foreach (explode(',', $field) as $a) {
		$a = str_replace(' ', '', $a);
		preg_match('/<([^>]*)>/', $a, $m);
		if (isset($m[1]) && $m[1]) {
			$a = $m[1];
		}
		// ----- restore quoted text -----
		$a = preg_replace('/\376\376\376(\d+)\376\376\376/e', '$quoted[$1]', $a);
		// ----- got address -----
		if ($a) {
			$addresses[] = $a;
		}
	}
	return $addresses;
}

/* ==================================================
 * @param	object   $structure
 * @return	string   $sender
 */
private function read_sender($structure) {
	$senders = $this->pickup_rfc2822_address(trim($structure->headers['from']));
	$sender = $senders[0];
	if (! $sender) {
		$senders = $this->pickup_rfc2822_address($_ENV['SENDER']);
		$sender = $senders[0];
	}
	return $sender;
}

/* ==================================================
 * @param	object   $structure
 * @return	array    $recipients
 */
private function read_recipients($structure) {
	$recipients = $this->pickup_rfc2822_address(trim($structure->headers['to'])) 
	            + $this->pickup_rfc2822_address(trim($structure->headers['cc']));
	return $recipients;
}

/* ==================================================
 * @param	string   $address
 * @return	int      $user_id
 */
private function validate_address($address) {
	$user_id = 0;
	$user = get_user_by_email($address);
	if ($user) {
		$user_id = $user->ID;
	}
	$user_id = apply_filters('ktai_validate_address', $user_id, $address);
	if (! $user_id) {
		return NULL;
	}
	return $user_id;
}

/* ==================================================
 * @param	string   $sender
 * @param	array    $recipients
 * @return	none
 */
private function select_operator($sender, $recipients) {
	require_once dirname(__FILE__) . '/operators.php';
	$this->operator = KtaiEntry_Operator::factory($sender, $recipients);
	$this->base->debug_print(sprintf(__('1 message from %1$s, Pictogram type: %2$s', 'ktai_entry_log'), $sender, $this->operator->pictogram_type));
	return;
}

/* ==================================================
 * @param	string   $post_time_gmt
 * @return	int      $ID
 */
private function check_duplication_by_time($post_time_gmt) {
	global $wpdb;
	$datetime = gmdate('Y-m-d H:i:s', $post_time_gmt);
	$post_id = $wpdb->get_var($wpdb->prepare("SELECT ID FROM `{$wpdb->posts}` WHERE post_status = 'publish' AND post_date_gmt = %s LIMIT 1", $datetime));
	return $post_id;
}

/* ==================================================
 * @param	string   $content4sql (db-quoted)
 * @return	int      $ID
 */
private function check_duplication_by_content($content) {
	global $wpdb;
	$post_id = $wpdb->get_var($wpdb->prepare("SELECT ID FROM `{$wpdb->posts}` WHERE post_status = 'publish' AND post_content = %s LIMIT 1", $content));
	return $post_id;
}

/* ==================================================
 * @param	none
 * @return	array    $categories
 */
private function decide_category() {
	$categories = array();
	if (preg_match('/^((' . preg_quote(KTAI_SET_CATEGORY, '/') . 
	                ')|(' . preg_quote(KTAI_ADD_CATEGORY, '/') . 
	                ')|(' . preg_quote(KTAI_CHANGE_CATEGORY, '/') . 
	                ')|(' . preg_quote(KTAI_ADD_CHANGE_CATEGORY, '/') . 
	                 '))(.*)$/m', $this->contents->text, $c)) {
		$new_default = 0;
		$this->contents->text = trim(preg_replace('/^' . preg_quote($c[0], '/') . '[ \t\r]*(\n|\z)/m', '', $this->contents->text, 1));
		$categories = $this->cat_name2id($c[6]);
		if (isset($c[4]) && $c[4] || isset($c[5]) && $c[5]) {
			$new_default = $categories[0];
		}
		if (isset($c[3]) && $c[3] || isset($c[5]) && $c[5]) {
			array_unshift($categories, get_option('default_email_category'));
		}
		if ($new_default) {
			update_option('default_email_category', $new_default);
		}
	}
	if (count($categories) < 1) {
		$categories[] = get_option('default_email_category');
	}
	$categories = apply_filters('ktai_post_category', $categories);
	$this->base->debug_print(sprintf(__('Category: %s', 'ktai_entry_log'), implode(', ', array_map('get_catname', $categories))));
	return $categories;
}

/* ==================================================
 * @param	string   $cat_names
 * @return	array    $categories
 */
private function cat_name2id($cat_names) {
	$categories = array();
	foreach (explode(',', $cat_names) as $c) {
		$c = trim($c);
		if (is_numeric($c)) {
			$c = intval($c);
		} else {
			$cat = get_category_by_slug($c);
			if ($cat) {
				$c = $cat->cat_ID;
			} else {
				$c = get_cat_ID($c);
			}
		}
		if ($c) {
			$categories[] = $c;
		}
	}
	if (count($categories)) {
		$this->base->debug_print(sprintf(__('Assign cats: "%1$s" -> %2$s', 'ktai_entry_log'), $cat_names, implode(',',$categories)));
	} else {
		$this->base->debug_print(sprintf(__('No categories found from: "%s"', 'ktai_entry_log'), $cat_names));
	}
	return $categories;
}

/* ==================================================
 * @param	none
 * @return	string   $keywords
 */
private function decide_keywords() {
	$keywords = '';
	if (preg_match('/^' . preg_quote(KTAI_SET_TAGS, '/') . '(.*)$/m', $this->contents->text, $k)) {
		$keywords = trim($k[1]);
		$this->contents->text = trim(preg_replace('/^' . preg_quote($k[0], '/') . '[ \t\r]*(\n|\z)/m', '', $this->contents->text, 1));
		$keywords = apply_filters('ktai_post_keywords', $keywords);
		$this->base->debug_print(sprintf(__('Tags: "%s"', 'ktai_entry_log'), $keywords));
	}
	return $keywords;
}

/* ==================================================
 * @param	none
 * @return	array    $rotations
 */
private function decide_rotations() {
	if (preg_match('/^' . preg_quote(KTAI_ROTATE_IMAGE) . '(.*)$/m', $this->contents->text, $r) ) {
		$this->contents->text = trim(preg_replace('/^' . preg_quote($r[0], '/') . '[ \t\r]*(\n|\z)/m', '', $this->contents->text, 1));
		$rot_direction = strtoupper($r[1]);
	} else {
		$rot_direction = '';
	}
	$rotations = $this->parse_rotation($rot_direction, count($this->contents->images));
	$rotations = apply_filters('ktai_image_rotate', $rotations, $rot_direction, $this->contents->images);
	if (isset($rotations) && count($rotations)) {
		$this->base->debug_print(sprintf(__('Rotation: %s', 'ktai_entry_log'), implode(',', $rotations)));
	}
	return $rotations;
}

/* ==================================================
 * @param	string   $rotations
 * @param	int      $num_images
 * @return	array    $rotations
 */
private function parse_rotation($rot_desc, $num_images) {
	if ($num_images < 1) {
		return NULL;
	}
	$rot_desc = trim($rot_desc);
	// ----- Single 'L' or 'R' means rotating all image to the same direction.
	if ($rot_desc == 'L' || $rot_desc == 'R' || $rot_desc == 'N') {
		$rotations = array_fill(0, $num_images, $rot_desc);
	// ----- Continuous of 'N', 'L', or 'R' string means rotating each image to such direction.
	} elseif (preg_match('/^[NLR]+$/', $rot_desc)) {
		$rotations = str_split($rot_desc) + array_fill(0, $num_images, 'N');
	// ----- Number and 'L'/'R' means to rotate the numbered images to the desired direction.
	} elseif (preg_match('/^(\d+[LR])+/', $rot_desc)) {
		$rotations = array_fill(0, $num_images, 'N');
		preg_match_all('/(\d+)([LR])/', $rot_desc, $rot, PREG_SET_ORDER);
		foreach ($rot as $r) {
			$rotations[$r[1] -1] = $r[2];
		}
	// ----- Default is no rotation.
	} else {
		$rotations = array_fill(0, $num_images, 'N');
	}
	return $rotations;
}

/* ==================================================
 * @param	none
 * @return	string   $post_name
 */
private function decide_postname() {
	$post_name = '';
	if (preg_match('/^' . preg_quote(KTAI_SET_POSTSLUG, '/') . '(.*)$/m', $this->contents->text, $p)) {
		$post_name = trim($p[1]);
		$this->contents->text = trim(preg_replace('/^' . preg_quote($p[0], '/') . '[ \t\r]*(\n|\z)/m', '', $this->contents->text, 1));
	}
	$post_name = apply_filters('ktai_post_name', $post_name, $this->contents);
	if ($post_name) {
		$this->base->debug_print(sprintf(__('Post slug: "%s"', 'ktai_entry_log'), $post_name));
	}
	return $post_name;
}

/* ==================================================
 * @param	none
 * @return	int      $post_time_gmt
 * @return	int      $image_num
 * @return	string   $date_string
 */
private function decide_postdate() {
	$post_time_gmt = NULL;
	$image_num     = NULL;
	$date_string   = '';
	if (preg_match('/^' . preg_quote(KTAI_SET_POSTDATE, '/') . '(.*)$/m', $this->contents->text, $p)) {
		$date_string = trim($p[1]);
		$this->contents->text = trim(preg_replace('/^' . preg_quote($p[0], '/') . '[ \t\r]*(\n|\z)/m', '', $this->contents->text, 1));
	}
	$date_string = apply_filters('ktai_post_date', $date_string, $this->contents->images);
	if ($date_string) {
		if (is_numeric($date_string)) {
			$image_num = intval($date_string);
			if ($image_num > 0 && $image_num <= count($this->contents->images)) {
				$this->base->debug_print(sprintf(__('Decide post date by image #%d', 'ktai_entry_log'), $image_num));
			} else {
				$image_num = NULL;
			}
		} else {
			$post_time_gmt = @strtotime($date_string);
			if ($post_time_gmt >= 86400) {
				$this->base->debug_print(sprintf(__('Post date: "%s"', 'ktai_entry_log'), gmdate('Y-m-d H:i:s', $post_time_gmt)));
			} else {
				$post_time_gmt = NULL;
				$this->base->debug_print(sprintf(__('Invalid DATE command: "%s"', 'ktai_entry_log'), $date_string));
			}
		}
	}
	return array($post_time_gmt, $image_num, $date_string);
}

/* ==================================================
 * @param	none
 * @return	string   $status
 */
private function decide_status() {
	$user = set_current_user($this->contents->post_author);
	if (current_user_can('publish_posts')) {
		$status = 'publish';
	} elseif (current_user_can('edit_posts')) {
		$status = 'pending';
	} else {
		$status = NULL;
	}

	$status = apply_filters('ktai_post_status', $status, true, $this->contents->post_author, $this->contents->from);
	$available = array('publish' => 1, 'pending' => 1, 'draft' => 1, 'private' => 1);
	if (empty($status) || ! isset($available[$status])) {
		$status = NULL;
	}

	if (preg_match('/^' . preg_quote(KTAI_STATUS_PRIVATE) . '$/m', $this->contents->text, $s) ) {
		$this->contents->text = trim(preg_replace('/^' . preg_quote($s[0]) . '[ \t\r]*(\n|\z)/m', '', $this->contents->text, 1));
		$status = $status ? 'private' : $status;
	} elseif (preg_match('/^' . preg_quote(KTAI_STATUS_DRAFT) . '$/m', $this->contents->text, $s) ) {
		$this->contents->text = trim(preg_replace('/^' . preg_quote($s[0]) . '[ \t\r]*(\n|\z)/m', '', $this->contents->text, 1));
		$status = $status ? 'draft' : $status;
	} elseif (preg_match('/^' . preg_quote(KTAI_STATUS_PENDING) . '$/m', $this->contents->text, $s) ) {
		$this->contents->text = trim(preg_replace('/^' . preg_quote($s[0]) . '[ \t\r]*(\n|\z)/m', '', $this->contents->text, 1));
		$status = ($status == 'publish') ? 'pending' : $status;
	}
	$this->base->debug_print(sprintf(__('Status: %s', 'ktai_entry_log'), $status ? $status : __('(N/A)', 'ktai_entry_log')));
	return $status;
}

/* ==================================================
 * @param	none
 * @return	none
 */
private function chop_signature() {
	if (defined('KTAI_DELIM_STR')) {
		$text = $this->contents->text;
		$sig_match = strripos($text, "\n" . KTAI_DELIM_STR);
		if ($sig_match > 0) {
			$text = substr($text, 0, $sig_match);
			$this->base->debug_print(sprintf(__('Signature chopped at byte position: %d', 'ktai_entry_log'), $sig_match));
		}
		$this->contents->text = $text;
	}
	return;
}

/* ==================================================
 * @param	array    $rotations
 * @param	int      $post_id
 * @param	int      $post_time
 * @return	array    $attachments
 * based on wp_handle_upload() at wp-admin/includes/file.php of WP 2.5
 */
private function upload_images($rotations, $post_id = 0, $post_time) {
	if (count($this->contents->images) < 1) {
		return array();
	}
	if (! function_exists('imagecreatefromstring')) {
		$this->log_error(__('GD not available.', 'ktai_entry_log'));
		return array();
	}
	$attachments = array();
	foreach ($this->contents->images as $count => $img) {
		if ( ! ( ( $uploads = wp_upload_dir() ) && false === $uploads['error'] ) ) {
			$this->log_error(@$uploads['error']);
			return array();
		}
		$filename = $this->unique_filename($uploads['path'], $img['name'], $post_time);
		$new_file = $uploads['path'] . '/' . $filename;
		$this->base->debug_print(sprintf(__('Saving file: %s', 'ktai_entry_log'), $new_file));
		$result = $this->save_image($new_file, $img['s_type'], $img['body'], @$rotations[$count]);
		if (is_ke_error($result)) {
			$this->log_error($result->getMessage());
			return $attachments;
		}
		$url = $uploads['url'] . "/$filename";
		$file = apply_filters('wp_handle_upload', array(
			'file' => $new_file, 
			'url'  => $url, 
			'type' => $img['p_type'] . '/' . $img['s_type'],
		));

		$url  = $file['url'];
		$type = $file['type'];
		$file = $file['file'];
		$title = preg_replace('/\.[^.]+$/', '', basename($file));
		$content = '';

		$image_meta = @wp_read_image_metadata($file);
		if ($image_meta) {
			if ( trim($image_meta['title']) )
				$title = $image_meta['title'];
			if ( trim($image_meta['caption']) )
				$content = $image_meta['caption'];
		}

		$attachment = array(
			'post_mime_type' => $type,
			'guid'           => $url,
			'post_parent'    => $post_id,
			'post_title'     => $title,
			'post_content'   => $content,
			'post_excerpt'   => ($content ? $content : basename($file)),
		);

		$id = wp_insert_attachment($attachment, $file, $post_id);
		if (is_wp_error($id) || $id <= 0) {
			$this->log_error(sprintf(__('Failed inserting attachment for post # %1$d: %2$s', 'ktai_entry_log'), $post_id,  $file));
		} else {
			wp_update_attachment_metadata($id, wp_generate_attachment_metadata($id, $file));
			$this->base->debug_print(sprintf(__('Inserted attachment: #%1$d for post #%2$d', 'ktai_entry_log'), $id, $post_id));
			$attachments[$id] = array(
				'file' => $file,
				'cid'  => $img['cid'],
				'pos'  => $img['pos'],
			);
		}
	}
	unset($this->contents->images);
	return $attachments;
}

/* ==================================================
 * @param	string   $dir
 * @param	string   $filepath
 * @param	string   $new_file
 */
// If $file is exists, change filename to $file_2, $file_3, ...
private function unique_filename($dir, $filename, $post_time) {
	$parts = pathinfo($filename);
	$ext = $parts['extension'];
	if ( isset($parts['filename']) ) { // PHP 5.2 and later
		$name = $parts['filename'];
	} else {
		$name = preg_replace('/\.' . preg_quote($ext, '/') . '$/', '', $parts['basename']);
	}
	if ( $name == 'image' || $name == 'photo' 
	  || $name == __('image', 'ktai_entry') || $name == __('photo', 'ktai_entry') ) {
		$new_name = gmdate(KTAI_DEFAULT_FILENAME, $post_time);
		$this->base->debug_print(sprintf(__('Replaced the filename into %s', 'ktai_entry_log'), $name));
	} else {
		$new_name = preg_replace(
			array('/ /', '/[^-_~+a-zA-Z0-9]/'),
			array( '_' , ''), 
			$name);
		if ( !preg_match('/[0-9a-zA-Z]/', $new_name) ) {
			$new_name = md5($name);
			$this->base->debug_print(sprintf(__('Replaced the filename into %s', 'ktai_entry_log'), $new_name));
		}
	}
	$count = '';
	while (file_exists("$dir/$new_name$count.$ext")) {
		$count = $count ? preg_replace('/(\d+)/e', "intval('$1') + 1", $count) : "_2";
	}
	return "$new_name$count.$ext";
}

/* ==================================================
 * @param	string   $filepath
 * @param	string   $type
 * @param	string   $image_string
 * @param	string   $rotation
 * @return	boolean  $result
 */
private function save_image($filepath, $type, $image_string, $rotation) {
	try {
		$image  = imagecreatefromstring($image_string);
		if (! $image) {
			throw new KE_Error(sprintf(__('Invalid image resource for file: %s', 'ktai_entry_log'), $filepath));
		}
		$width  = imagesx($image);
		$height = imagesy($image);
		if ($rotation != 'L' && $rotation != 'R') {
			$fp = @fopen($filepath, 'w');
			if (! $fp) {
				throw new KE_Error(sprintf(__("Can't create a file: %s", 'ktai_entry_log'), $filepath));
			}
			if (! fwrite($fp, $image_string)) {
				@flose($fp);
				@unlink($filepath);
				throw new KE_Error(sprintf(__("Can't write to file: %s", 'ktai_entry_log'), $filepath));
			}
			if (! fclose($fp)) {
				@unlink($filepath);
				throw new KE_Error(sprintf(__("Can't close the file: %s", 'ktai_entry_log'), $filepath));
			}
			$dir_stat = stat(dirname($filepath));
			if (! chmod($filepath, $dir_stat['mode'] & self::IMAGE_PERM)) {
				@unlink($filepath);
				throw new KE_Error(sprintf(__("Can't chmod the file: %s", 'ktai_entry_log'), $filepath));
			}
			$imagesize = getimagesize($filepath);
			$mimetype = preg_replace('!^.*/!', '', image_type_to_mime_type($imagesize[2]));
			if (strtolower($type) != $mimetype) {
				@unlink($filepath);
				throw new KE_Error(sprintf(__('Invalid image type "%1$s" for file: %2$s', 'ktai_entry_log'), $mimetype, $filepath));
			}
			$this->base->debug_print(sprintf(__('Image without rotation: %1$dx%2$d type:%3$s', 'ktai_entry_log'), $width, $height, $type));
		} else {
			$rotated = $this->rotate_image($image, $type, $rotation, $filepath);
			if (is_ke_error($rotated)) {
				return $rotated;
			}
			$dir_stat = stat(dirname($filepath));
			if (! chmod($filepath, $dir_stat['mode'] & self::IMAGE_PERM)) {
				throw new KE_Error(sprintf(__("Can't chmod the file: %s", 'ktai_entry_log'), $filepath));
			}
			imagedestroy($rotated);
			$this->base->debug_print(sprintf(__('Image with rotation(%1$s): %2$dx%3$d type:%4$s', 'ktai_entry_log'), $rotation, $width, $height, $type));
		}
		imagedestroy($image);
		return self::SUCCESS;

	} catch (KE_Error $e) {
		$e->setCode(self::FAILED_SAVE_IMAGE);
		return $e;
	}
}

/* ==================================================
 * @param	resource $image
 * @param	string   $type
 * @param	string   $direction
 * @param	string   $filepath
 * @return	resource $rotated
 */
private function rotate_image($image, $type, $direction, $filepath) {	
	$angle = $direction == 'L' ? 90: 270;
	$rotated = imagerotate($image, $angle, 0);
	switch (strtolower($type)) {
	case 'gif':
		$result = imagegif($rotated, $filepath);
		break;
	case 'png':
		$result = imagepng($rotated, $filepath);
		break;
	case 'jpeg':
	default:
		$result = imagejpeg($rotated, $filepath);
		break;
	}
	if (! $result || ! file_exists($filepath)) {
		return new KE_Error(sprintf(__("Can't write rotated image: %s", 'ktai_entry_log'), $filepath));
	}
	return $rotated;
}

/* ==================================================
 * @param	array    $post_data
 * @param	int      $image_num
 * @param	string   $post_name_assign
 * @return	none
 */
private function postdate_from_image(&$post_data, $image_num, $post_name_assign) {
	$img = array_slice($this->attachments, $image_num -1 , 1);
	$timestamp = NULL;

	// Read the date and time from EXIF
	if (function_exists('exif_read_data')) {
		$exif = exif_read_data($img[0]['file'], 'FILE');
		if (isset($exif['DateTimeOriginal']) && ($timestamp = @strtotime($exif['DateTimeOriginal'])) > 0) {
			$this->set_post_date($post_data, $timestamp, array_keys((array) $this->attachments), $image_num, $post_name_assign);
			$this->base->debug_print(sprintf(__('Post date "%1$s" by EXIF of image: %2$s', 'ktai_entry_log'), $post_data['post_date'], $img[0]['name']));
		}
	} else {
		$this->log_error(__('EXIF functions not available.', 'ktai_entry_log'));
	}

	// Read the date and time from the filename
	$filename = preg_replace('/\.[^.]*$/', '', basename($img[0]['file']));
	$century = sprintf('%02d', intval(date('Y') / 100));
	if (preg_match('/^(\d\d)(\d\d)(\d\d)_(\d\d)(\d\d)(\d\d)?$/', $filename, $t)) {
		$timestamp = mktime($t[4], $t[5], (isset($t[6]) ? $t[6] : 0), $t[2], $t[3], $t[1]);
	} elseif (preg_match('/^(\d\d)(\d\d)(\d\d)_(\d\d)(\d\d)(~(\d+)(-\d+)?)?(~\d\d)?$/', $filename, $t)) {
		$timestamp = mktime($t[4], $t[5], (isset($t[6]) ? $t[7] : 0), $t[2], $t[3], $t[1]);
	} elseif (preg_match('/^(\d\d)-(\d\d)-(\d\d)_(\d\d)-(\d\d)(~(\d\d))?$/', $filename, $t)) {
		$timestamp = mktime($t[4], $t[5], (isset($t[6]) ? $t[7] : 0), $t[2], $t[3], $t[1]);
	} elseif (preg_match('/^(' . $century . '\d\d)(\d\d)(\d\d)(\d\d)(\d\d)(\d\d)(\d?)(_\d+)?$/', $filename, $t)) {
		$timestamp = mktime($t[4], $t[5], (isset($t[7]) ? $t[6] : 0), $t[2], $t[3], $t[1]);
	}
	if ($timestamp) {
		$this->set_post_date($post_data, $timestamp, array_keys((array) $this->attachments), $image_num, $post_name_assign);
		$this->base->debug_print(sprintf(__('Post date "%1$s" by filename of image: %2$s', 'ktai_entry_log'), $post_data['post_date'], $img['name']));
	}
	return;
}

/* ==================================================
 * @param	array    $post_data
 * @param	string   $timestamp
 * @param	array    $image_ids
 * @param	int      $image_num
 * @param	string   $post_name_assign
 * @return	none
 */
private function set_post_date(&$post_data, $timestamp, $image_ids, $image_num, $post_name_assign) {
	$post_data['post_date']     = date('Y-m-d H:i:s', $timestamp);
	$post_data['post_date_gmt'] = date('Y-m-d H:i:s', $timestamp - (get_option('gmt_offset') * 3600));
	if ($this->check_duplication_by_time($timestamp)) {
		$this->delete_post($post_data['ID'], $image_ids);
		throw new KE_Error(sprintf('There is a post for specified date "%1$s" of image #%2$d.', $post_data['post_date'], $image_num), self::ALREADY_POSTED);
	}
	if (empty($post_name_assign)) {
		$post_data['post_name'] = date(KTAI_DEFAULT_POSTNAME, $timestamp);
	}
	return;
}


/* ==================================================
 * @param	string   $post_content
 * @return	string   $post_content
 */
private function images_to_html($post_content) {
	global $post;
	if (! is_array($this->attachments) || count($this->attachments) < 1) {
		return $post_content;
	}
	$image_html = array();
	$size = ($this->base->get_option('ke_thumb_size') == 'medium') ? 'medium' : 'thumbnail';
	foreach ($this->attachments as $id => $img) {
		if (! $id) continue;
		$html = wp_get_attachment_link($id, $size);
		if ( !preg_match('/href=(["\'])([^"\']*)\\1/', $html, $file) ) {
			preg_match('/src=(["\'])([^"\']*)\\1/', $html, $file);
		}
		if ( preg_match('/alt=(""|\'\')/', $html, $alt) ) {
			$html = str_replace($alt[0], 'alt="' . basename($file[2]) . '"', $html);
		} elseif ( !preg_match('/alt=/', $html) ) {
			$html = str_replace('<img ', '<img alt="' . basename($file[2]) . '" ', $html);
		}
		$html = apply_filters('ktai_image_link', $html, $id, $size);
		if ( empty($img['cid']) || !$this->replace_inline_image_link($post_content, $html, $img['cid'], $img['pos']) ) {
			$image_html[] = $html;
		}
	}
	if (count($image_html)) {
		$post_template = $this->base->get_option('ke_post_template');
		if (strpos($post_template, KTAI_TEMPLATE_IMAGES) === false) {
			$post_template .= KTAI_TEMPLATE_IMAGES;
		}
		$alignment = $this->base->get_option('ke_image_alignment');
		$trans = array(
			KTAI_TEMPLATE_TEXT      => $post_content, 
			KTAI_TEMPLATE_IMAGES    => implode(KTAI_TEMPLATE_IMAGE_SEPALATOR, $image_html),
			KTAI_TEMPLATE_ALIGNMENT => isset($this->alignment_classes[$alignment]) ? $this->alignment_classes[$alignment] : $this->alignment_classes['none'],
		);
		$post_content = apply_filters('ktai_media_to_html', strtr($post_template, $trans), $post_content, $image_html);
	}
	return $post_content;
}

/* ==================================================
 * @param	string   &$content
 * @param	string   $html
 * @param	string   $cid
 * @param	string   $pos
 * @return	boolean  $replaced
 * @since	0.9.0
 */
private function replace_inline_image_link(&$content, $html, $cid, $pos) {
	$replaced = false;
	$regex = '!<img([^<>]*?) src=([\'"])(cid:)?' . preg_quote($cid) . '\\2([^<>]*?)/?>!i'; // <?php /* syntax highting fix */
	if ( !preg_match($regex, $content) ) {
		return $replaced;
	}
	$html = preg_replace('/<img /i', '<img class="' . KTAI_INLINE_IMAGE_CLASS . '"$1$4', $html, 1);
	$content = preg_replace($regex, $html, $content, 1, $replaced);
	return $replaced;
}

/* ==================================================
 * @param	int      $post_id
 * @param   array    $attachments
 * @return	none
 */
private function delete_post($post_id, $attachments) {
	if ($attachments) {
		foreach ($attachments as $id) {
			wp_delete_attachment($id);
		}
	}
	wp_delete_post($post_id);
}

// ===== End of class ====================
}
?>