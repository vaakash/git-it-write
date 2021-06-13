<?php

if( ! defined( 'ABSPATH' ) ) exit;

class GIW_Admin{

    private static $pagehook = 'settings_page_git-it-write';

    public static function init(){

        add_action( 'admin_menu', array( __CLASS__, 'admin_menu' ) );

        add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_scripts' ) );

    }

    public static function admin_menu(){

        add_options_page( 'Git it Write', 'Git it Write', 'manage_options', 'git-it-write', array( __CLASS__, 'admin_page' ) );

    }

    public static function enqueue_scripts( $hook ){

        if( $hook == self::$pagehook ){

            wp_enqueue_style( 'giw-admin-css', GIW_ADMIN_URL . '/css/style.css', array(), GIW_VERSION );

            wp_enqueue_script( 'jquery' );
            wp_enqueue_script( 'sc-admin-js', GIW_ADMIN_URL . '/js/script.js', array( 'jquery' ), GIW_VERSION );
        
        }

    }

    public static function admin_page(){

        echo '<div class="wrap">';
        echo '<div class="head_wrap">';
        echo '<h1 class="giw_title">Git it Write <span class="title-count">' . GIW_VERSION . '</span></h1>';
        echo '</div>';

        echo '<div id="main">';

        echo '<div id="content">';
        
        $g = self::clean_get();
        $action = isset( $g[ 'action' ] ) ? $g[ 'action' ] : 'manage';
        
        if( $action != 'manage' ){
            echo '<p class="toolbar">';
            echo '<a href="' . self::link() . '" class="button"><span class="dashicons dashicons-arrow-left-alt"></span>Back</a>';
            self::toolbar_extra();
            echo '</p>';
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
            self::sidebar();
        echo '</div>';

        echo '</div>'; // #main
        
        echo '</div>';  // .wrap

    }

    public static function manage_repo(){

        $all_repos = Git_It_Write::all_repositories();

        echo '<p class="toolbar">';
        echo '<a href="' . self::link( 'new' ) . '" class="button button-primary"><span class="dashicons dashicons-plus"></span> Add a new repository to publish posts from</a>';
        echo '<a href="' . self::link( 'logs' ) . '" class="button"><span class="dashicons dashicons-text"></span> Logs</a>';
        self::toolbar_extra();
        echo '</p>';

        echo '<h2>Configured repositories</h2>';

        if( empty( $all_repos ) || count( $all_repos ) == 1 ){
            echo '<p class="description">No repositories configured. Go ahead and add one ! See <a href="https://www.aakashweb.com/docs/git-it-write/" target="_blank">getting started</a> for more information.</p>';
        }else{

        echo '<table class="wp-list-table widefat fixed striped">';
        echo '<thead>
            <tr>
                <th width="40px">ID</th>
                <th>Repository</th>
                <th>Branch</th>
                <th>Folder to publish</th>
                <th>Post type to publish under</th>
                <th>Last published</th>
            </tr>
        </thead>
        <tbody>';

        foreach( $all_repos as $id => $config ){

            if( $id == 0 ){
                continue;
            }

            echo '<tr>';

            echo '<th>' . $id . '</th>';

            echo '<td class="title column-title has-row-actions column-primary page-title">';
            echo '<a href="' . self::link( 'edit', $id ) . '" class="row-title">' . $config[ 'username' ] . '/' . $config[ 'repository' ] . '</a>';
            echo '<div class="row-actions">';
            echo '<span><a href="' . self::link( 'edit', $id ) . '">Edit</a> | </span>';
            echo '<span><a href="' . self::link( 'pull', $id ) . '">Pull posts</a> | </span>';
            echo '<span class="trash"><a href="' . self::link( 'delete', $id, array( '_wpnonce' => wp_create_nonce( 'giw_delete_nonce' ) ) ) . '">Delete</a></span>';
            echo '</div>';
            '</td>';

            echo '<td>' . ( empty( $config[ 'branch' ] ) ? 'master' : $config[ 'branch' ] ) . '</td>';
            echo '<td>' . ( empty( $config[ 'folder' ] ) ? 'Root' : $config[ 'folder' ] ) . '</td>';
            echo '<td>' . $config[ 'post_type' ] . '</td>';
            echo '<td>' . ( $config[ 'last_publish' ] == 0 ? '-' : human_time_diff( $config[ 'last_publish' ] ) . ' ago' ) . '</td>';

            echo '</tr>';
        }

        echo '</tbody>';
        echo '</table>';

        } // End if

        self::general_settings();

    }

    public static function new_repo(){
        self::edit_repo( 'new' );
    }

    public static function edit_repo( $action = 'edit' ){

        self::save_repo_settings();

        $all_repos = Git_It_Write::all_repositories();
        $g = self::clean_get();
        $id = 0;

        $page_title = ( $action == 'edit' ) ? 'Edit repository settings' : 'Add new repository to publish posts from';
        $save_button = ( $action == 'edit' ) ? 'Save settings' : 'Add repository' ;

        $values = Git_It_Write::default_config();

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

        echo '<table class="form-table widefat">';
        echo '<tbody>';

        echo '<tr>';
            echo '<td style="width: 300px">Github username/owner</td>';
            echo '<td><input type="text" name="giw_username" value="' . $values[ 'username' ] . '" required="required" />';
            echo '<p class="description">The username of the Github repository</p>';
            echo '</td>';
        echo '</tr>';

        echo '<tr>';
            echo '<td>Repository name</td>';
            echo '<td><input type="text" name="giw_repository" value="' . $values[ 'repository' ] . '" required="required" />';
            echo '<p class="description">The name of the Github repository to pull and publish posts from</p>';
            echo '</td>';
        echo '</tr>';

        echo '<tr>';
            echo '<td>Branch to publish from</td>';
            echo '<td><input type="text"name="giw_branch" value="' . $values[ 'branch' ] . '" />';
            echo '<p class="description">The name of the repository branch to pull and publish posts from. Leave blank to default to "master". Example: main</p>';
            echo '</td>';
        echo '</tr>';

        echo '<tr>';
            echo '<td>Folder to publish from</td>';
            echo '<td><input type="text"name="giw_folder" value="' . $values[ 'folder' ] . '" />';
            echo '<p class="description">The folder in the repository from which posts have to be published. Leave blank to publish from the root of the repository. Example: website/main/docs</p>';
            echo '</td>';
        echo '</tr>';

        echo '<tr>';
            echo '<td>Post type to publish to</td>';
            echo '<td>' . GIW_utils::post_type_selector( 'giw_post_type', $values[ 'post_type' ] );
            echo '<p class="description">The post type to publish the posts under. Hierarchial post types are preferred as they support level by level pages.</p>';
            echo '</td>';
        echo '</tr>';

        echo '<tr>';
            echo '<td>Author to set for the posts</td>';
            echo '<td>' . wp_dropdown_users( array('name' => 'giw_post_author', 'selected' => $values[ 'post_author' ], 'echo' => false ) );
            echo '<p class="description">The user to be set as post author for all the posts pulled from this repository</p>';
            echo '</td>';
        echo '</tr>';

        echo '<tr>';
            echo '<td>Post content template</td>';
            echo '<td>';
            wp_editor( $values[ 'content_template' ], 'giw_content_template', array(
                'media_buttons' => false,
                'teeny' => true,
                'textarea_rows' => 4
            ));
            echo '<p class="description">The template of the post content. Use any text, HTML, shortcode you would like to be added to all the posts when they are published. Supported placeholder <code>%%content%%</code> (The HTML of the pulled post). Use shortcode <code>[giw_edit_link]</code> to insert a link of the source Github file to edit and collaborate. You might need to "Pull all the files" to update the post if the template is changed.</p>';
            echo '</td>';
        echo '</tr>';

        echo '</tbody>';
        echo '</table>';

        echo '<input type="hidden" name="giw_id" value="' . $id . '" />';

        wp_nonce_field( 'giw_edit_nonce' );

        echo '<p><button type="submit" class="button button-primary">' . $save_button . '</button></p>';

        echo '</form>';

    }

    public static function delete_repo(){

        $g = self::clean_get();

        if( isset( $g[ 'id' ] ) && check_admin_referer( 'giw_delete_nonce' ) ){

            $all_repos = $all_repos = Git_It_Write::all_repositories();
            $id = $g[ 'id' ];
            if( isset( $all_repos[ $id ] ) ){
                unset( $all_repos[ $id ] );
            }else{
                self::print_notice( 'Invalid repository to delete !', 'error' );
                return;
            }

            if( update_option( 'giw_repositories', $all_repos ) ){
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

        if( !isset( $g[ 'id' ] ) ){
            self::print_notice( 'No repository ID provided to pull posts from', 'error' );
            return;
        }

        $id = $g[ 'id' ];

        echo '<h2>Pull posts from Github for [' . $id . ']</h2>';

        echo '<table class="widefat striped">';
        echo '<tbody>
        <tr>
            <th>To pull only the latest changes made to the repository and publish posts, select this option</td>
            <td><a class="button" href="' . self::link( 'pull', $id, array( 'pull' => 'changes', '_wpnonce' => wp_create_nonce( 'giw_pull_nonce' ) ) ) . '">Pull only changes</a></td>
        </tr>
        <tr>
            <th>To pull all the items even though unchanged and to overwrite all the published posts related to this repository, select this option</td>
            <td><a class="button" href="' . self::link( 'pull', $id, array( 'pull' => 'force', '_wpnonce' => wp_create_nonce( 'giw_pull_nonce' ) ) ) . '">Pull all the files</a></td>
        </tr>
        </tbody>';
        echo '</table>';

        if( !isset( $g[ 'pull' ] ) || !check_admin_referer( 'giw_pull_nonce' ) ){
            return;
        }

        echo '<h2>Pulling posts [' . $g[ 'pull' ] . ']</h2>';

        define( 'GIW_ON_GUI', true );
        if( $g[ 'pull' ] == 'force' ){
            define( 'GIW_PUBLISH_FORCE', true );
        }

        echo '<div class="log_wrap">';
        GIW_Publish_Handler::publish_by_id( $id );
        echo '</div>';

    }

    public static function logs(){

        echo '<div class="log_wrap">';

        $lines = GIW_Utils::read_log();
        foreach( $lines as $line ){
            echo '<p>' . $line . '</p>';
        }

        echo '</div>';

    }

    public static function general_settings(){

        self::save_general_settings();

        echo '<h2>General settings</h2>';

        $values = Git_It_Write::general_settings();

        echo '<form method="post">';

        echo '<table class="form-table widefat">';
        echo '<tbody>';

        echo '<tr>';
            echo '<td style="width: 200px">Webhook secret</td>';
            echo '<td><input type="password" class="webhook_secret" name="giw_webhook_secret" value="' . $values[ 'webhook_secret' ] . '" autocomplete="new-password" /> &nbsp;<button class="button">Toggle view</button>';
            echo '<p class="description">Go to Github repository settings --> Webhook and add a webhook for the payload URL <code>' . rest_url( '/giw/v1/publish' ) . '</code> if you would like to automatically publish the changes whenever repository is updated.</p>';
            echo '<p class="description">Select content-type as <code>application/json</code> and enter a secret text. Provide the same secret text in the above field. Select "Just the push event" for the webhook trigger. Make sure all the repositories you would like to automatically update have the same payload URL and the secret.</p>';
            echo '</td>';
        echo '</tr>';

        echo '</tbody>';
        echo '</table>';

        wp_nonce_field( 'giw_gs_nonce' );

        echo '<p><button type="submit" class="button button-primary">Save settings</button></p>';

        echo '</form>';

    }
    
    public static function save_repo_settings(){

        if( $_POST && check_admin_referer( 'giw_edit_nonce' ) ){

            $all_repos = Git_It_Write::all_repositories();
            $defaults = Git_It_Write::default_config();
            $p = wp_parse_args( self::clean_post(), $defaults );
            $values = array();
            $is_new = false;

            foreach( $defaults as $field => $default ){
                $form_field = 'giw_' . $field;
                $values[ $field ] = isset( $p[ $form_field ] ) ? wp_kses_post( $p[ $form_field ] ) : $default;
            }

            if( !isset( $p[ 'giw_id' ] ) || empty( $p[ 'giw_id' ] ) || !$p[ 'giw_id' ] ){ // If no ID, then new item
                $is_new = true;
                if( empty( $all_repos ) ){
                    $all_repos[1] = $values;
                }else{
                    array_push( $all_repos, $values );
                }
            }else{
                $id = $p[ 'giw_id' ];
                $all_repos[ $id ] = $values;
            }

            if( update_option( 'giw_repositories', $all_repos ) ){
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

        if( $_POST && check_admin_referer( 'giw_gs_nonce' ) ){
            
            $defaults = Git_It_Write::default_general_settings();
            $p = wp_parse_args( self::clean_post(), $defaults );

            $values = array();

            foreach( $defaults as $field => $default ){
                $form_field = 'giw_' . $field;
                $values[ $field ] = isset( $p[ $form_field ] ) ? sanitize_text_field( $p[ $form_field ] ) : $default;
            }

            if( update_option( 'giw_general_settings', $values ) ){
                self::print_notice( 'Successfully saved the changes !' );
            }else{
                self::print_notice( 'Failed to save the settings !', 'error' );
            }

        }

    }

    public static function sidebar(){

        echo '<div class="side_card">';
        echo '<h2><span class="dashicons dashicons-info"></span> Get updates</h2>';
        echo '<p>Get updates on the WordPress plugins, tips and tricks to enhance your WordPress experience. No spam.</p>';

    echo '<form class="subscribe_form" action="https://aakashweb.us19.list-manage.com/subscribe/post?u=b7023581458d048107298247e&amp;id=ef5ab3c5c4" method="post" name="mc-embedded-subscribe-form" target="_blank" novalidate>
        <input type="email" value="" name="EMAIL" class="required subscribe_email_box" id="mce-EMAIL" placeholder="Your email address">
        <div style="position: absolute; left: -5000px;" aria-hidden="true"><input type="text" name="b_b7023581458d048107298247e_ef5ab3c5c4" tabindex="-1" value=""></div>
        <input type="submit" value="Subscribe" name="subscribe" id="mc-embedded-subscribe" class="button subscribe_btn">
    </form>';

        echo '<a href="https://www.facebook.com/aakashweb" target="_blank" class="cta_link">Follow me on Facebook <span class="dashicons dashicons-arrow-right-alt"></span></a>';
        echo '<a href="https://www.twitter.com/aakashweb" target="_blank" class="cta_link">Follow me on Twitter <span class="dashicons dashicons-arrow-right-alt"></span></a>';
        echo '</div>';

        echo '<div class="side_card">';
        echo '<h2><span class="dashicons dashicons-sos"></span> Help &amp; Support</h2>';
        echo '<p>Got any issue or not sure how to achieve what you are looking for with the plugin or have any idea or missing feature ? Let me know. Please post a topic in the forum for an answer.</p>';
        echo '<a class="cta_link" href="https://www.aakashweb.com/forum/discuss/wordpress-plugins/git-it-write/" target="_blank">Visit the support forum <span class="dashicons dashicons-arrow-right-alt"></span></a>';
        echo '<a class="cta_link" href="https://www.aakashweb.com/docs/git-it-write/" target="_blank">See plugin documentation <span class="dashicons dashicons-arrow-right-alt"></span></a>';
        echo '</div>';

        echo '<p><a href="https://github.com/vaakash/git-it-write" class="button side_btn" target="_blank"><span class="dashicons dashicons-editor-code"></span> Contribute on Github</a></p>';

        echo '<p><a href="https://twitter.com/intent/tweet?hashtags=wordpress,markdown,github&related=aakashweb&text=Check%20it%20out%20-%20%22Git%20it%20Write%22%20a%20new%20WordPress%20plugin%20to%20publish%20posts%20from%20Github%20using%20markdown%20files%20and%20allow%20people%20to%20collaborate%20%E2%9C%8D%20https://wordpress.org/plugins/git-it-write/" class="button side_btn" target="_blank"><span class="dashicons dashicons-share"></span> Share with friends</a></p>';

    }

    public static function toolbar_extra(){
        echo '<span class="extra">';
        echo '<a href="https://www.paypal.me/vaakash/6" target="_blank" class="button">☕ Buy me a Coffee !</a>';
        echo '<a href="https://wordpress.org/support/plugin/git-it-write/reviews/?rate=5#new-post" target="_blank" class="button">⭐ Rate this plugin</a>';
        echo '</span>';
    }

    public static function link( $action = false, $id = false, $more = array() ){
        
        $params[ 'page' ] = 'git-it-write';
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

GIW_Admin::init();

?>