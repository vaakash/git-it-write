<?php

if( ! defined( 'ABSPATH' ) ) exit;

class GIW_Repository{

    public $repo;

    public $branch;

    public $user;

    public $parsedown;

    public $structure = array();

    public function __construct( $user, $repo, $branch ){

        $this->user = $user;
        $this->repo = $repo;
        $this->branch = $branch;

        $this->build_repo_structure();

    }

    public function get( $url ){

        $general_settings = Git_It_Write::general_settings();

        $username = $general_settings[ 'github_username' ];
        $access_token = $general_settings[ 'github_access_token' ];
        
        $args = array(
            'timeout' => 30, // Increased timeout for webhook processing
            'redirection' => 5,
            'httpversion' => '1.1',
            'blocking' => true,
            'headers' => array(
                'Authorization' => 'Basic ' . base64_encode($username . ':' . $access_token),
                'User-Agent' => 'WordPress-Plugin-Git-It-Write',
                'Accept' => 'application/vnd.github.v3+json',
                'Cache-Control' => 'no-cache, no-store, must-revalidate',
                'Pragma' => 'no-cache',
                'Expires' => '0'
            ),
            'sslverify' => true, // Ensure SSL verification is enabled
        ); 

        // Add retry logic for webhook processing
        $max_retries = 3;
        $retry_count = 0;
        
        do {
            $response = wp_remote_get( $url, $args );
            
            if( !is_wp_error( $response ) ) {
                $response_code = wp_remote_retrieve_response_code( $response );
                if( $response_code == 200 ) {
                    break; // Success, exit retry loop
                }
                GIW_Utils::log( 'HTTP Error Code: ' . $response_code . ' for URL: ' . $url );
            } else {
                GIW_Utils::log( 'WP Error on attempt ' . ($retry_count + 1) . ': ' . $response->get_error_message() . ' for URL: ' . $url );
            }
            
            $retry_count++;
            if( $retry_count < $max_retries ) {
                sleep(1); // Wait 1 second before retry
            }
            
        } while( $retry_count < $max_retries );

        if( is_wp_error( $response ) ) {
            GIW_Utils::log( 'Final Error after ' . $max_retries . ' attempts: ' . $response->get_error_message() );
            return false;
        }

        $response_code = wp_remote_retrieve_response_code( $response );
        if( $response_code != 200 ) {
            GIW_Utils::log( 'Final HTTP Error Code: ' . $response_code . ' after ' . $max_retries . ' attempts' );
            return false;
        }

        $body = wp_remote_retrieve_body( $response );

        return $body;

    }

    public function get_json( $url ){

        $content = $this->get( $url );
        if( !$content ){
            return false;
        }
        
        $decoded = json_decode( $content );
        if( json_last_error() !== JSON_ERROR_NONE ) {
            GIW_Utils::log( 'JSON Decode Error: ' . json_last_error_msg() . ' for URL: ' . $url );
            return false;
        }
        
        return $decoded;

    }

    public function tree_url(){
        return 'https://api.github.com/repos/' . $this->user . '/' . $this->repo . '/git/trees/' . $this->branch . '?recursive=1';
    }

    public function raw_url( $file_path ){
        // Remove timestamp parameter as it can cause issues with webhooks
        // Instead, rely on proper cache headers
        return 'https://raw.githubusercontent.com/' . $this->user . '/' . $this->repo . '/' . $this->branch . '/' . $file_path;
    }

    public function content_url( $file_path ){
        return 'https://api.github.com/repos/' . $this->user . '/' . $this->repo . '/contents/' . $file_path . '?ref=' . $this->branch;
    }

    public function github_url( $file_path ){
        return 'https://github.com/' . $this->user . '/' . $this->repo . '/blob/' . $this->branch . '/' . $file_path;
    }

