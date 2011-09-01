<?php

// ----- Settings -------------------------
define('KE_DRAFT',               'DRAFT');
define('KE_PENDING',             'PENDING');
define('KE_PRIVATE',             'PRIVATE');
define('KE_SET_POSTDATE',        'DATE:');
define('KE_SET_POSTSLUG',        'SLUG:');
define('KE_SET_CATEGORY',        'CAT:');
define('KE_ADD_CATEGORY',        'CAT+');
define('KE_CHANGE_CATEGORY',     'CAT>');
define('KE_ADD_CHANGE_CATEGORY', 'CAT+>');
define('KE_SET_TAGS',            'TAG:');
define('KE_ROTATE_IMAGE',        'ROT:');
define('KE_DELIM_STR',           '-- ');

// ----- Constants -------------------------
define('KE_DETECT_ORDER', 'JIS, SJIS, UTF-8, EUC-JP');
define('KE_IMAGE_PERM', 0000666);
define('KE_SUCCESS', false);
define('KE_UNKNOWN_FATAL_ERROR', -1);
define('KE_INVALID_RECIPIENT_ADDRESS', -2);
define('KE_NO_SENDER_ADDRESS', -3);
define('KE_NOT_REGISTERED_ADDRESS', -4);
define('KE_ALREADY_POSTED', -5);
define('KE_NOT_ALLOWED_TO_POST', -6);
define('KE_COULDNT_POST', -7);
define('KE_FAILED_SAVE_IMAGE', -8);
define('KE_UNKNOWN_NOTICE', 1);
define('KE_FAILED_UPDATE_POST', 2);

/* ==================================================
 *   Ktai_Entry_Post class
   ================================================== */

