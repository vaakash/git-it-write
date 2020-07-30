<?php

class G2W_Utils{

    public static function log( $message = '' ){
        
        try{
            
            $file = G2W_PATH . 'logs/log.log';
            $line_tmpl = '%s - %s';
            
            $date = date('m/d/Y H:i');
            $line = sprintf( $line_tmpl, $date, print_r( $message, true ) );
            
            file_put_contents( $file, $line.PHP_EOL , FILE_APPEND | LOCK_EX );
            
            if( defined( 'G2W_ON_GUI' ) ){
                show_message( $line );
            }

        }catch( Exception $e ){
            
        }
        
    }

    public static function remove_extension_relative_url( $url, $allowed_file_types ){
        /**
         * Accepts only a relative URL. Starting with . or /
         * ./hello/abcd.md?param=value.md#heading => ./hello/abcd/?param=value.md#heading
        */

        $parts = parse_url( $url );

        if( !isset( $parts[ 'path' ] ) ){
            return $url;
        }

        $path_parts = pathinfo( $parts[ 'path' ] );
        if( !isset( $path_parts[ 'extension' ] ) ){ # No extension already
            return $url;
        }

        if( !in_array( strtolower( $path_parts[ 'extension' ] ), $allowed_file_types ) ){ # Extension is not part of the publish list, then return
            return $url;
        }

        $final_url = array();

        array_push( $final_url, $path_parts[ 'dirname' ] . '/' . $path_parts[ 'filename' ] . '/' );
        if( isset( $parts[ 'query' ] ) ) array_push( $final_url, '?' . $parts[ 'query' ] );
        if( isset( $parts[ 'fragment' ] ) ) array_push( $final_url, '#' . $parts[ 'fragment' ] );

        return implode( '', $final_url );

    }

}

?>