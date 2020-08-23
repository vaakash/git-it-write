<?php

if( ! defined( 'ABSPATH' ) ) exit;

use Symfony\Component\Yaml\Yaml;

class GIW_Parsedown extends ParsedownExtra{

    public $default_front_matter = array(
        'title' => '',
        'menu_order' => 0,
        'post_status' => 'publish',
        'post_excerpt' => '',
        'taxonomy' => array(),
        'custom_fields' => array()
    );

    public $uploaded_images = array();

    // Parses the front matter and the markdown from the content
    public function parse_content( $text ){

        $pattern = '/^[\s\r\n]?---[\s\r\n]?$/sm';
        $parts = preg_split( $pattern, PHP_EOL.ltrim( $text ) );

        if ( count( $parts ) < 3 ) {
            return array(
                'front_matter' => $this->default_front_matter,
                'markdown' => $text
            );
        }

        $front_matter = Yaml::parse( trim( $parts [1] ) );
        $front_matter = wp_parse_args( $front_matter, $this->default_front_matter );

        $markdown = implode( PHP_EOL . '---' . PHP_EOL, array_slice( $parts, 2 ) );

        return array(
            'front_matter' => $front_matter,
            'markdown' => $markdown
        );

    }

    public function inlineLink( $excerpt ){

        $link_data = parent::inlineLink( $excerpt );

        if( empty( $link_data ) ){
            return $link_data;
        }

        $href = $link_data[ 'element' ][ 'attributes' ][ 'href' ];

        // #1 - Since permalinks end as folder in WordPress, relative URLs are prefixed with .. to access bring down the relative path by one level.
        $first_character = substr( $href, 0, 1 );
        $prefix = '';

        if( $first_character == '.' ){
            $prefix = '../';
        }

        // #2 - Remove .md file extension in relative URLs
        if( in_array( $first_character, array( '.', '/' ) ) ){
            $href = GIW_Utils::remove_extension_relative_url( $href );
        }

        $link_data[ 'element' ][ 'attributes' ][ 'href' ] = $prefix . $href;

        return $link_data;

    }

    public function inlineImage( $excerpt ){

        $image_data = parent::inlineImage( $excerpt );

        if( empty( $image_data ) ){
            return $image_data;
        }

        $image_url = $image_data[ 'element' ][ 'attributes' ][ 'src' ];

        // Check if the image URL is relative to the root and is under the _images directory
        $first_character = substr( $image_url, 0, 1 );

        if( $first_character == '/' ){

            $parts = explode( '/', $image_url ); // Expecting a path like /_images/pic1.jpg

            if( count( $parts ) < 3 ){ // If less than 3 parts then continue with original URL
                return $image_data;
            }

            if( $parts[1] != '_images' ){ // If the directory is not _images then continue with original
                return $image_data;
            }
            
            // Get the file name under the _images directory
            $image_file_name = $parts[2];

            // Remove the file extension from the file name to match that of the repo structure built
            $image_file_parts = explode( '.', $image_file_name );
            array_pop( $image_file_parts );
            $image_file_name = implode( '', $image_file_parts );

            // Get the attachment URL from the uploaded images cache list
            if( array_key_exists( $image_file_name, $this->uploaded_images ) ){
                $image_data[ 'element' ][ 'attributes' ][ 'src' ] = $this->uploaded_images[ $image_file_name ][ 'url' ];
            }

        }

        return $image_data;

    }

}

?>