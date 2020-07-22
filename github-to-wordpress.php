<?php
/*
Plugin Name: Github to WordPress
Plugin URI: https://www.aakashweb.com/
Description: A WordPress plugin to publish posts from Github
Author: Aakash Chakravarthy
Version: 1.0
*/

define( 'G2W_PATH', plugin_dir_path( __FILE__ ) ); // All have trailing slash

require __DIR__ . '/vendor/autoload.php';

final class Github_To_WordPress{

    function __construct(){
        
        $this->includes();

    }

    function includes(){

        require_once( G2W_PATH . 'includes/class-g2w.php' );
        require_once( G2W_PATH . 'includes/class-parsedown.php' );
        require_once( G2W_PATH . 'admin/admin.php' );

    }

}

new Github_To_WordPress();

?>