<?php

if( ! defined( 'ABSPATH' ) ) exit;

class GIW_Shortcodes{

    public static function init(){

        add_shortcode( 'giw_edit_link', array( __CLASS__, 'edit_link' ) );

    }

    public static function edit_link( $atts ){

        global $post;

        $current_post_id = '';
        if( is_object( $post ) ){
            $current_post_id = $post->ID;
        }

        $atts = shortcode_atts( array(
            'post_id' => $current_post_id,
            'text' => 'Edit this page',
            'icon' => '<i class="fas fa-pen"></i> &nbsp; '
        ), $atts );

        if( empty( $atts[ 'post_id' ] ) ){
            return '';
        }

        $meta = get_post_meta( $atts[ 'post_id' ], '', true );

        if( !array_key_exists( 'github_url', $meta ) || empty( $meta[ 'github_url' ][0] ) ){
            return '';
        }

        $github_url = $meta[ 'github_url' ][0];

        return '<a href="' . $github_url . '" class="giw-edit_link" target="_blank" rel="noreferrer noopener">' . $atts[ 'icon' ] . $atts[ 'text' ] . '</a>';

    }

}

GIW_Shortcodes::init();

?>