    public function add_to_structure( $structure, $path_split, $item ){

        if( count( $path_split ) == 1 ){

            $full_file_name = $path_split[0];
            $file_info = pathinfo( $full_file_name );
            $file_slug = $file_info[ 'filename' ];
            $extension = array_key_exists( 'extension', $file_info ) ? $file_info[ 'extension' ] : '';

            $structure[ $file_slug ] = array(
                'type' => 'file',
                'raw_url' => $this->raw_url( $item->path ),
                'content_url' => $this->content_url( $item->path ),
                'github_url' => $this->github_url( $item->path ),
                'rel_url' => $item->path,
                'sha' => $item->sha,
                'file_type' => strtolower( $extension )
            );

            return $structure;

        }else{

            $first_dir = array_shift( $path_split );

            if( !array_key_exists( $first_dir, $structure ) ){
                $structure[ $first_dir ] = array(
                    'items' => array(),
                    'type' => 'directory'
                );
            }

            $structure[ $first_dir ][ 'items' ] = $this->add_to_structure( $structure[ $first_dir ][ 'items' ], $path_split, $item );
            return $structure;
        }

    }

    public function build_repo_structure(){

        GIW_Utils::log( 'Building repo structure ...' );

        $tree_url = $this->tree_url();
        GIW_Utils::log( 'Fetching tree from: ' . $tree_url );
        
        $data = $this->get_json( $tree_url );

        if( !$data ){
            GIW_Utils::log( 'Failed to fetch the repository tree! ['. $tree_url .']' );
            return false;
        }

        if( !property_exists( $data, 'tree' ) ){
            $error_msg = property_exists( $data, 'message' ) ? $data->message : 'Unknown error';
            GIW_Utils::log( 'Repository not found on Github! ['. $tree_url .']. Error message [' . $error_msg . ']' );
            return false;
        }

        GIW_Utils::log( 'Found ' . count($data->tree) . ' items in repository tree' );

        foreach( $data->tree as $item ){
            if( $item->type == 'tree' ){
                continue;
            }

            $path = $item->path;
            $path_split = explode( '/', $path );
            $this->structure = $this->add_to_structure( $this->structure, $path_split, $item );
        }

        GIW_Utils::log( 'Repository structure built successfully' );
        return true;

    }

    public function get_item_content( $item_props ){

        if( !is_array($item_props) || !isset($item_props['rel_url']) ) {
            GIW_Utils::log( 'Invalid item properties provided' );
            return false;
        }

        // For text-based files (like markdown), use the GitHub API which provides more reliable content
        // For binary files (like images), use the raw URL
        $text_file_types = array('md', 'markdown', 'txt', 'html', 'htm', 'css', 'js', 'json', 'xml', 'yml', 'yaml');
        
        $file_type = isset($item_props['file_type']) ? $item_props['file_type'] : '';
        
        if (in_array($file_type, $text_file_types)) {
            // Use GitHub API for text files to avoid caching issues
            GIW_Utils::log('Fetching content via GitHub API for: ' . $item_props['rel_url']);
            
            if( !isset($item_props['content_url']) ) {
                GIW_Utils::log('Content URL not available for: ' . $item_props['rel_url']);
                return false;
            }
            
            $content_data = $this->get_json($item_props['content_url']);
            
            if ($content_data && isset($content_data->content)) {
                $content = base64_decode($content_data->content);
                if( $content !== false ) {
                    GIW_Utils::log('Successfully fetched content via API for: ' . $item_props['rel_url']);
                    return $content;
                } else {
                    GIW_Utils::log('Failed to decode base64 content for: ' . $item_props['rel_url']);
                }
            } else {
                GIW_Utils::log('Failed to fetch content via API for: ' . $item_props['rel_url']);
            }
            
            // Fall back to raw URL if API fails
            if( isset($item_props['raw_url']) ) {
                GIW_Utils::log('Falling back to raw URL for: ' . $item_props['rel_url']);
                $content = $this->get( $item_props[ 'raw_url' ] );
            } else {
                GIW_Utils::log('Raw URL not available for: ' . $item_props['rel_url']);
                return false;
            }
        } else {
            // For binary files, use the raw URL
            if( !isset($item_props['raw_url']) ) {
                GIW_Utils::log('Raw URL not available for: ' . $item_props['rel_url']);
                return false;
            }
            
            GIW_Utils::log('Fetching binary content via raw URL for: ' . $item_props['rel_url']);
            $content = $this->get( $item_props[ 'raw_url' ] );
        }

        if( !$content ){
            GIW_Utils::log('Failed to fetch any content for: ' . $item_props['rel_url']);
            return false;
        }

        GIW_Utils::log('Successfully fetched content for: ' . $item_props['rel_url']);
        return $content;
    
    }

}

?>