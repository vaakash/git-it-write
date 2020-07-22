<?php

class G2W_Admin{

    public static function init(){

        add_action( 'admin_menu', array( __class__, 'admin_menu' ) );

    }

    public static function admin_menu(){

        add_options_page( 'Github to WordPress', 'Github to WordPress', 'manage_options', 'github-to-wordpress', array( __class__, 'admin_page' ) );

    }

    public static function admin_page(){

        $c = new G2W( 'vaakash', 'test', 'doc');
        $c->build_structure();
    
        $c->create_posts( $c->repo_structure, 0 );

        // $out = $c->get_item_content('https://raw.githubusercontent.com/vaakash/test/master/dir2/withfm.md');
        // print_r($out['front_matter']);
        // print_r($out['html']);


    }

}

G2W_Admin::init();

?>