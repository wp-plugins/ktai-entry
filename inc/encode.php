<?php
/* ==================================================
 *   KtaiMailEncode class
   ================================================== */
   
/*  Copyright (c) 2010 IKEDA Yuriko

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

class KtaiMailContent {
	public $from;
	public $post_author;
	public $post_time_gmt;
	public $post_title;
	public $text = '';
	public $text_type = NULL;
	public $images = array();
}

class KtaiMailEncode {
	private $base;
	private $original_encoding;
	private $input_encoding;
	private $blog_encoding;
	const ALLOW_AUTO = true;
	const DISALLOW_AUTO = false;
	const LOOSE = true;

/* ==================================================
 * @param	string  none
 * @return	object  $this
 * @since 0.9.0
 */
public static function factory($base) {
	if ( function_exists('mb_convert_encoding') ) {
		return new KtaiMailEncode_mbstring($base);
	} else { // assume having iconv extension
		return new KtaiMailEncode_iconv($base);
	}
}

/* ==================================================
 * @param	string  $none
 * @return	object  $this
 * @since 0.9.0
 */
public function __construct($base) {
	$this->base = $base;
	$this->blog_encoding = get_bloginfo('charset');
}

/* ==================================================
 * @param	string  $key
 * @param	mixed   $value
 * @return	none
 * @since 0.9.0
 */
public function set($key, $value) {
	$this->$key = $value;
}

/* ==================================================
 * @param	string  $charset
 * @return	mixed   $value
 * @since 0.9.0
 */
public function iana_charset($charset) {
	return apply_filters( 'ktai_iana_charset', KtaiMailEncode_iconv::normalize($charset, self::LOOSE) );
}

/* ==================================================
 * @param	string  $encoding1
 * @param	string  $encoding2
 * @return	boolean $is_same
 * @since 0.9.0
 */
public function similar($encoding1, $encoding2) {
	return (strcmp(
		strtolower($this->normalize($encoding1, self::LOOSE)), 
		strtolower($this->normalize($encoding2, self::LOOSE))
	) === 0);
}

/* ==================================================
 * @param	string  $buffer
 * @param	string  $in_encoding
 * @return	string  $buffer
 * @since 0.9.0
 */
public function convert_from_mobile($buffer, $encoding = NULL) {
	if ( !$encoding ) {
		$encoding = $this->input_encoding;
	}
	$buffer = $this->convert($buffer, $this->blog_encoding, $encoding);
	return $buffer;
}

/* ==================================================
 * @param	string   $encoded
 * @param	array    $ctype
 * @return	string   $encoding
 */
