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

        $request = wp_remote_get( $url );

        if( is_wp_error( $request ) ) {
            return false;
        }

        $body = wp_remote_retrieve_body( $request );

        return $body;

    }

    public function get_json( $url ){
        $content = $this->get( $url );
        if( !$content ){
            return false;
        }
        return json_decode( $content );
    }

    public function tree_url(){
        return 'https://api.github.com/repos/' . $this->user . '/' . $this->repo . '/git/trees/' . $this->branch . '?recursive=1';
    }

    public function raw_url( $file_path ){
        return 'https://raw.githubusercontent.com/' . $this->user . '/' . $this->repo . '/' . $this->branch . '/' . $file_path;
    }

    public function github_url( $file_path ){
        return 'https://github.com/' . $this->user . '/' . $this->repo . '/blob/' . $this->branch . '/' . $file_path;
    }

    public function add_to_structure( $structure, $path_split, $item ){
        
        if( count( $path_split ) == 1 ){

            $full_file_name = $path_split[0];
            $extension = '';

            // Remove the file extension
            $file_slug = explode( '.', $full_file_name );
            if( count( $file_slug ) == 2 ){
                $extension = array_pop( $file_slug );
                $file_slug = implode( '', $file_slug );
            }else{
                $file_slug = $file_slug[0];
            }

            $structure[ $file_slug ] = array(
                'type' => 'file',
                'raw_url' => $this->raw_url( $item->path ),
                'github_url' => $this->github_url( $item->path ),
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

        GIW_Utils::log( 'Building repo structure...' );

        $tree_url = $this->tree_url();
        $data = $this->get_json( $tree_url );

        if( !$data ){
            GIW_Utils::log( 'Failed to fetch the repository tree! ['. $tree_url .']' );
            return false;
        }

        if( !property_exists( $data, 'tree' ) ){
            GIW_Utils::log( 'Repository not found on Github! ['. $tree_url .']' );
            return false;
        }

        foreach( $data->tree as $item ){
            if( $item->type == 'tree' ){
                continue;
            }

            $path = $item->path;
            $path_split = explode( '/', $path );
            $this->structure = $this->add_to_structure( $this->structure, $path_split, $item );
        }

    }

    public function get_item_content( $item_props ){

        $content = $this->get( $item_props[ 'raw_url' ] );

        if( !$content ){
            return false;
        }

        return $content;

    }



}

?>