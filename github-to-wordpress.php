<?php
/*
Plugin Name: Github to WordPress
Plugin URI: https://www.aakashweb.com/
Description: A WordPress plugin to publish posts from Github
Author: Aakash Chakravarthy
Version: 1.0
*/

define( 'G2W_PATH', plugin_dir_path( __FILE__ ) ); // All have trailing slash

final class Github_To_WordPress{

    function __construct(){
        
        $this->includes();

    }

    function includes(){

        require __DIR__ . '/vendor/autoload.php';

        require_once( G2W_PATH . 'includes/g2w.php' );
        require_once( G2W_PATH . 'includes/parsedown.php' );

        require_once( G2W_PATH . 'admin/admin.php' );

    }

}

new Github_To_WordPress();

?>