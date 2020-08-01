<?php

class G2W_Admin{

    private static $pagehook = 'settings_page_github-to-wordpress';

    public static function init(){

        add_action( 'admin_menu', array( __CLASS__, 'admin_menu' ) );

        add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_scripts' ) );

    }

    public static function admin_menu(){

        add_options_page( 'Github to WordPress', 'Github to WordPress', 'manage_options', 'github-to-wordpress', array( __CLASS__, 'admin_page' ) );

    }

    public static function enqueue_scripts( $hook ){

        if( $hook == self::$pagehook ){

            wp_enqueue_style( 'g2w-admin-css', G2W_ADMIN_URL . '/css/style.css', array(), G2W_VERSION );

            wp_enqueue_script( 'jquery' );
            wp_enqueue_script( 'sc-admin-js', G2W_ADMIN_URL . '/js/script.js', array( 'jquery' ), G2W_VERSION );
        
        }

    }

    public static function admin_page(){

        echo '<div class="wrap">';
        echo '<div class="head_wrap">';
        echo '<h1 class="g2w_title">Github to WordPress <span class="title-count">' . G2W_VERSION . '</span></h1>';
        echo '</div>';
        
        echo '<div id="main">';

        echo '<div id="content">';
        
        $g = self::clean_get();
        $action = isset( $g[ 'action' ] ) ? $g[ 'action' ] : 'manage';
        
        if( $action != 'manage' ){
            echo '<p><a href="' . self::link() . '" class="button"><span class="dashicons dashicons-arrow-left-alt"></span> Back</a></p>';
        }

        if( $action == 'manage' || empty( $action ) ){
            self::manage_repo();
        }
        
        if( $action == 'edit' ){
            self::edit_repo();
        }
        
        if( $action == 'new' ){
            self::new_repo();
        }
        
        if( $action == 'delete' ){
            self::delete_repo();
        }

        if( $action == 'pull' ){
            self::pull_posts();
        }

        if( $action == 'logs' ){
            self::logs();
        }

        echo '</div>'; // #content

        echo '<div id="sidebar">';
        
        echo '</div>';

        echo '</div>'; // #main
        
        echo '</div>';  // .wrap

    }

    public static function manage_repo(){

        $all_repos = Github_To_WordPress::all_repositories();

        echo '<p>';
        echo '<a href="' . self::link( 'new' ) . '" class="button button-primary"><span class="dashicons dashicons-plus"></span> Add a new repository to publish from</a> ';
        echo '<a href="' . self::link( 'logs' ) . '" class="button"><span class="dashicons dashicons-text"></span> Logs</a>';
        echo '</p>';

        echo '<h2>Configured repositories</h2>';

        if( empty( $all_repos ) || count( $all_repos ) == 1 ){
            echo '<p class="description">No repositories configured. Go ahead and add one !</p>';
        }

        echo '<div class="repo_list">';
        foreach( $all_repos as $id => $config ){

            if( $id == 0 ){
                continue;
            }

            echo '<div class="repo_item">';
            echo '<div>User: ' . $config[ 'username' ] . '</div>';
            echo '<div>Repository: ' . $config[ 'repository' ] . '</div>';
            echo '<div>Folder to publish from: ' . $config[ 'folder' ] . '</div>';

            echo '<div>Last publish on: ' . $config[ 'last_publish' ] . '</div>';

            echo '<footer>';
            echo '<a href="' . self::link( 'edit', $id ) . '">Edit</a> | ';
            echo '<a href="' . self::link( 'pull', $id, array( '_wpnonce' => wp_create_nonce( 'g2w_pull_nonce' ) ) ) . '">Pull posts</a> | ';
            echo '<a href="' . self::link( 'delete', $id, array( '_wpnonce' => wp_create_nonce( 'g2w_delete_nonce' ) ) ) . '">Delete</a>';
            echo '</footer>';

            echo '</div>';
        }
        echo '</div>';

        self::general_settings();

    }

