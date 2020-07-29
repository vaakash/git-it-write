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

}

?>