class Ktai_Entry_Post extends Ktai_Entry {
	private $type;
	private $operator;

/* ==================================================
 * @param	string   $type
 * @return	object   $this
 */
public function __construct ($type, $format = 'html') {
	$this->type        = $type;
	$this->disp_format = $format;
	
	$level = error_reporting(E_ALL ^ E_WARNING ^ E_NOTICE);
	require_once ABSPATH . 'wp-admin/admin-functions.php';
	if (! include_once 'Mail/mimeDecode.php') { // try to use PEAR in the server.
		require dirname(__FILE__) . '/Mail_mimeDecode.php'; // use local version
	}
	error_reporting($level);

	// ----- Remove filters of Change Max Thumbnail Length (http://www.yuriko.net/arc/2008/03/27d)
	if (defined('MY_THUMBNAIL_MAX_SIDE_LENGTH')) {
		remove_filter('wp_thumbnail_max_side_length', array('Change_Thumb_Max_Length', 'decide_length'));
		remove_filter('wp_thumbnail_max_side_length', 'change_thumb_max_length', 10, 3);
	}
	add_filter('wp_thumbnail_max_side_length', create_function(
		'$size,$id,$file', 
		'return ' . intval($this->get_option('ke_thumb_size')) . ';'
	), 11, 3);
	add_filter('wp_create_thumbnail', create_function(
		'$path', 
		'$stat = stat(dirname($path));
		 chmod($path, $stat["mode"] & ' . KE_IMAGE_PERM .');
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
 * @return	array    $contents
 */
public function parse($input) {
	global $allowedposttags, $allowedtags;

	try {
		$structure = $this->decode_message($input);
		if (PEAR::isError($structure)) {
			throw new KE_Error(sprintf('Invalid MIME structure: %s', $structure->getMessage()), KE_NO_SENDER_ADDRESS);
		}
		if (is_email($this->get_option('ke_posting_addr'))) {
			$recipients = $this->read_recipients($structure);
			if (! in_array($this->get_option('ke_posting_addr'), $recipients)) {
				throw new KE_Error('Invalid recipient address.', KE_INVALID_RECIPIENT_ADDRESS);
			}
		}
		$from = $this->read_sender($structure);
		if (! $from || preg_match('/^MAILER-DAEMON@/i', $from)) {
			throw new KE_Error('No sender address found.', KE_NO_SENDER_ADDRESS);
		}
		$post_author = $this->validate_address($from);
		if (! $post_author) {
			throw new KE_Error("Sender address is not registered: $from", KE_NOT_REGISTERED_ADDRESS);
		}
		$post_time_gmt = @strtotime(trim($structure->headers['date']));
		if ($post_time_gmt <= 0) {
			throw new KE_Error('There is no Date: field.');
		} elseif ($this->check_duplication_by_time($post_time_gmt)) {
			throw new KE_Error(sprintf('The mail at "%s" was already posted.', $structure->headers['date']), KE_ALREADY_POSTED);
		}
		$this->select_operator($from);
		$contents = $this->get_mime_parts($structure);
		$this->debug_print(sprintf(__('Text %1$d bytes, Attachment %2$d part(s)', 'ktai_entry_log'), strlen($contents->text), count($contents->images)));
		$contents->from            = $from;
		$contents->post_author     = $post_author;
		$contents->post_time_gmt   = $post_time_gmt;
		$post_title = xmlrpc_getposttitle($content_text);
		if (! $post_title) {
			$subject = $this->decode_header($structure->headers['subject'], $structure->ctype_parameters, 'subject');
			$post_title = trim(str_replace(get_option('subjectprefix'), '', $subject));
		}
		$contents->post_title = $post_title;
		return $contents;

	} catch (KE_Error $e) {
		return $e;
	}
}

/* ==================================================
 * @param	array    $contents
 * @return	int      $status
 * based on wp-mail.php of WordPress 2.0.5
 */
public function insert($contents) {
	try {
		$post = get_default_post_to_edit();
		$this->chop_signature($contents);
		$status = $this->decide_status($contents);
		if (! $status) {
			throw new KE_Error('You are not allowed to post.', KE_NOT_ALLOWED_TO_POST);
		}
		if (count($contents->images)) {
			$post_status  = 'draft';
		} else {
			$post_status  = $status;
		}
		list($post_time_gmt, $image_num, $date_string) = $this->decide_postdate($contents);
		if ($post_time_gmt >= 86400) {
			$post_time = $post_time_gmt + get_option('gmt_offset') * 3600;
			if ($this->check_duplication_by_time($post_time_gmt)) {
				throw new KE_Error(sprintf('There is a post for specified date "%s".', $date_string), KE_ALREADY_POSTED);
			}
		} else {
			$post_time_gmt = $contents->post_time_gmt;
			$post_time     = $post_time_gmt + get_option('gmt_offset') * 3600;
		}
		$post_date_gmt  = gmdate('Y-m-d H:i:s', $post_time_gmt);
		$post_date      = gmdate('Y-m-d H:i:s', $post_time);
		$post_category  = $this->decide_category($contents);
		$tags_input     = $this->decide_keywords($contents);
		$rotations      = $this->decide_rotations($contents);
		$post_title     = $contents->post_title;
		$post_author    = $contents->post_author;
		$post_name = $post_name_assign = $this->decide_postname($contents);
		if (empty($post_name)) {
			$post_name = gmdate('His', $post_time);
		}
		$comment_status = $post->comment_status;
		$ping_status    = $post->ping_status;
		$post_content   = apply_filters('phone_content', $contents->text);
		$post_data = compact('post_title', 'post_name', 'post_date', 'post_date_gmt', 'post_author', 'post_category', 'tags_input', 'post_status', 'comment_status', 'ping_status', 'post_content');
		$post_data = add_magic_quotes($post_data);
		if ($post_data['post_status'] == 'publish' && ($result = $this->check_duplication_by_content($post_data['post_content']))) {
			throw new KE_Error(sprintf('There is a post #%d with the same content.', $result), KE_ALREADY_POSTED);
		}
		if (defined('KE_DEBUG') && KE_DEBUG) {
			$poster_info = get_userdata($post_author);
			$log  =  sprintf(__('Author  : %1$s (ID: %2$d)', 'ktai_entry_log'), $poster_info->user_nicename, $post_data['post_author']) . "\n";
			$log .= __('Date    : ', 'ktai_entry_log') . $post_data['post_date'] . "\n";
			$log .= __('Date GMT: ', 'ktai_entry_log') . $post_data['post_date_gmt'] . "\n";
			$log .= __('Title   : ', 'ktai_entry_log') . $post_data['post_title'] . "\n";
			$log .= __('+-- Content -------------------', 'ktai_entry_log') . "\n";			
			$log .= preg_replace('/^/m', '|', $post_data['post_content']);
			$log .= "\n+------------------------------";
			$this->debug_print($log);
		}
	
		$post_ID = wp_insert_post($post_data);
		if (! $post_ID || (function_exists('is_wp_error') && is_wp_error($post_ID))) {
			throw new KE_Error("We couldn't post, for whatever reason.", KE_COULDNT_POST);
		}
		$post_data['ID'] = $post_ID;
		$this->debug_print(sprintf(__('Inserted a post with ID: %1$d, status: %2$s', 'ktai_entry_log'), $post_ID, $post_status));

		if (count($contents->images)) {
			$images = $this->upload_images($contents, $rotations, $post_ID);
			$post_data['post_status'] = $status;
			if ($images) {
				if ($image_num) {
					$this->postdate_from_image($post_data, $images, $image_num, $post_name_assign);
				}
				$post_content = $this->images_to_html($contents->text, $images);
				$post_content = apply_filters('phone_content', $post_content);
				$content_array = add_magic_quotes((array) $post_content);
				$post_data['post_content'] = $content_array[0];
				$log =    "+-- Content w/images ----------\n";			
				$log .= preg_replace('/^/m', '|', $post_data['post_content']);
				$log .= "\n+------------------------------";
				$this->debug_print($log);
			}
			if ($result = $this->check_duplication_by_content($post_data['post_content'])) {
				$this->delete_post($post_data['ID'], array_keys( (array) $images));
				throw new KE_Error(sprintf('There is a post #%d with the same content.', $result), KE_ALREADY_POSTED);
			}
			$result = wp_update_post($post_data);
			if (! $result || (function_exists('is_wp_error') && is_wp_error($result))) {
				throw new KE_Error(sprintf('Failed updating the new post #%1$d with %2$d image(s).', $post_data['ID'], count($images)), KE_FAILED_UPDATE_POST);
			}
			$this->debug_print(sprintf(__('Updated the new post to status "%1$s" with %2$d image(s).', 'ktai_entry_log'),  $status, count($images)));
		}

		if ($post_data['post_status'] == 'publish') {
			do_action('publish_phone', $post_data['ID']);
		}
		return KE_SUCCESS;

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
	if (function_exists('get_user_by_email')) {
		$user = get_user_by_email($address);
		if ($user) {
			$user_id = $user->ID;
		}
	} else {
		global $wpdb;
		$email4sql = $wpdb->escape($address);
		$user_id = $wpdb->get_var("SELECT ID FROM {$wpdb->users} WHERE user_email = '$email4sql' ORDER BY ID ASC LIMIT 1");
	}
	$user_id = apply_filters('validate_address/ktai_entry.php', $user_id, $address);
	if (! $user_id) {
		return NULL;
	}
	return $user_id;
}

/* ==================================================
 * @param	string   $address
 * @return	none
 */
private function select_operator($address) {
	$is_yahoo = ($this->type == 'pop' && preg_match('/pop\.mail\.yahoo\.co\.jp$/i', get_option('mailserver_url')));
	if (preg_match('/@(ezweb\.ne|auone)\.jp$/i', $address)) {
		require_once dirname(__FILE__) . '/operators.php';
		$this->operator = new Ktai_Entry_EZweb();
		$operator = 'KDDI';
	} elseif (preg_match('/@((\w+\.)?pdx\.ne\.jp|willcom\.com)$/i', $address)) {
		require_once dirname(__FILE__) . '/operators.php';
		$this->operator = new Ktai_Entry_WILLCOM();
		$operator = 'WILLCOM';
	} elseif (preg_match('/@docomo\.ne\.jp$/i', $address)) {
		require_once dirname(__FILE__) . '/operators.php';
		if ($is_yahoo) {
			$this->operator = new Ktai_Entry_imode_ISO();
			$operator = 'DoCoMo (JIS)';
		} else {
			$this->operator = new Ktai_Entry_imode_SJIS();
			$operator = 'DoCoMo';
		}
	} elseif (preg_match('/@emnet\.ne\.jp$/i', $address)) {
		require_once dirname(__FILE__) . '/operators.php';
		$this->operator = new Ktai_Entry_EMNet();
		$operator = 'EMOBILE';
	} elseif (preg_match('/@((softbank|disney|[dhrtcknsq]\.vodafone)\.ne|i\.softbank)\.jp$/i', $address)) {
		require_once dirname(__FILE__) . '/operators.php';
		if ($is_yahoo) {
			$this->operator = new Ktai_Entry_SoftBank_ISO();
			$operator = 'SoftBank/Disney (JIS)';
		} else {
			$this->operator = new Ktai_Entry_SoftBank_SJIS();
			$operator = 'SoftBank/Disney';
		}
	} else {
		$this->operator = NULL;
		$operator = __('(N/A)', 'ktai_entry_log');
	}
	$this->debug_print(sprintf(__('1 message from %1$s, Pictogram type: %2$s', 'ktai_entry_log'), $address, $operator));
	return;
}

/* ==================================================
 * @param	string   $post_time_gmt
 * @return	int      $ID
 */
private function check_duplication_by_time($post_time_gmt) {
	global $wpdb;
	$time4sql = gmdate('Y-m-d H:i:s', $post_time_gmt);
	$result = $wpdb->get_var("SELECT ID FROM {$wpdb->posts} WHERE post_date_gmt = '$time4sql' LIMIT 1");
	return $result;
}

/* ==================================================
 * @param	string   $content4sql (db-quoted)
 * @return	int      $ID
 */
private function check_duplication_by_content($content4sql) {
	global $wpdb;
	$result = $wpdb->get_var("SELECT ID FROM {$wpdb->posts} WHERE post_status = 'publish' AND post_content = '$content4sql' LIMIT 1");
	return $result;
}

/* ==================================================
 * @param	string   $encoded
 * @param	array    $ctype
 * @return	string   $encoding
 */
private function decode_header($encoded, $ctype, $place = 'elesewhere') {
	if (preg_match('/=\?([^?]+)\?[qb]\?/ims', $encoded, $mime)) {
		$encoding = $mime[1];
		$encoded = $this->decode_mime($encoded);
	} else {
		$encoding = $this->get_charset($ctype);
		if ($encoding == 'auto') {
			$encoding = KE_DETECT_ORDER;
		}
	}
	$this->debug_print(sprintf(__('Detect %1$s encoding as "%2$s"', 'ktai_entry_log'), $place, $encoding));
	return mb_convert_encoding($encoded, get_bloginfo('charset'), $encoding);
}

/* ==================================================
 * @param	string   $input
 * @return	string   $input
 * based on _decodeHeader() at Mail_mimeDecode.php from PEAR
 */
private function decode_mime($input)
{
	// Remove white space between encoded-words
	$input = preg_replace('/(=\?[^?]+\?(q|b)\?[^?]*\?=)(\s)+=\?/i', '\1=?', $input);

	// For each encoded-word...
	while (preg_match('/(=\?([^?]+)\?(q|b)\?([^?]*)\?=)/i', $input, $matches)) {

		$encoded  = $matches[1];
		$charset  = $matches[2];
		$encoding = $matches[3];
		$text     = $matches[4];

		switch (strtolower($encoding)) {
			case 'b':
				$text = base64_decode($text);
				break;

			case 'q':
				$text = str_replace('_', ' ', $text);
				preg_match_all('/=([a-f0-9]{2})/i', $text, $matches);
				foreach($matches[1] as $value)
					$text = str_replace('='.$value, chr(hexdec($value)), $text);
				break;
		}

		$input = str_replace($encoded, $text, $input);
	}

	return $input;
}

/* ==================================================
 * @param	array    $ctype
 * @return	string   $charset
 */
private function get_charset($ctype) {
	return isset($ctype['charset']) ? $ctype['charset'] : 'auto';
}

/* ==================================================
 * @param	object   $part
 * @return	array    $contents
 */
private function get_mime_parts($part) {
	$contents = new stdClass;
	$contents->text = '';
	$contents->text_type = NULL;
	$contents->images = array();
	switch (strtolower($part->ctype_primary)) {
		case 'multipart':
			foreach ($part->parts as $p) {
				$part_content = $this->get_mime_parts($p);
				if (! $contents->text_type || $contents->text_type == $part_content->text_type) {
					$contents->text .= $part_content->text;
					$contents->text_type = $part_content->text_type;
				}
				$contents->images = array_merge($contents->images, $part_content->images);
			}
			break;
		case 'text':
			if ($part->ctype_secondary == 'plain' || $part->ctype_secondary == 'x-pmaildx') {
				if ($contents->text_type && $contents->text_type != 'plain') {
					$this->debug_print(sprintf(__('Skipped %1$s/%2$s part', 'ktai_entry_log'), $part->ctype_primary, $part->ctype_secondary));
					continue;
				}
				$text = trim($part->body);
				$contents->text_type = 'plain';
			} elseif ($part->ctype_secondary == 'html') {
				if ($contents->text_type && $contents->text_type != 'html') {
					$this->debug_print(sprintf(__('Skipped %1$s/%2$s part', 'ktai_entry_log'), $part->ctype_primary, $part->ctype_secondary));
					continue;
				}
				$text = trim(strip_tags($part->body));
				$contents->text_type = 'html';
			}
			$charset = $this->get_charset($part->ctype_parameters);
			if (is_object($this->operator)) {
				$text = $this->operator->pickup_pics($text, $charset);
			}
			if ($charset == 'auto') {
				$charset = KE_DETECT_ORDER;
			}
			$this->debug_print(sprintf(__('Detect text/%1$s part encoding as "%2$s"', 'ktai_entry_log'), $part->ctype_secondary, $charset));
			$contents->text .= mb_convert_encoding($text, get_bloginfo('charset'), $charset);
			break;
		case 'image':
			$name = $this->get_filename($part->d_parameters, $part->ctype_parameters);
			$this->debug_print(sprintf(__('Found %1$s/%2$s part with filename: %3$s', 'ktai_entry_log'), $part->ctype_primary, $part->ctype_secondary, $name));
			if (! $this->validate_extension($name, $part->ctype_primary, $part->ctype_secondary)) {
				$this->debug_print(sprintf(__('Invalid filename "%1$s" for mime type "%2$s/%3$s"', 'ktai_entry_log'), $name, $part->ctype_primary, $part->ctype_secondary));
				continue;
			}
			$contents->images[] = array(
				'name'   => $name, 
				'p_type' => strtolower($part->ctype_primary), 
				's_type' => strtolower($part->ctype_secondary), 
				'body'   => $part->body
			);
			break;
	}		
	return $contents;
}

/* ==================================================
 * @param	array    $params
 * @param	array    $ctype
 * @params	array    $headers
 * @return	string   $name
 */
private function get_filename($params, $ctype) {
	$filename = '';
	if (isset($params['filename*0']) || isset($params['filename*0*'])) {
		$sections = array();
		foreach($params as $p_name => $value) {
			if (! preg_match('/^filename\*(\d+)(\*?)$/', $p_name, $n)) {
				continue;
			}
			if (isset($n[2])) {
				$sections[intval($n[1])] = rawurldecode($value);
			} else {
				$sections[intval($n[1])] = $value;
			}
		}
		ksort($sections);
		$filename = implode('', $sections);
	} elseif (isset($params['filename*'])) {
		$filename = rawurldecode($params['filename*']);
	} elseif (isset($params['filename'])) {
		$filename = $params['filename'];
		if (preg_match('/=\?[^?]+\?[QB]\?/i', $filename)) { // none RFC compliant filename
			$filename = $this->decode_header($filename, $ctype, 'attachment filename');
		}
	}
	if ($filename && preg_match("/^([^']*)'[^']*'(.*)\$/", $filename, $attr)) {
		$filename = mb_convert_encoding($attr[2], get_bloginfo('charset'), $attr[1]);
	}
	if (! $filename && isset($ctype['name'])) { // none RFC compliant filename
		$filename = $this->decode_header($ctype['name'], $ctype, 'attachment filename');
	}
	return $filename;
}

/* ==================================================
 * @param	string   $ext
 * @param	int      $type
 * @return	boolean  $valid
 */
private function validate_extension($filename, $p_type, $s_type) {
	$parts = pathinfo($filename);
	$valid = false;
	switch (strtolower($p_type)) {
	case 'image':
		if (strtolower($s_type) == 'jpeg') {
			$valid = preg_match('/^(jpg|jpeg)$/i', $parts['extension']);
		} elseif (preg_match('/^[-a-zA-Z0-9]+$/', $s_type)) {
			$valid = preg_match("/^$s_type\$/i", $parts['extension']);
		}
	}
	return $valid;
}

/* ==================================================
 * @param	array    $contents
 * @return	array    $categories
 */
private function decide_category(&$contents) {
	$categories = array();
	if (preg_match('/^((' . preg_quote(KE_SET_CATEGORY, '/') . 
	                ')|(' . preg_quote(KE_ADD_CATEGORY, '/') . 
	                ')|(' . preg_quote(KE_CHANGE_CATEGORY, '/') . 
	                ')|(' . preg_quote(KE_ADD_CHANGE_CATEGORY, '/') . 
	                 '))(.*)$/m', $contents->text, $c)) {
		$new_default = 0;
		$contents->text = trim(preg_replace('/^' . preg_quote($c[0], '/') . '[ \t\r]*(\n|\z)/m', '', $contents->text, 1));
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
	$categories = apply_filters('post_category/ktai_entry.php', $categories);
	$this->debug_print(sprintf(__('Category: %s', 'ktai_entry_log'), implode(', ', array_map('get_catname', $categories))));
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
		} elseif (function_exists('get_category_by_slug') && $cat = get_category_by_slug($c)) {
			$c = $cat->cat_ID;
		} else {
			$c = get_cat_ID($c);
		}
		if ($c) {
			$categories[] = $c;
		}
	}
	if (count($categories)) {
		$this->debug_print(sprintf(__('Assign cats: "%1$s" -> %2$s', 'ktai_entry_log'), $cat_names, implode(',',$categories)));
	} else {
		$this->debug_print(sprintf(__('No categories found from: "%s"', 'ktai_entry_log'), $cat_names));
	}
	return $categories;
}

/* ==================================================
 * @param	array    $contents
 * @return	string   $keywords
 */
private function decide_keywords(&$contents) {
	$keywords = '';
	if (preg_match('/^' . preg_quote(KE_SET_TAGS, '/') . '(.*)$/m', $contents->text, $k)) {
		$keywords = trim($k[1]);
		$contents->text = trim(preg_replace('/^' . preg_quote($k[0], '/') . '[ \t\r]*(\n|\z)/m', '', $contents->text, 1));
		$keywords = apply_filters('post_keywords/ktai_entry.php', $keywords);
		$this->debug_print(sprintf(__('Tags: "%s"', 'ktai_entry_log'), $keywords));
	}
	return $keywords;
}

/* ==================================================
 * @param	array    $contents
 * @return	array    $rotations
 */
private function decide_rotations(&$contents) {
	if (preg_match('/^' . preg_quote(KE_ROTATE_IMAGE) . '(.*)$/m', $contents->text, $r) ) {
		$contents->text = trim(preg_replace('/^' . preg_quote($r[0], '/') . '[ \t\r]*(\n|\z)/m', '', $contents->text, 1));
		$rot_direction = strtoupper($r[1]);
	} else {
		$rot_direction = '';
	}
	$rotations = $this->parse_rotation($rot_direction, count($contents->images));
	$rotations = apply_filters('image_rotate/ktai_entry.php', $rotations, $rot_direction, $contents->images);
	if (isset($rotations) && count($rotations)) {
		$this->debug_print(sprintf(__('Rotation: %s', 'ktai_entry_log'), implode(',', $rotations)));
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
 * @param	array    $contents
 * @return	string   $post_name
 */
private function decide_postname(&$contents) {
	$post_name = '';
	if (preg_match('/^' . preg_quote(KE_SET_POSTSLUG, '/') . '(.*)$/m', $contents->text, $p)) {
		$post_name = trim($p[1]);
		$contents->text = trim(preg_replace('/^' . preg_quote($p[0], '/') . '[ \t\r]*(\n|\z)/m', '', $contents->text, 1));
	}
	$post_name = apply_filters('post_name/ktai_entry.php', $post_name, $contents);
	if ($post_name) {
		$this->debug_print(sprintf(__('Post slug: "%s"', 'ktai_entry_log'), $post_name));
	}
	return $post_name;
}

/* ==================================================
 * @param	array    $contents
 * @return	int      $post_time_gmt
 * @return	int      $image_num
 * @return	string   $date_string
 */
private function decide_postdate(&$contents) {
	$post_time_gmt = NULL;
	$image_num     = NULL;
	$date_string   = '';
	if (preg_match('/^' . preg_quote(KE_SET_POSTDATE, '/') . '(.*)$/m', $contents->text, $p)) {
		$date_string = trim($p[1]);
		$contents->text = trim(preg_replace('/^' . preg_quote($p[0], '/') . '[ \t\r]*(\n|\z)/m', '', $contents->text, 1));
	}
	$date_string = apply_filters('post_date/ktai_entry.php', $date_string, $contents->images);
	if ($date_string) {
		if (is_numeric($date_string)) {
			$image_num = intval($date_string);
			if ($image_num > 0 && $image_num <= count($contents->images)) {
				$this->debug_print(sprintf(__('Decide post date by image #%d', 'ktai_entry_log'), $image_num));
			} else {
				$image_num = NULL;
			}
		} else {
			$post_time_gmt = @strtotime($date_string);
			if ($post_time_gmt >= 86400) {
				$this->debug_print(sprintf(__('Post date: "%s"', 'ktai_entry_log'), gmdate('Y-m-d H:i:s', $post_time_gmt)));
			} else {
				$post_time_gmt = NULL;
				$this->debug_print(sprintf(__('Invalid DATE command: "%s"', 'ktai_entry_log'), $date_string));
			}
		}
	}
	return array($post_time_gmt, $image_num, $date_string);
}

/* ==================================================
 * @param	array    $contents
 * @return	string   $status
 */
private function decide_status(&$contents) {
	$can_pending = $this->check_wp_version('2.3', '>=');
	$user = set_current_user($contents->post_author);
	if (current_user_can('publish_posts')) {
		$status = 'publish';
	} elseif (current_user_can('edit_posts')) {
		$status = $can_pending ? 'pending' : 'draft';
	} else {
		$status = NULL;
	}

	$status = apply_filters('post_status/ktai_entry.php', $status, $can_pending, $contents->post_author, $contents->from);
	$available = array('publish' => 1, 'pending' => 1, 'draft' => 1, 'private' => 1);
	if (empty($status) || ! isset($available[$status])) {
		$status = NULL;
	}

	if (preg_match('/^' . preg_quote(KE_PRIVATE) . '$/m', $contents->text, $s) ) {
		$contents->text = trim(preg_replace('/^' . preg_quote($s[0]) . '[ \t\r]*(\n|\z)/m', '', $contents->text, 1));
		$status = $status ? 'private' : $status;
	} elseif (preg_match('/^' . preg_quote(KE_DRAFT) . '$/m', $contents->text, $s) ) {
		$contents->text = trim(preg_replace('/^' . preg_quote($s[0]) . '[ \t\r]*(\n|\z)/m', '', $contents->text, 1));
		$status = $status ? 'draft' : $status;
	} elseif (preg_match('/^' . preg_quote(KE_PENDING) . '$/m', $contents->text, $s) ) {
		$contents->text = trim(preg_replace('/^' . preg_quote($s[0]) . '[ \t\r]*(\n|\z)/m', '', $contents->text, 1));
		if ($can_pending) {
			$status = ($status == 'publish') ? 'pending' : $status;
		} else {
			$status = $status ? 'draft' : $status;
		}
	}
	$this->debug_print(sprintf(__('Status: %s', 'ktai_entry_log'), $status ? $status : __('(N/A)', 'ktai_entry_log')));
	return $status;
}

/* ==================================================
 * @param	object   $contents
 * @return	none
 */
private function chop_signature(&$contents) {
	if (defined('KE_DELIM_STR')) {
		$text = $contents->text;
		$sig_match = strripos($text, "\n" . KE_DELIM_STR);
		if ($sig_match > 0) {
			$text = substr($text, 0, $sig_match);
			$this->debug_print(sprintf(__('Signature chopped at byte position: %d', 'ktai_entry_log'), $sig_match));
		}
		$contents->text = $text;
	}
	return;
}

/* ==================================================
 * @param	object   $contents
 * @param	array    $rotations
 * @param	int      $post_id
 * @return	array    $images
 * based on wp_handle_upload() at wp-admin/includes/file.php of WP 2.5
 */
private function upload_images($contents, $rotations, $post_id = 0) {
	if (count($contents->images) < 1) {
		return array();
	}
	if (! function_exists('imagecreatefromstring')) {
		$this->log_error(__('GD not available.', 'ktai_entry_log'));
		return array();
	}
	$images = array();
	foreach ($contents->images as $count => $img) {
		if ( ! ( ( $uploads = wp_upload_dir() ) && false === $uploads['error'] ) ) {
			$this->log_error(@$uploads['error']);
			return array();
		}
		$filename = $this->unique_filename($uploads['path'], $img['name']);
		$new_file = $uploads['path'] . '/' . $filename;
		$this->debug_print(sprintf(__('Saving file: %s', 'ktai_entry_log'), $new_file));
		$result = $this->save_image($new_file, $img['s_type'], $img['body'], @$rotations[$count]);
		if (is_ke_error($result)) {
			$this->log_error($result->getMessage());
			return $images;
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

		if ( function_exists('wp_read_image_metadata') 
		 && $image_meta = @wp_read_image_metadata($file) ) {
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
		if ((function_exists('is_wp_error') && is_wp_error($id)) || $id <= 0) {
			$this->log_error(sprintf(__('Failed inserting attachment for post # %1$d: %2$s', 'ktai_entry_log'), $post_id,  $file));
		} else {
			if (function_exists('wp_generate_attachment_metadata')) {
				wp_update_attachment_metadata($id, wp_generate_attachment_metadata($id, $file));
			} else {
				$this->update_attachment_metadata($attachment, $id, $file);
			}
			$this->debug_print(sprintf(__('Inserted attachment: #%1$d for post #%2$d', 'ktai_entry_log'), $id, $post_id));
			$images[$id] = $file;
		}
	}
	return $images;
}

/* ==================================================
 * @param	string   $dir
 * @param	string   $filename
 * @param	string   $new_file
 */
// If $file is exists, change filename to $file_2, $file_3, ...
private function unique_filename($dir, $filename) {
	$parts = pathinfo($filename);
	$ext = $parts['extension'];
	$basename = preg_replace("/\.{$ext}\$/", '', $parts['basename']);
	$name = preg_replace(
		array('/ /', '/[^-_~+a-zA-Z0-9]/'),
		array( '_' , ''), 
		$basename);
	if (! preg_match('/[0-9a-zA-Z]/', $name)) {
		$name = md5($basename);
	}
	$count = '';
	while (file_exists("$dir/$name$count.$ext")) {
		$count = $count ? preg_replace('/(\d+)/e', "intval('$1') + 1", $count) : "_2";
	}
	return "$name$count.$ext";
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
			$fp = fopen($filepath, 'w');
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
			if (! chmod($filepath, $dir_stat['mode'] & KE_IMAGE_PERM)) {
				@unlink($filepath);
				throw new KE_Error(sprintf(__("Can't chmod the file: %s", 'ktai_entry_log'), $filepath));
			}
			$imagesize = getimagesize($filepath);
			$mimetype = preg_replace('!^.*/!', '', image_type_to_mime_type($imagesize[2]));
			if (strtolower($type) != $mimetype) {
				@unlink($filepath);
				throw new KE_Error(sprintf(__('Invalid image type "%1$s" for file: %2$s', 'ktai_entry_log'), $mimetype, $filepath));
			}
			$this->debug_print(sprintf(__('Image without rotation: %1$dx%2$d type:%3$s', 'ktai_entry_log'), $width, $height, $type));
		} else {
			$rotated = $this->rotate_image($image, $type, $rotation, $filepath);
			if (is_ke_error($rotated)) {
				return $rotated;
			}
			$dir_stat = stat(dirname($filepath));
			if (! chmod($filepath, $dir_stat['mode'] & KE_IMAGE_PERM)) {
				throw new KE_Error(sprintf(__("Can't chmod the file: %s", 'ktai_entry_log'), $filepath));
			}
			imagedestroy($rotated);
			$this->debug_print(sprintf(__('Image with rotation(%1$s): %2$dx%3$d type:%4$s', 'ktai_entry_log'), $rotation, $width, $height, $type));
		}
		imagedestroy($image);
		return KE_SUCCESS;

	} catch (KE_Error $e) {
		$e->setCode(KE_FAILED_SAVE_IMAGE);
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
 * @param	object   $attachment
 * @param	int      $id
 * @param	string   $file
 * @return	object   $this
 * based on line 78-105 at inline-uploading.php of WP ME 2.0.11
 */
private function update_attachment_metadata($attachment, $id, $file) {
	if ( preg_match('!^image/!', $attachment['post_mime_type']) ) {
		// Generate the attachment's postmeta.
		$imagesize = getimagesize($file);
		$imagedata['width'] = $imagesize['0'];
		$imagedata['height'] = $imagesize['1'];
		list($uwidth, $uheight) = get_udims($imagedata['width'], $imagedata['height']);
		$imagedata['hwstring_small'] = "height='$uheight' width='$uwidth'";
		$imagedata['file'] = $file;

		add_post_meta($id, '_wp_attachment_metadata', $imagedata);

		$max_length = intval($this->get_option('ke_thumb_size'));
		if ($max_length < 10) {
			$max_length = 128;
		}
		if ( $imagedata['width'] * $imagedata['height'] < 3 * 1024 * 1024 ) {
			if ( $imagedata['width'] > $max_length 
			&& $imagedata['width'] >= $imagedata['height'] * 4 / 3 )
				$thumb = wp_create_thumbnail($file, $max_length);
			elseif ( $imagedata['height'] > $max_length )
				$thumb = wp_create_thumbnail($file, $max_length);

			if ( @ file_exists($thumb) ) {
				$dir_stat = stat(dirname($thumb));
				chmod($thumb, $dir_stat['mode'] & KE_IMAGE_PERM);
				$newdata = $imagedata;
				$newdata['thumb'] = basename($thumb);
				update_post_meta($id, '_wp_attachment_metadata', $newdata, $imagedata);
			} else {
				$error = $thumb;
			}
		}
	} else {
		add_post_meta($id, '_wp_attachment_metadata', array());
	}
}

/* ==================================================
 * @param	array    $post_data
 * @param	array    $images
 * @param	int      $image_num
 * @param	string   $post_name_assign
 * @return	none
 */
private function postdate_from_image(&$post_data, $images, $image_num, $post_name_assign) {
	if (! function_exists('exif_read_data')) {
		$this->log_error(__('EXIF functions not available.', 'ktai_entry_log'));
		return;
	}
	$img = array_slice($images, $image_num -1 , 1);
	$exif = exif_read_data($img[0], 'FILE');
	if (isset($exif['DateTimeOriginal']) && ($datetime = @strtotime($exif['DateTimeOriginal'])) > 0) {
		$post_data['post_date']     = date('Y-m-d H:i:s', $datetime);
		$post_data['post_date_gmt'] = date('Y-m-d H:i:s', $datetime - (get_option('gmt_offset') * 3600));
		if ($this->check_duplication_by_time($datetime)) {
			$this->delete_post($post_data['ID'], array_keys( (array) $images));
			throw new KE_Error(sprintf('There is a post for specified date "%1$s" of image #%2$d.', $post_data['post_date'], $image_num), KE_ALREADY_POSTED);
		}
		if (empty($post_name_assign)) {
			$post_data['post_name'] = date('His', $datetime);
		}
		$this->debug_print(sprintf(__('Post date "%1$s" by image: %2$s', 'ktai_entry_log'), $post_data['post_date'], $img['name']));
	}
}

/* ==================================================
 * @param	array    $html
 * @param	array    $images
 * @return	string   $html
 */
private function images_to_html($content, $images) {
	global $post;
	if (is_array($images) && count($images)) {
		$img = array();
		if (function_exists('wp_get_attachment_link')) {
			$link_func = 'wp_get_attachment_link';
			$size      = ($this->get_option('ke_thumb_size') == 'medium') ? 'medium' : 'thumbnail';
		} else {
			$link_func = 'get_the_attachment_link';
			$size      = false;
		}
		foreach (array_keys($images) as $id) {
			if (! $id) continue;
			$post = get_post($id); // workaround for wp 2.1.x bug
			$html = $link_func($id, $size);
			if (! preg_match('/href=(["\'])([^"\']*)\\1/', $html, $file)) {
				preg_match('/src=(["\'])([^"\']*)\\1/', $html, $file);
			}
			if (preg_match('/alt=(""|\'\')/', $html, $alt)) {
				$html = str_replace($alt[0], 'alt="' . basename($file[2]) . '"', $html);
			} elseif (! preg_match('/alt=/', $html)) {
				$html = str_replace('<img ', '<img alt="' . basename($file[2]) . '" ', $html);
			}
			$html = apply_filters('image_link/ktai_entry.php', $html, $id, $size);
			$img[] = $html;
		}
		$template = $this->get_option('ke_post_template');
		if (strpos($template, KE_TEMPLATE_IMAGES) === false) {
			$template .= KE_TEMPLATE_IMAGES;
		}
		if (count($img)) {
			$trans = array(
				KE_TEMPLATE_TEXT   => $content, 
				KE_TEMPLATE_IMAGES => implode(' ', $img)
				);
			$content = apply_filters('media_to_html/ktai_entry.php', strtr($template, $trans), $content, $img);
		}
	}
	return $content;
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

/* ==================================================
 *   KE_Error class
   ================================================== */

if (! class_exists('KE_Error')) :

function is_ke_error($thing) {
	return (is_object($thing) && is_a($thing, 'KE_Error'));
}

class KE_Error extends Exception {

public function setCode($code) {
	$this->code = $code;
}

// ===== End of class ====================
}
endif;

?>