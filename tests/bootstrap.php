<?php
/**
 * Test bootstrap - stubs WordPress functions so we can test plugin code standalone.
 * Run: php tests/run-tests.php
 */

define( 'ABSPATH', '/tmp/fake-wp/' );
define( 'GIW_PATH', dirname( __DIR__ ) . '/' );
define( 'GIW_VERSION', '2.0-test' );

// ── WordPress function stubs ──

function wp_parse_args( $args, $defaults = array() ){
    if( is_array( $args ) ){
        return array_merge( $defaults, $args );
    }
    return $defaults;
}

function sanitize_title( $title ){
    return strtolower( preg_replace( '/[^a-zA-Z0-9\-]/', '-', $title ) );
}

function apply_filters( $tag, $value ){
    return $value;
}

class WP_Error {
    protected $code;
    protected $message;

    public function __construct( $code = '', $message = '' ){
        $this->code = $code;
        $this->message = $message;
    }

    public function get_error_message(){
        return $this->message;
    }

    public function get_error_code(){
        return $this->code;
    }
}

function is_wp_error( $thing ){
    return $thing instanceof WP_Error;
}

// ── GIW_Utils stub that captures log messages ──

class GIW_Utils {
    public static $logs = array();

    public static function log( $message ){
        if( is_array( $message ) || is_object( $message ) ){
            self::$logs[] = print_r( $message, true );
        } else {
            self::$logs[] = (string) $message;
        }
    }

    public static function clear_logs(){
        self::$logs = array();
    }

    public static function get_logs(){
        return self::$logs;
    }

    public static function remove_extension_relative_url( $url ){
        return preg_replace( '/\.md$/', '', $url );
    }

    public static function process_content_template( $template, $html ){
        return $html;
    }

    public static function process_date( $date ){
        return $date;
    }

    public static function get_uploaded_images(){
        return array();
    }
}

// ── Load composer autoloader (real Symfony YAML + Parsedown) ──

$autoloader = GIW_PATH . 'vendor/autoload.php';
if( !file_exists( $autoloader ) ){
    echo "ERROR: vendor/autoload.php not found. Run 'composer install' first.\n";
    exit(1);
}
require_once $autoloader;

// ── Load plugin file under test ──

require_once GIW_PATH . 'includes/parsedown.php';