public function decode_header($encoded, $ctype, $place = 'elesewhere') {
	if (preg_match('/=\?([^?]+)\?[qb]\?/ims', $encoded, $mime)) {
		$encoding = $mime[1];
		$encoded = $this->decode_mime($encoded);
	} else {
		$encoding = $this->get_charset($ctype);
	}
	$this->base->debug_print(sprintf(__('Detect %1$s encoding as "%2$s"', 'ktai_entry_log'), $place, $encoding));
	return $this->convert_from_mobile($encoded, $encoding);
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
public function get_charset($ctype) {
	return isset($ctype['charset']) ? $ctype['charset'] : 'auto';
}

/* ==================================================
 * @param	object   $part
 * @return	object   $content
 */
public function get_mime_parts($part) {
	$contents = new KtaiMailContent;
	switch (strtolower($part->ctype_primary)) {
		case 'multipart':
			if ($part->ctype_secondary == 'alternative') {
				foreach ($part->parts as $p) {
					$part_content = $this->get_mime_parts($p);
					if ( $part_content->text_type == 'html' ) {
						$contents->text      = $part_content->text;
						$contents->text_type = $part_content->text_type;
					} elseif ( $part_content->text_type == 'plain' ) {
						if ( empty($contents->text) ) {
							$contents->text      = $part_content->text;
							$contents->text_type = $part_content->text_type;
						}
					} elseif ( !empty($part_content->images) ) {
						$contents->images = $part_content->images;
					}
				}
			} else {
				foreach ($part->parts as $p) {
					$part_content = $this->get_mime_parts($p);
					if ( !empty($part_content->text) ) {
						$contents->text     .= $part_content->text;
						$contents->text_type = $part_content->text_type;
					}
					if ( !empty($part_content->images) ) {
						$contents->images = array_merge($contents->images, $part_content->images);
					}
				}
			}
			break;
		case 'text':
			$this->get_mime_text_part($contents, $part);
			break;
		case 'image':
			$name = $this->get_filename($part->d_parameters, $part->ctype_parameters);
			$this->base->debug_print(sprintf(__('Found %1$s/%2$s part with filename: %3$s', 'ktai_entry_log'), $part->ctype_primary, $part->ctype_secondary, $name));
			if ( !$this->validate_extension($name, $part->ctype_primary, $part->ctype_secondary) ) {
				$this->base->debug_print(sprintf(__('Invalid filename "%1$s" for mime type "%2$s/%3$s"', 'ktai_entry_log'), $name, $part->ctype_primary, $part->ctype_secondary));
				break;
			}
			$contents->images[] = array(
				'name'   => $name, 
				'p_type' => strtolower($part->ctype_primary), 
				's_type' => strtolower($part->ctype_secondary), 
				'cid'    => (empty($part->cid) ? NULL : $part->cid),
				'pos'    => (empty($part->disposition) ? NULL : strtolower($part->disposition)),
				'body'   => $part->body
			);
			break;
	}
	return $contents;
}

/* ==================================================
 * @param	object   $contents
 * @param	object   $part
 * @return	none
 * @since	0.9.0
 */
private function get_mime_text_part(&$contents, $part) {
	$text = $part->body;
	$encoding = $this->get_charset($part->ctype_parameters);
	$text = apply_filters('ktai_raw_mime_text', $text, $encoding); // pickup pictograms for JIS
	if ( !$this->check($text, $encoding) ) {
			$this->base->debug_print(sprintf(__('Invalid character found for %1$s encoding.', 'ktai_entry_log'),  $encoding));
			$this->base->debug_print(sprintf(__('Skipped %1$s/%2$s part.', 'ktai_entry_log'), $part->ctype_primary, $part->ctype_secondary));
			return;
	}
	if ($encoding == 'auto') {
		$encoding = $this->get('detect_order');
	}
	$this->base->debug_print(sprintf(__('Detect text/%1$s part encoding as "%2$s"', 'ktai_entry_log'), $part->ctype_secondary, $encoding));
	$text = apply_filters('ktai_checked_mime_text', $text, $encoding); // pickup pictograms for SJIS
	$text = $this->convert_from_mobile($text, $encoding);
	switch (strtolower($part->ctype_secondary)) {
		case 'x-pmaildx':
		case 'plain':
			$contents->text = trim($text);
			$contents->text_type = 'plain';
			break;
		case 'html':
			$contents->text = $this->html_to_text($text);
			$contents->text_type = 'html';
			break;
	}
	return;
}

/* ==================================================
 * @param	string   $html
 * @return	string   $text
 */
private function html_to_text($html) {
	$text = preg_replace('!</(p|div)>!i', "</\$1>\n", $html);
	$text = trim(strip_tags($text, apply_filters('ktai_html_allowedtags', '<img><title>')));
	return apply_filters('ktai_html_to_text', $text, $html);
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
		$filename = $this->convert_from_mobile($attr[2], $attr[1]);
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
		if (strtolower($s_type) == 'jpeg' || strtolower($s_type) == 'jpg') { // Mail.app uses image/jpg type with *.jpeg filename
			$valid = preg_match('/^jpe?g$/i', $parts['extension']);
		} elseif (preg_match('/^[-a-zA-Z0-9]+$/', $s_type)) {
			$valid = preg_match("/^$s_type\$/i", $parts['extension']);
		}
	}
	return $valid;
}

// ===== End of class ====================
}

/* ==================================================
 *   KtaiMailEncode_mbstring class
   ================================================== */