    public static function new_repo(){
        self::edit_repo( 'new' );
    }

    public static function edit_repo( $action = 'edit' ){

        self::save_repo_settings();

        $all_repos = Github_To_WordPress::all_repositories();
        $g = self::clean_get();
        $id = 0;

        $page_title = ( $action == 'edit' ) ? 'Edit settings' : 'Add new repository to publish posts from';
        $save_button = ( $action == 'edit' ) ? 'Save settings' : 'Add repository' ;

        $values = Github_To_WordPress::default_config();

        if( $action == 'edit' ){

            if( !isset( $g[ 'id' ] ) || empty( $g[ 'id' ] ) ){
                self::print_notice( 'Invalid ID provided to edit settings', 'error' );
                return;
            }

            $id = $g[ 'id' ];

            if( !isset( $all_repos[ $id ] ) ){
                self::print_notice( 'Unable to find repository settings', 'error' );
                return;
            }

            $values = $all_repos[ $id ];

        }

        echo '<h2>' . $page_title . '</h2>';

        echo '<form method="post">';

        echo '<table class="widefat">';
        echo '<tbody>';

        echo '<tr>';
            echo '<td>Username</td>';
            echo '<td><input type="text" class="widefat" name="g2w_username" value="' . $values[ 'username' ] . '" /></td>';
        echo '</tr>';

        echo '<tr>';
            echo '<td>Repository</td>';
            echo '<td><input type="text" class="widefat" name="g2w_repository" value="' . $values[ 'repository' ] . '" /></td>';
        echo '</tr>';

        echo '<tr>';
            echo '<td>Folder to publish from</td>';
            echo '<td><input type="text" class="widefat" name="g2w_folder" value="' . $values[ 'folder' ] . '" /></td>';
        echo '</tr>';

        echo '<tr>';
            echo '<td>Post type to publish to</td>';
            echo '<td><input type="text" class="widefat" name="g2w_post_type" value="' . $values[ 'post_type' ] . '" /></td>';
        echo '</tr>';

        echo '</tbody>';
        echo '</table>';

        echo '<input type="text" name="g2w_id" value="' . $id . '" />';

        wp_nonce_field( 'g2w_edit_nonce' );

        echo '<p><button type="submit" class="button button-primary">' . $save_button . '</button></p>';

        echo '</form>';

    }

    public static function delete_repo(){

        $g = self::clean_get();

        if( isset( $g[ 'id' ] ) && check_admin_referer( 'g2w_delete_nonce' ) ){

            $all_repos = $all_repos = Github_To_WordPress::all_repositories();
            $id = $g[ 'id' ];
            if( isset( $all_repos[ $id ] ) ){
                unset( $all_repos[ $id ] );
            }else{
                self::print_notice( 'Invalid repository to delete !', 'error' );
                return;
            }

            if( update_option( 'g2w_repositories', $all_repos ) ){
                self::print_notice( 'Deleted repository configuration !' );
            }else{
                self::print_notice( 'Failed to delete repository configuration !', 'error' );
            }

        }else{
            self::print_notice( 'No repository ID provided to delete.', 'error' );
        }

    }

    public static function pull_posts(){

        $g = self::clean_get();

        if( !isset( $g[ 'id' ] ) || !check_admin_referer( 'g2w_pull_nonce' ) ){
            self::print_notice( 'No repository ID provided to pull posts from', 'error' );
            return;
        }

        echo '<h2>Pulling posts from Github</h2>';

        define( 'G2W_ON_GUI', true );

        echo '<div class="log_wrap">';
        G2W_Publish_Handler::publish_by_id( $g[ 'id' ] );
        echo '</div>';

    }

    public static function logs(){

        echo '<div class="log_wrap">';

        $lines = G2W_Utils::read_log();
        foreach( $lines as $line ){
            echo '<p>' . $line . '</p>';
        }

        echo '</div>';

    }

