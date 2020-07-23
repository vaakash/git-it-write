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
        $c->upload_images();
        $c->create_posts( $c->repo_structure, 0 );

        
        echo $c->parsedown->text('
---
title: This is with images post 2
order: 3
---

# This is a heading

- List 1
- List 2



Hers our logo (hover to see the title text):

Inline-style: 
![alt text](/_images/pic4.jpg "This is PIC4")

Reference-style: 
![alt text][logo]

[logo]: /_images/pic1.jpg "Logo Title Text 2"

        ')['html'];


        // $out = $c->get_item_content('https://raw.githubusercontent.com/vaakash/test/master/dir2/withfm.md');
        // print_r($out['front_matter']);
        // print_r($out['html']);


    }

}

G2W_Admin::init();

?>