class KtaiMailEncode_mbstring extends KtaiMailEncode {
	public static $detect_order = array('ASCII', 'JIS', 'UTF-8', 'SJIS', 'EUC-JP', 'SJIS-win');

public function __construct($base) {
	parent::__construct($base);
	$this->original_encoding = mb_internal_encoding();
}

/* ==================================================
 * @param	string  $key
 * @return	mixed   $value
 * @since 0.9.0
 */
public function get($key) {
	if ($key == 'detect_order') {
		return apply_filters('ktai_detect_order', self::$detect_order); // must be at child class
	} else {
		return $this->$key;
	}
}

/* ==================================================
 * @param	string  $encoding
 * @param	boolean $loose
 * @return	string  $encoding
 * @since 0.9.0
 */
public function normalize($encoding, $loose = false) {
	$normalize = array(
		'ujis'           => 'EUC-JP',
		'cp932'          => 'SJIS-win',
		'shift_jis'      => 'SJIS',
		'ms_kanji'       => 'SJIS',
		'windows-31j'    => 'SJIS-win',
		'iso-2022-jp'    => 'JIS',
		'iso-2022-jp-1'  => 'JIS',
		'iso-2022-jp-2'  => 'JIS',
		'iso-2022-jp-ms' => 'ISO-2022-JP-MS', // prevent normalizing into 'JIS-ms'
	);
	if ($loose) {
		$normalize = array_merge($normalize, array(
			'cp932'          => 'SJIS', // override
			'sjis-win'       => 'SJIS', // override
			'windows-31j'    => 'SJIS', // override
			'eucjp-win'      => 'EUC-JP',
			'iso-2022-jp-ms' => 'JIS', // override
		));
	}
	return strtr(strtolower($encoding), $normalize);
}

/* ==================================================
 * @param	string   $buffer
 * @return	string   $encoding
 * @since 0.9.0
 */
public function check($buffer, $encoding) {
	if ($encoding == 'auto') {
		$encoding = $this->guess($buffer, parent::DISALLOW_AUTO);
	}
	if ($this->similar($encoding, 'SJIS')) {
		$result = mb_check_encoding($buffer, 'SJIS') || mb_check_encoding($buffer, 'SJIS-win');
	} elseif ($this->similar($encoding, 'EUC-JP')) {
		$result = mb_check_encoding($buffer, 'EUC-JP') || mb_check_encoding($buffer, 'eucJP-win');
	} elseif ($this->similar($encoding, 'JIS')) {
		$result = mb_check_encoding($buffer, 'ISO-2022-JP') || mb_check_encoding($buffer, 'ISO-2022-JP-MS');
	} else {
		$result = mb_check_encoding($buffer, $this->normalize($encoding));
	}
	return $result;
}

/* ==================================================
 * @param	string  $input
 * @param	boolean $allow_auto
 * @return	string  $encoding
 * @since 0.9.0
 */
public function guess_from_http($allow_auto = false) {
	$encoding = ini_get('mbstring.encoding_translation') ? $this->original_encoding : mb_http_input('G');
	if ( !$encoding && $allow_auto ) {
		$encoding = 'auto';
	}
	if ( $encoding ) {
		$this->input_encoding = $encoding;
	}
	return $encoding;
}

/* ==================================================
 * @param	string  $input
 * @param	boolean $allow_auto
 * @return	string  $encoding
 * @since 0.9.0
 */
public function guess($input, $allow_auto = false) {
	$default = $this->input_encoding ? $this->input_encoding : $this->original_encoding;
	if ( $input ) {
		$encoding = mb_detect_encoding($input, $this->get('detect_order'));
		if ( $allow_auto && $encoding == 'ASCII' ) {
			$encoding = 'auto';
		}
	} else {
		$encoding = $allow_auto ? 'auto' : $default;
	}
	return $encoding;
}

/* ==================================================
 * @param	string  $buffer
 * @param	string  $out_encoding
 * @param	string  $in_encoding
 * @return	string  $buffer
 * @since 0.9.0
 */
public function convert($buffer, $out_encoding = 'UTF-8', $in_encoding = 'auto') {
	if ( !$this->similar($in_encoding, $out_encoding) ) {
		$buffer = mb_convert_encoding($buffer, $this->normalize($out_encoding), $this->normalize($in_encoding));
	}
	return $buffer;
}

/* ==================================================
 * @param	string  $bufpatternfer
 * @param	string  $replacement
 * @param	string  $target
 * @param	string  $option
 * @return	string  $replaced
 * @since 0.9.0
 */
public function replace($pattern, $replacement, $target, $encoding = '', $option = NULL) {
	if ( empty($encoding) ) {
		$encoding = $this->input_encoding;
	}
	mb_regex_encoding($encoding);
	return mb_ereg_replace($pattern, $replacement, $target, $option);
}

// ===== End of class ====================
}

/* ==================================================
 *   KtaiMailEncode_iconv class
   ================================================== */

