<?php 
/* ==================================================
 *   Select blog for inject.php
   ================================================== */

global $wpdb, $current_site, $current_blog, $blog_id;
if (isset($_SERVER['HTTP_HOST']) || ! isset($wpdb->blogs) || isset($current_site) && isset($current_blog)) {
	return;
}

if (! isset($blog_id) || ! $blog_id) {
	$blog_id = 1;
}

$address = $wpdb->get_row($wpdb->prepare("SELECT domain, path FROM `{$wpdb->blogs}` WHERE blog_id = %d LIMIT 1", $blog_id));
if ($address != NULL) {
	$_SERVER['HTTP_HOST'] = $address->domain;
	$_SERVER['REQUEST_URI'] = $address->path;
} else {
	$_SERVER['HTTP_HOST'] = defined('DOMAIN_CURRENT_SITE') ? DOMAIN_CURRENT_SITE : 'localhost';
	$_SERVER['REQUEST_URI'] = defined('PATH_CURRENT_SITE') ? PATH_CURRENT_SITE : '/';
}
?>