<?php

if( ! defined( 'ABSPATH' ) ) exit;

class GIW_Publish_Handler{

    public static $repo_obj_cache = array();

    public static function publish_by_id( $repo_id ){

        $all_repos = Git_It_Write::all_repositories();

        if( !isset( $all_repos[ $repo_id ] ) ){
            return false;
        }

        GIW_Utils::log( '********** Publishing posts by repository config ID **********' );
        GIW_Utils::log( 'Working with repository config ID ' . $repo_id );

        $repo_config = $all_repos[ $repo_id ];
        $username = $repo_config[ 'username' ];
        $repo_name = $repo_config[ 'repository' ];
        $repository = false;

        // Cache the repository class object
        if( array_key_exists( $username, self::$repo_obj_cache ) && array_key_exists( $repo_name, self::$repo_obj_cache[ $username ] ) ){
            $repository = self::$repo_obj_cache[ $username ][ $repo_name ];
        }else{
            GIW_Utils::log( 'Creating repository object' );
            $repository = new GIW_Repository( $username, $repo_name );
            self::$repo_obj_cache[ $username ][ $repo_name ] = $repository;
        }

        $publisher = new GIW_Publisher( $repository, $repo_config );
        $result = $publisher->publish();

        $all_repos[ $repo_id ][ 'last_publish' ] = time();
        update_option( 'giw_repositories', $all_repos );

        GIW_Utils::log( '********** END **********' );

        return $result;

    }

    public static function publish_by_repo_full_name( $full_name ){

        GIW_Utils::log( '********** Publishing posts by repository full name ' .  $full_name  . ' **********' );

        $name_split = explode( '/', $full_name );
        if( count( $name_split ) != 2 ){
            return false;
        }

        $all_results = array();
        $username = $name_split[0];
        $repo_name = $name_split[1];

        $all_repos = Git_It_Write::all_repositories();

        foreach( $all_repos as $id => $repo ){
            if( $id == 0 ) continue;

            if( $repo[ 'username' ] == $username && $repo[ 'repository' ] == $repo_name ){
                GIW_Utils::log( 'There is a repo configured for this' );
                $result = self::publish_by_id( $id );
                array_push( $all_results, $result );
            }
        }

        GIW_Utils::log( '********** END **********' );

        return $all_results;

    }

}

?>