class KtaiMailEncode_iconv extends KtaiMailEncode {
	public static $detect_order = array('US-ASCII', 'ISO-2022-JP', 'UTF-8', 'Shift_JIS', 'EUC-JP', 'CP932');

public function __construct($base) {
	parent::__construct($base);
	$this->original_encoding = $this->blog_encoding;
}

/* ==================================================
 * @param	string  $key
 * @return	mixed   $value
 * @since 0.9.0
 */
public function get($key) {
	if ($key == 'detect_order') {
		return apply_filters('ktai_detect_order', self::$detect_order); // must be at child class
	} else {
		return $this->$key;
	}
}

/* ==================================================
 * @param	string  $encoding
 * @param	boolean $loose
 * @return	string  $encoding
 * @since 0.9.0
 */
public function normalize($encoding, $loose = false) {
	$normalize = array(
		'jis'            => 'ISO-2022-JP',
		'sjis'           => 'Shift_JIS',
		'shift_jis'      => 'Shift_JIS', // prevent normalizing into 'shift_ISO-2022-JP'
		'sjis-win'       => 'CP932',
		'ujis'           => 'EUC-JP',
		'ms_kanji'       => 'Shift_JIS',
		'windows-31j'    => 'CP932',
		'eucjp-win'      => 'EUC-JP',
		'iso-2022-jp-ms' => 'ISO-2022-JP',
	);
	if ($loose) {
		$normalize = array_merge($normalize, array(
			'cp932'          => 'Shift_JIS',
			'sjis-win'       => 'Shift_JIS', // override
			'windows-31j'    => 'Shift_JIS', // override
			'eucjp-win'      => 'EUC-JP',
			'iso-2022-jp-1'  => 'ISO-2022-JP',
			'iso-2022-jp-2'  => 'ISO-2022-JP',
		));
	}
	return strtr(strtolower($encoding), $normalize);
}

/* ==================================================
 * @param	string   $buffer
 * @return	string   $encoding
 * @since 0.9.0
 */
public function check($buffer, $encoding) {
	if ($encoding == 'auto') {
		$encoding = $this->guess($buffer, parent::DISALLOW_AUTO);
	}
	if ($this->similar($encoding, 'shift_jis')) {
		$converted1 = iconv('Shift_JIS', 'Shift_JIS//IGNORE', $buffer);
		$converted2 = iconv('CP932',     'CP932//IGNORE',     $buffer);
		$result = ( $converted1 === $buffer || $converted2 === $buffer );
	} elseif ($this->similar($encoding, 'iso-2022-jp')) {
		$converted1 = iconv('ISO-2022-JP',   'ISO-2022-JP//IGNORE',   $buffer);
		$converted2 = iconv('ISO-2022-JP-1', 'ISO-2022-JP-1//IGNORE', $buffer);
		$converted3 = iconv('ISO-2022-JP-2', 'ISO-2022-JP-2//IGNORE', $buffer);
		$result = ( $converted1 === $buffer || $converted2 === $buffer || $converted3 === $buffer );
	} else {
		$encoding = $this->normalize($encoding);
		$converted = iconv($encoding, $encoding . '//IGNORE', $buffer);
		$result = ( $converted === $buffer );
	}
	return $result;
}

/* ==================================================
 * @param	string  $input
 * @param	boolean $allow_auto
 * @return	string  $encoding
 * @since 0.9.0
 */
public function guess_from_http($allow_auto = false) {
	$encoding = NULL;
	if ( $allow_auto ) {
		$encoding = 'auto';
		$this->input_encoding = $encoding;
	}
	return $encoding;
}

/* ==================================================
 * @param	string  $input
 * @param	boolean $allow_auto
 * @return	string  $encoding
 * @since 0.9.0
 */
public function guess($input, $allow_auto = false) {
	$default = $this->input_encoding ? $this->input_encoding : $this->original_encoding;
	if ( $input ) {
		$encoding = NULL;
		$detect_order = $this->get('detect_order');
		foreach ($detect_order as $enc) {
			$enc = $this->normalize($enc);
			$converted = iconv($enc, $enc . '//IGNORE', $buffer);
			if ( $converted === $input ) {
				$encoding = $enc;
				break;
			}
		}
	} else {
		$encoding = $allow_auto ? 'auto' : $default;
	}
	return $encoding;
}

/* ==================================================
 * @param	string  $buffer
 * @param	string  $out_encoding
 * @param	string  $in_encoding
 * @return	string  $buffer
 * @since 0.9.0
 */
public function convert($buffer, $out_encoding = 'UTF-8', $in_encoding = 'auto') {
	if ( $in_encoding == 'auto' ) {
		$in_encoding = $this->input_encoding ? $this->input_encoding : $this->original_encoding;
	}
	if ( !$this->similar($in_encoding, $out_encoding) ) {
		$buffer = iconv($this->normalize($in_encoding), $this->normalize($out_encoding) . '//TRANSLIT', $buffer);
	}
	return $buffer;
}

/* ==================================================
 * @param	string  $bufpatternfer
 * @param	string  $replacement
 * @param	string  $target
 * @param	string  $option
 * @return	string  $replaced
 * @since 0.9.0
 */
public function replace($pattern, $replacement, $target, $encoding = '', $option = NULL) {
	return preg_replace('/' . $pattern . '/', $replacement, $target, $option);
}

// ===== End of class ====================
}
?>