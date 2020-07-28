<?php

class G2W_Admin{

    private static $pagehook = 'settings_page_github-to-wordpress';

    public static function init(){

        add_action( 'admin_menu', array( __class__, 'admin_menu' ) );

        add_action( 'admin_enqueue_scripts', array( __class__, 'enqueue_scripts' ) );

    }

    public static function admin_menu(){

        add_options_page( 'Github to WordPress', 'Github to WordPress', 'manage_options', 'github-to-wordpress', array( __class__, 'admin_page' ) );

    }

    public static function enqueue_scripts( $hook ){

        if( $hook == self::$pagehook ){

            wp_enqueue_style( 'g2w-admin-css', G2W_ADMIN_URL . '/css/style.css', array(), G2W_VERSION );

            //wp_enqueue_script( 'jquery' );
            //wp_enqueue_script( 'sc-admin-js', G2W_ADMIN_URL . '/js/script.js', array( 'jquery' ), G2W_VERSION );
        
        }

    }

    public static function admin_page(){

        echo '<div class="wrap">';
        echo '<div class="head_wrap">';
        echo '<h1 class="g2w_title">Github to WordPress <span class="title-count">' . G2W_VERSION . '</span></h1>';
        echo '</div>';
        
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

        echo '</div>';
        
        echo '</div>';

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

            echo '<footer>';
            echo '<a href="' . self::link( 'edit', $id ) . '">Edit</a> | ';
            echo '<a href="' . self::link( 'pull', $id, array( '_wpnonce' => wp_create_nonce( 'g2w_pull_nonce' ) ) ) . '">Pull posts</a> | ';
            echo '<a href="' . self::link( 'delete', $id, array( '_wpnonce' => wp_create_nonce( 'g2w_delete_nonce' ) ) ) . '">Delete</a>';
            echo '</footer>';

            echo '</div>';
        }
        echo '</div>';

    }

    public static function new_repo(){
        self::edit_repo( 'new' );
    }

    public static function edit_repo( $action = 'edit' ){

        self::save_settings();

        $all_repos = Github_To_WordPress::all_repositories();
        $g = self::clean_get();
        $id = 0;

        $page_title = ( $action == 'edit' ) ? 'Edit settings' : 'Add new repository to publish posts from';
        $save_button = ( $action == 'edit' ) ? 'Save settings' : 'Add repository' ;

        $values = Github_To_WordPress::default_config();

        if( $action == 'edit' ){

            if( !isset( $g[ 'id' ] ) || empty( $g[ 'id' ] ) ){
                echo '<p>Invalid ID provided to edit settings</p>';
                return;
            }

            $id = $g[ 'id' ];

            if( !isset( $all_repos[ $id ] ) ){
                echo '<p>Unable to find repository settings</p>';
            }

            $values = $all_repos[ $id ];

        }

        echo '<h2>' . $page_title . '</h2>';

        echo '<form method="post">';

        echo '<table class="widefat">';
        echo '<tbody>';

        echo '<tr>';
            echo '<td>Username</td>';
            echo '<td><input type="text" class="widefat" name="username" value="' . $values[ 'username' ] . '" /></td>';
        echo '</tr>';

        echo '<tr>';
            echo '<td>Repository</td>';
            echo '<td><input type="text" class="widefat" name="repository" value="' . $values[ 'repository' ] . '" /></td>';
        echo '</tr>';

        echo '<tr>';
            echo '<td>Folder to publish from</td>';
            echo '<td><input type="text" class="widefat" name="folder" value="' . $values[ 'folder' ] . '" /></td>';
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

        echo 'Pulling';

    }

    public static function save_settings(){

        if( $_POST && check_admin_referer( 'g2w_edit_nonce' ) ){

            $all_repos = Github_To_WordPress::all_repositories();
            $defaults = Github_To_WordPress::default_config();
            $p = wp_parse_args( self::clean_post(), $defaults );
            $values = array();
            $is_new = false;

            foreach( $defaults as $field => $default ){
                $values[ $field ] = isset( $p[ $field ] ) ? sanitize_text_field( $p[ $field ] ) : $default;
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