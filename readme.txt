# Github to WordPress
Contributors: vaakash
Author URI: https://www.aakashweb.com/
Plugin URI: https://www.aakashweb.com/wordpress-plugins/shortcoder/
Tags: shortcode, html, javascript, shortcodes, snippets, posts, pages, widgets, insert, adsense, ads, code
Donate link: https://www.paypal.me/vaakash/
License: GPLv2 or later
Requires PHP: 5.3
Requires at least: 4.4
Tested up to: 5.5
Stable tag: 1.0

Publish markdown, HTML files present in a Github repository as posts to WordPress automatically



## Description

Github to WordPress allows to publish the markdown, text, HTML files present in a Github repository to your WordPress site. So with this plugin, whenever the files are added, updated in the repository the WordPress post will be added, updated accordingly.

This allows people to collaborate with the post and share edits, suggestions in Github which when pulled will be updated the WordPress post will be updated automatically.

### Links

* [Documentation](https://www.aakashweb.com/docs/shortcoder-doc/)
* [FAQs](https://www.aakashweb.com/docs/shortcoder-doc/)
* [Support forum/Report bugs](https://www.aakashweb.com/forum/)



## Installation

1. Extract the zipped file and upload the folder `Github-To-WordPress` to to `/wp-content/plugins/` directory.
1. Activate the plugin through the `Plugins` menu in WordPress.
1. Open the admin page from the "Github to WordPress" link under the settings menu.



## Frequently Asked Questions

Please visit the [plugin documentation page](https://www.aakashweb.com/docs/shortcoder-doc/) for complete list of FAQs.

### What are the allowed characters for shortcode name ?

Allowed characters are alphabets, numbers, hyphens and underscores.

### My shortcode is not working in my page builder !

Please check with your page builder plugin to confirm if the block/place/area where the shortcode is being used can execute shortcodes. If yes, then shortcode should work fine just like regular WordPress shortcodes.

### My shortcode is not working !

Please check the following if you notice that the shortcode content is not printed or when the output is not as expected.

* Please verify if the shortcode content is printed. If shortcode content is not seen printed, check the shortcode settings to see if any option is enabled to restrict where and when shortcode is printed. Also confirm if the shortcode name is correct and there is no duplicate `name` attribute for the shortcode.
* If shortcode is printed but the output is not as expected, please try the shortcode content in an isolated environment and confirm if the shortcode content is working correctly as expected. Sometimes it might be external factors like theme, other plugin might conflict with the shortcode content being used.
* There is a known limitation in shortcodes API when there is a combination of unclosed and closed shortcodes. Please refer [this document](https://codex.wordpress.org/Shortcode_API#Unclosed_Shortcodes) for more information.

### Can I insert PHP code in shortcode content ?

No, right now the plugin supports only HTML, Javascript and CSS as shortcode content.



## Screenshots

1. Shortcoder admin page.
2. Editing a shortcode.
3. "Insert shortcode" popup to select and insert shortcodes.
4. A shortcode inserted into post.
5. Shortcoder block for Gutenberg editor.
6. Shortcoder executed in the post.

[More Screenshots](https://www.aakashweb.com/wordpress-plugins/shortcoder/)



## Changelog

### 1.0
* Brand new plugin



## Upgrade Notice

Version 1.0 is the first version of the plugin.