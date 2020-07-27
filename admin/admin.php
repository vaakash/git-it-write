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
        
        if( !isset( $g[ 'action' ] ) ){
            $g[ 'action' ] = 'manage';
        }
        
        if( $g[ 'action' ] != 'manage' ){
            echo '<p><a href="' . self::link() . '" class="button"><span class="dashicons dashicons-arrow-left-alt"></span> Back</a></p>';
        }

        if( $g[ 'action' ] == 'manage' ){
            self::manage_repo();
        }
        
        if( $g[ 'action' ] == 'edit' ){
            self::edit_repo();
        }
        
        if( $g[ 'action' ] == 'new' ){
            self::new_repo();
        }
        
        echo '</div>';
        
        echo '</div>';

    }

    public static function manage_repo(){

        $all_repos = Github_To_WordPress::all_repositories();

        echo '<p><a href="' . self::link( 'new' ) . '" class="button button-primary"><span class="dashicons dashicons-plus"></span> Add a new repository to publish from</a></p>';

        echo '<div class="repo_list">';
        foreach( $all_repos as $id => $config ){

            if( $id == 0 ){
                continue;
            }

            echo '<div class="repo_item">';
            echo '<div>User: ' . $config[ 'username' ] . '</div>';
            echo '<div>Repository: ' . $config[ 'repository' ] . '</div>';
            echo '<div>Folder to publish from: ' . $config[ 'folder' ] . '</div>';
            echo '<footer><a href="' . self::link( 'edit', $id ) . '">Edit</a> | <a href="' . self::link( 'pull', $id ) . '">Pull posts</a></footer>';
            echo '</div>';
        }
        echo '</div>';

    }

    public static function new_repo(){
        self::edit_repo( 'new' );
    }

    public static function edit_repo( $action = 'edit' ){

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

    public static function save_settings(){

        if( $_POST && check_admin_referer( 'g2w_edit_nonce' ) ){

            

        }

    }

    public static function link( $action = false, $id = false ){
        
        $params[ 'page' ] = 'github-to-wordpress';
        if( $action ) $params[ 'action' ] = $action;
        if( $id ) $params[ 'id' ] = $id;

        return add_query_arg( $params, admin_url( 'options-general.php' ) );
        
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