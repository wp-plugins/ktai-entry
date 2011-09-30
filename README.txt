=== Ktai Entry ===
Contributors: lilyfan
Tags: email, post, post by email, mobile, keitai, japan, pictogram
Requires at least: 2.6
Tested up to: 3.2.1
Stable tag: 0.9.1.2

"Ktai Entry" is a plugin to post to WordPress by email. You can attach images and/or use pictograms in the message.

== Description ==

[日本語の説明を読む](http://wppluginsj.sourceforge.jp/ktai_entry/)

"Ktai Entry" is a plugin to post to WordPress by email. To say "Moblog plugin"

* No web browser to reflect new mail message (against wp-mail.php)
* Supports to specify categories and keyword tags.
* Supports to change post slug and post stagus.
* Supports to specify post date by test string, or the date of attached JPEG image.
* Supports to rotate attached images.
* Supports to include pictograms of Japanese mobile phones of all five carreers (NTT docomo, KDDI au, SoftBank, WILLCOM, EMOBILE). Pictograms are stored HTML like `<img localsrc="NNNN" />`: format of Ktai Style (Mobile view plugin).

You can choose two ways of sending mail:

1. Retrieve message of external mailbox periodically.
1. Kick the posting script by arrival of message.

== Requirements ==

* WordPress 2.6 or later
* PHP 5.1 or later (NOT support PHP 4.x!!)

(Only for Kicking the posting script)

* The mail server provides editing .forward/.qmail/.procmail to kick the posting script when mail messages are arrived.

== Installation ==

1. Unzip the plugin archive and put `ktai-entry` folder into your plugins directory (`wp-content/plugins/`) of the server. 
1. Before activate the plugin, you need to configure an mail address. 
   * (Case of retrieving message of external mailbox)
   * Prepare an exclusive posting email address. Please avoid to select existing mail address that you are already usging. It is OK to get no-charge addresses like Gmail, Yahoo! Mail.
   * At Post via e-mail section (in Writing Settings of WordPress admin panel), type in your POP3 settings: Mail Server, Login Name, Password, Default Mail Category. Ktai Entry Reflects "Default Mail Category".
   * (Case of kicking the posting script)
   * Create a secret posting address. For example, execute below commands in a shell:
     `php -r 'echo md5(uniqid(rand(), true)) . "\n";'
   * Login to the mail server by shells, create the .qmail-SECRET or .foreard+SECRET file (SECRET means the string created before) If you create the file, qmail/postfix serves a mail address like USER-secret@example.com, USER+secret@example.com. (USER means your user account name) Take care not to include an upper characters or dot characters.
   * In the content of .qmail-SECRET/.forward-SECREt file like below. (Adjust the path to PHP, WordPress if needed)
     `| /usr/bin/php /PATH_TO_WORDPRESS/wp-content/plugins/ktai-entry/inject.php`
     In short, you may configure that arriving a mail to the address starts `inject.php` script.
   * For WordPress MU, create .qmail-SECRET/.forward-SECRET files for each blog, and a `-blog` option needed to specify the blog_id (interger number).
     `| /usr/bin/php /PATH_TO_WORDPRESS/wp-content/plugins/ktai-entry/inject.php -blog 2`
     Next, comment out the `define( 'SUNRISE', 'on' );` line in wp-config.php and copy `ktai-entry/sunrise.php` file under `wp-content/` (as `wp-content/sunrise.php`). If there is already `sunrise.php` file under `wp-content/`, add the content of `ktai-entry/sunrise.php` after `wp-content/sunrise.php`.
1. Login to you WordPress site, and create a new user. The mail address of the user must be the email address from what you send post (like mail phone's email, etc).
   If you want publish the post as soon as mail was send, the privilege of the user must be higher than and equal to author. If the user is a contributor, the post is pending or draft.
   If the user is a subscriber, posted mail is rejected.
1. Check the stylesheet of your theme. If the theme has no class of `alignleft, aligncenter, alignright, clear`, Add below code to style.css.
`img.alignright {
	padding: 4px;
	margin: 0 0 2px 7px;
	display: inline;
}
img.alignleft {
	padding: 4px;
	margin: 0 7px 2px 0;
	display: inline;
}
.aligncenter, div.aligncenter {
	display: block;
	margin-left: auto;
	margin-right: auto;
}
.alignright {
	float: right;
}
.alignleft {
	float: left
}
.clear {
	clear:both;
}`
1. Activate the plugin.
1. Configure the options "Post by Email" under "Settings" section. You can use default configuration. 
   * APOP: The server needs APOP connection. Check this. DO NOT check if you use POP over SSL (Gmail, Yahoo! Mail etc)
   * POP3 retrieve interval: The interval of checking external POP3 mailbox. If you use the method of kicking the posting script, Select "None".
   * Posting mail address (option): The address that you created above section. If the recipient field (To, CC) has not this address, the post is rejected. If this field is empty, recipient check is skipped. If you are unshure, keep this field blank.

= Moving wp-content, or wp-content/plugins directory to non-standard position =

After WordPress 2.6, you can move wp-content, or wp-content/plugins directory to non-standard position. If you move the directory, additional configuration is needed.

1. When installing the plugin, the permission of the `ktai-entry` directory to 757 or 777, so that the webserver can touch the directory.
1. Activate the plugin, then `wp-load-conf.php` file is automatically created. It is OK.
1. If the file is not created, you need to edit `ktai-entry/wp-load.php` manually. At line 20, change `$wp_root` variable to specify the absolute path to WordPress installed directory.

	e.g. WP diretory is `/home/foo/puglic_html/wp-core/` and wp-content directory is `/home/foo/public_html/wp-content/`
	`$wp_root = dirname(dirname(dirname(dirname(__FILE__)))) . '/wp-core/';`

= Gmail Configuration =
If you use Gmail to the external mailbox, like below:

1. [Create a new Gmail account](http://mail.google.com/mail/signup). If you have one, it is recommended to create another one.
1. At Gmail settings > Mail Forward and POP/IMAP settings > POP download, Select "".
1. Make a random string. You can use a string displayed at "Post via Mail" section of WordPress admin panel. And, substitute upper character and punctuations from it. (e.g. `aBM9dDu*^w$R` -> `abm9dduwr`). Then chain the local part of your Gmail address and this random string with plus sign, and new address is made. (e.g. `example@gmail.com` and `abm9dduwr` results `example+abm9dduwr@gmail.com`.
1. Put the new address in "Posting mail address" field. Therefore, only the message posted to the address having random string (e.g. `example+abm9dduwr@gmail.com`) is accepted, normal address (e.g. `example@gmail.com`) is rejected.
1. Configure "Post via Email" of "Writing" settings like below:

   * Mail Server: ssl://pop.gmail.com
   * Port: 995
   * Login Name: (Your Gmail adress) e.g. example@gmail.com
   * Password: (Your Gmail password)

== Usage ==

The standard usage is to post from your mobile phone to the posting address (you created above section).

* Title: The subject of the mail, or included content of <title>--</title> section of the body.
* Author: A registered user associated with the sender address (from field of the mail)
* Status: Status will be "publish" when the post author has privilege over than and equal to "author". It will be "pending" when the post author is a "contributor". If the post author is a "subscriber", the post is rejected.
* Date and Time: The date and time of the mail message (date field). You can change this by DATE command (described below). Messages which have the same date to the existing post is rejected.
* Slug: A number having six digits that represents hour-minutes-seconds. If there is a same slug, suffix number "-2", "-3", ... is added. (8 characters)
* Category: The default category follows to the field "Default mail category" of "Post vie e-mail" section.
* Content: The body of mail message is the content of a post. But commands (described below) and signature section (starts `-- ` character) is removed. In case of attaching images, Ktai Entry uploads the images and insert img elements in the post content. If there is a same content to an existing post, the process is aborted. But, attached images will be different suffix, the same-check does not effective and the same content will be posted.

== Licence ==

The license of this plugin is GPL v2.

== Getting a support ==

To get support for this plugin, please contact below methods:

1. Send an email to yuriko-ktaientry _@_ YURIKO (dot) NET. (You need adjust to the valid address)
1. Use contact form at http://www.yuriko.net/contact/ (Japanese site)
1. Post WordPress forum with 'ktai-entry' tag.

== Changelog ==

= 0.9.1.2 (2010-09-30) =
* Fixed an issue of messages with unspecified encodings.

= 0.9.1.1 (2010-09-14) =
* Fixed an issue of skipping the content with Yahoo! mail.

= 0.9.1 (2010-09-07) =
* When changing the post date time into the date time of the specified attached file, the plugin recognizes a date time format `yymmdd_hhiiss` of the image filename.
* Replace the attachment filename to `Ymd_His.jpg` format if the original name is 'image.jpg' or 'photo.jpg'
* Fixed a issue that mobile pictograms are not recognized. (For version 0.9.0.x only)
* Fixed a issue that nofity settings are not saved at Ktai Entry admin panel.

= 0.9.0.1 (2010-09-02) =
* Fixed a issue on a mail with attached images. (For version 0.9.0 only)
* Stopped debug mode. (Only version 0.9.0, debug mode is on by accident.)

= 0.9.0 (2010-09-01) =
* Change supporting WordPress version to 2.6+.
* Change supporting PHP version to 5.1+.
* Support decoration pictograms. They are stored into the media library as image. Flash pictograms are not supported.
* Change the trigger of retrieving external mailbox to wp-cron.
* Can notify new posts to admin users.
* Support "Activate Plugins Site Wide" after WordPress MU 2.8.
* Support kicking the posting script by email for multisite. You need to create posting address for each blog and put `wp-content/sunrise.php`. Please see setting instruction.
* Changed the post template. You can allocate images favorite place other than left of text.
* Support Large size image.
* Changed the exclusive filter name from `xxxxx/ktai_entry.php` into `ktai_xxxxx`.
* Moved inject.php, retrive.php, post.php into `inc` sub folder.

= 0.8.11 (2009-11-20) =
* Delete redundant dot if attached filename has more than one dot. +++ Security Issue +++
* Replace space into underscore (_) of attached filename.
* Fixed error that 21 pictograms of EZweb phones has recognized as another pictograms.

= --snip-- =

= 0.7.0 (2008-04-23) =
* Initial version. Integrate wp-shot and wp-mta (Both are released in Japan only)
* Support adding keyword tags.
* Attached images are uploaded to media library of WordPress.
