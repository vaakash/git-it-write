<?php

if( ! defined( 'ABSPATH' ) ) exit;

class GIW_Webhook{

    public static function init(){

        add_action( 'rest_api_init', function () {
            register_rest_route( 'giw/v1', '/publish', array(
                'methods' => 'POST',
                'callback' => array( __CLASS__, 'handle_webhook'),
                'permission_callback' => array( __CLASS__, 'check_permission' )
            ));
        });

    }

    public static function handle_webhook( WP_REST_Request $req ){

        if( $req->get_header( 'X-GitHub-Delivery' ) ){
            GIW_Utils::log('Got webhook delivery ' . $req->get_header( 'X-GitHub-Delivery' ) );
        }

        // User agent check
        if( !$req->get_header( 'User-Agent' ) ){
            return self::error( 'no_user_agent', 'No user agent', array( 'status' => 401 ) );
        }

        if( strpos( $req->get_header( 'User-Agent' ), 'GitHub-Hookshot' ) === false ){
            return self::error( 'who_are_you', 'Who are you ?', array( 'status' => 403 ) );
        }

        // Github event check
        if( !$req->get_header( 'X-GitHub-Event' ) ){
            return self::error( 'no_event', 'No event', array( 'status' => 400 ) );
        }

        $event = $req->get_header( 'X-GitHub-Event' );
        if( !in_array( $event, array( 'ping', 'push' ) ) ){
            return self::error( 'unsupported_event', 'Unsupported event', array( 'status' => 400 ) );
        }

        // Check signature
        if( !$req->get_header( 'X-Hub-Signature' ) ){
            return self::error( 'no_secret_configured', 'No secret configured', array( 'status' => 401 ) );
        }

        $got_signature = $req->get_header( 'X-Hub-Signature' );

        $settings = Git_It_Write::general_settings();
        $secret = trim( $settings[ 'webhook_secret' ] );

        if( empty( $secret ) ){
            return self::error( 'no_server_secret', 'No secret configured on server', array( 'status' => 500 ) );
        }

        if( !hash_equals( 'sha1=' . hash_hmac( 'sha1', $req->get_body(), $secret ), $got_signature ) ){
            return self::error( 'signature_mismatch', 'Signature mismatch', array( 'status' => 400 ) );
        }

        if( $event == 'ping' ){
            return 'pong';
        }

        $json = $req->get_json_params();

        if( !isset( $json[ 'repository' ] ) || !isset( $json[ 'repository' ][ 'full_name' ] ) ){
            return self::error( 'invalid_data', 'Invalid data', array( 'status' => 400 ) );
        }

        $repo_full_name = $json[ 'repository' ][ 'full_name' ];

        $result = GIW_Publish_Handler::publish_by_repo_full_name( $repo_full_name );

        GIW_Utils::log( 'Successfully honored webhook event.' );

        return $result;

    }

    public static function error( $code, $message, $data ){
        GIW_Utils::log( 'Error - ' . $message );
        return new WP_Error( $code, $message, $data );
    }

    public static function check_permission( $request ){
        // No authentication needed right now. The main callback has various checks on the requestor.
        // TODO - Move the checks in main callback here
        return true;
    }

}

GIW_Webhook::init();

?>