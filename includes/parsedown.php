<?php

use Symfony\Component\Yaml\Yaml;

class G2W_Parsedown extends Parsedown{

    public $default_front_matter = array(
        'title' => '',
        'order' => ''
    );

    public function text( $text ){

        $pattern = '/^[\s\r\n]?---[\s\r\n]?$/sm';
        $parts = preg_split( $pattern, PHP_EOL.ltrim( $text ) );

        if ( count( $parts ) < 3 ) {
            return array(
                'front_matter' => $this->default_front_matter,
                'html' => parent::text( $text )
            );
        }

        $front_matter = Yaml::parse( trim( $parts [1] ) );
        $front_matter = wp_parse_args( $front_matter, $this->default_front_matter );

        $markdown = implode( PHP_EOL . '---' . PHP_EOL, array_slice( $parts, 2 ) );

        return array(
            'front_matter' => $front_matter,
            'html' => parent::text( $markdown )
        );

    }

    

}

?>