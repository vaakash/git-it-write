<?php

if( ! defined( 'ABSPATH' ) ) exit;

use Symfony\Component\Yaml\Yaml;

class GIW_Parsedown extends ParsedownExtra{

    public $default_front_matter = array(
        'title' => '',
        'menu_order' => 0,
        'post_status' => 'publish',
        'post_excerpt' => '',
        'post_date' => '',
        'comment_status' => '',
        'page_template' => '',
        'taxonomy' => array(),
        'custom_fields' => array(),
        'featured_image' => '',
        'stick_post' => '',
        'skip_file' => ''
    );

    public $uploaded_images = array();

    public function __construct () {
        $this->BlockTypes['!'][] = 'Figure'; // Add blockFigure support for lines starting with !
    }

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
            
            /**
             * Uploaded images key contains the full relative path of the image file and does NOT start with slash.
             * Removing the prefix slash in $image_url to fetch the details.
             */
            $image_url = ltrim( $image_url, '/' );

            // Get the attachment URL from the uploaded images cache list
            if( array_key_exists( $image_url, $this->uploaded_images ) ){
                $image_data[ 'element' ][ 'attributes' ][ 'src' ] = $this->uploaded_images[ $image_url ][ 'url' ];

                // Add the image ID class to mimic default behavior
                $image_id_class = 'wp-image-' . $this->uploaded_images[ $image_url ][ 'id' ];
                if( array_key_exists( 'class', $image_data[ 'element' ][ 'attributes' ] ) ){
                    $image_data[ 'element' ][ 'attributes' ][ 'class' ] .= ' ' . $image_id_class;
                }else{
                    $image_data[ 'element' ][ 'attributes' ][ 'class' ] = $image_id_class;
                }
            }

        }

        return $image_data;

    }

    // Wraps the image with figure tag and adds caption. Thanks to https://gist.github.com/kantoniak/b1a5c7889e5583824487dc78d93da7cd
    public function blockFigure( $Line ) {

        GIW_Utils::log( $Line['text'] );

        // Check if the line matches the ![Alt text](image.png) {.class} format
        if ( 1 !== preg_match( "~^!\[.*?\]\(.*?\)\s?(\{.*\})?~", $Line[ 'text' ] ) ){
            return;
        }

        $InlineImage = $this->inlineImage( $Line );
        if ( !isset( $InlineImage ) ){
            return;
        }

        // TODO: Parse attributes like class, ID, width, height etc.
        $FigureBlock = array(
            'element' => array(
                'name' => 'figure',
                'handler' => 'elements',
                'attributes' => array(
                    'class' => 'wp-block-image size-full'
                ),
                'text' => array(
                    $InlineImage['element']
                )
            ),
        );

        // Add figcaption if title set
        if ( !empty( $InlineImage[ 'element' ][ 'attributes' ][ 'title' ] ) ){
            $InlineFigcaption = array(
                'element' => array(
                    'name' => 'figcaption',
                    'attributes' => array(
                        'class' => 'wp-element-caption'
                    ),
                    'text' => $InlineImage[ 'element' ][ 'attributes' ][ 'title' ]
                ),
            );

            $FigureBlock[ 'element' ][ 'text' ][] = $InlineFigcaption[ 'element' ];
        }

        return $FigureBlock;
    }

}

?>