    public static function general_settings(){

        self::save_general_settings();

        echo '<h2>General settings</h2>';

        $values = Github_To_WordPress::general_settings();

        echo '<form method="post">';

        echo '<table class="widefat">';
        echo '<tbody>';

        echo '<tr>';
            echo '<td>Webhook secret</td>';
            echo '<td><input type="password"  class="webhook_secret" name="g2w_webhook_secret" value="' . $values[ 'webhook_secret' ] . '" autocomplete="new-password" /><button class="button">Toggle</button>';
            echo '<p>' . rest_url( '/g2w/v1/publish' ) . '</p>';
            echo '</td>';
        echo '</tr>';

        echo '</tbody>';
        echo '</table>';

        wp_nonce_field( 'g2w_gs_nonce' );

        echo '<p><button type="submit" class="button button-primary">Save settings</button></p>';

        echo '</form>';

    }
    
    public static function save_repo_settings(){

        if( $_POST && check_admin_referer( 'g2w_edit_nonce' ) ){

            $all_repos = Github_To_WordPress::all_repositories();
            $defaults = Github_To_WordPress::default_config();
            $p = wp_parse_args( self::clean_post(), $defaults );
            $values = array();
            $is_new = false;

            foreach( $defaults as $field => $default ){
                $form_field = 'g2w_' . $field;
                $values[ $field ] = isset( $p[ $form_field ] ) ? sanitize_text_field( $p[ $form_field ] ) : $default;
            }

            if( !isset( $p[ 'g2w_id' ] ) || empty( $p[ 'g2w_id' ] ) || !$p[ 'g2w_id' ] ){ // If no ID, then new item
                $is_new = true;
                if( empty( $all_repos ) ){
                    $all_repos[1] = $values;
                }else{
                    array_push( $all_repos, $values );
                }
            }else{
                $id = $p[ 'g2w_id' ];
                $all_repos[ $id ] = $values;
            }

            if( update_option( 'g2w_repositories', $all_repos ) ){
                if( $is_new ){
                    self::print_notice( 'Successfully added new repository settings !' );
                }else{
                    self::print_notice( 'Successfully saved the changes !' );
                }
            }else{
                self::print_notice( 'Failed to save the settings !', 'error' );
            }

        }

    }

    public static function save_general_settings(){

        if( $_POST && check_admin_referer( 'g2w_gs_nonce' ) ){
            
            $defaults = Github_To_WordPress::default_general_settings();
            $p = wp_parse_args( self::clean_post(), $defaults );

            $values = array();

            foreach( $defaults as $field => $default ){
                $form_field = 'g2w_' . $field;
                $values[ $field ] = isset( $p[ $form_field ] ) ? sanitize_text_field( $p[ $form_field ] ) : $default;
            }

            if( update_option( 'g2w_general_settings', $values ) ){
                self::print_notice( 'Successfully saved the changes !' );
            }else{
                self::print_notice( 'Failed to save the settings !', 'error' );
            }

        }

    }

    public static function link( $action = false, $id = false, $more = array() ){
        
        $params[ 'page' ] = 'github-to-wordpress';
        if( $action ) $params[ 'action' ] = $action;
        if( $id ) $params[ 'id' ] = $id;

        $params = array_merge( $params, $more );

        return add_query_arg( $params, admin_url( 'options-general.php' ) );
        
    }

    public static function print_notice( $msg = '', $type = 'success' ){

        if( $msg != '' ){
            echo '<div class="notice notice-' . $type . ' is-dismissible"><p>' . $msg . '</p></div>';
        }

    }

    public static function clean_get(){
        
        foreach( $_GET as $k=>$v ){
            $_GET[$k] = sanitize_text_field( $v );
        }

        return $_GET;
    }
    
    public static function clean_post(){
        
        return stripslashes_deep( $_POST );
        
    }

}

G2W_Admin::init();

?>