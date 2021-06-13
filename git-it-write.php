<?php
/*
Plugin Name: Git it Write
Plugin URI: https://www.aakashweb.com/wordpress-plugins/git-it-write/
Description: Publish markdown files present in a Github repository as posts to WordPress automatically
Author: Aakash Chakravarthy
Author URI: https://www.aakashweb.com/
Version: 1.2
*/

define( 'GIW_VERSION', '1.2' );
define( 'GIW_PATH', plugin_dir_path( __FILE__ ) ); // All have trailing slash
define( 'GIW_ADMIN_URL', trailingslashit( plugin_dir_url( __FILE__ ) . 'admin' ) );

final class Git_It_Write{

    public static function init(){
        
        self::includes();

    }

    public static function includes(){

        require __DIR__ . '/vendor/autoload.php';

        require_once( GIW_PATH . 'includes/utilities.php' );
        require_once( GIW_PATH . 'includes/repository.php' );
        require_once( GIW_PATH . 'includes/publisher.php' );
        require_once( GIW_PATH . 'includes/publish-handler.php' );
        require_once( GIW_PATH . 'includes/parsedown.php' );
        require_once( GIW_PATH . 'includes/webhook.php' );
        require_once( GIW_PATH . 'includes/shortcodes.php' );

        require_once( GIW_PATH . 'admin/admin.php' );

    }

    public static function default_config(){
        return array(
            'username' => '',
            'repository' => '',
            'folder' => '',
            'branch' => 'master',
            'post_type' => '',
            'post_author' => 1,
            'content_template' => '%%content%%',
            'last_publish' => 0
        );
    }

    public static function default_general_settings(){
        return array(
            'webhook_secret' => ''
        );
    }

    public static function allowed_file_types(){
        return array(
            'md'
        );
    }

    public static function all_repositories(){

        $repos_raw = get_option( 'giw_repositories', array( array() ) );
        $repos = array();
        $default_config = self::default_config();

        foreach( $repos_raw as $id => $config ){
            array_push( $repos, wp_parse_args( $config, $default_config ) );
        }

        return $repos;

    }

    public static function general_settings(){
        
        $settings = get_option( 'giw_general_settings', array() );
        $default_settings = self::default_general_settings();

        return wp_parse_args( $settings, $default_settings );

    }

}

Git_It_Write::init();

?>