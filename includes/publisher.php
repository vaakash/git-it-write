<?php

if( ! defined( 'ABSPATH' ) ) exit;

class GIW_Publisher{

    public $repository;

    public $branch;

    public $folder;

    public $post_type;

    public $post_author;

    public $content_template;

    public $stats = array(
        'posts' => array(
            'new' => array(),
            'updated' => array(),
            'failed' => 0
        ),
        'images' => array(
            'uploaded' => array(),
            'failed' => 0
        )
    );

    public $default_post_meta = array(
        'sha' => '',
        'github_url' => ''
    );

    public $allowed_file_types = array();

    public function __construct( GIW_Repository $repository, $repo_config ){

        $this->repository = $repository;
        $this->post_type = $repo_config[ 'post_type' ];
        $this->branch = empty( $repo_config[ 'branch' ] ) ? 'master' : $repo_config[ 'branch' ];
        $this->folder = $repo_config[ 'folder' ];
        $this->post_author = $repo_config[ 'post_author' ];
        $this->content_template = $repo_config[ 'content_template' ];

        $this->parsedown = new GIW_Parsedown();
        $this->parsedown->uploaded_images = get_option( 'giw_uploaded_images', array() );

        $this->allowed_file_types = Git_It_Write::allowed_file_types();

    }

    public function get_posts_by_parent( $parent ){

        $result = array();
        $posts = get_posts(array(
            'post_type' => $this->post_type,
            'posts_per_page' => -1,
            'post_status' => 'publish',
            'post_parent' => $parent
        ));

        
        foreach( $posts as $index => $post ){

            $result[ $post->post_name ] = array(
                'id' => $post->ID,
                'parent' => $post->post_parent
            );
        }

        return $result;

    }

    public function get_post_meta( $post_id ){

        $current_meta = get_post_meta( $post_id, '', true );
        $metadata = array();

        if( !is_array( $current_meta ) ){
            return $this->default_post_meta;
        }

        foreach( $this->default_post_meta as $key => $default_val ){
            $metadata[ $key ] = array_key_exists( $key, $current_meta ) ? $current_meta[$key][0] : $default_val;
        }

        return $metadata;

    }

    public function create_post( $post_id, $item_slug, $item_props, $parent ){

        GIW_Utils::log( sprintf( '---------- Checking post [%s] under parent [%s] ----------', $post_id, $parent ) );

        // If post exists, check if it has changed and proceed further
        if( $post_id ){

            $post_meta = $this->get_post_meta( $post_id );

            if( $post_meta[ 'sha' ] == $item_props[ 'sha' ] ){
                GIW_Utils::log( 'Post is unchanged' );
                if( !defined( 'GIW_PUBLISH_FORCE' ) ){
                    return $post_id;
                }else{
                    GIW_Utils::log( 'Forcefully updating post' );
                }
            }

        }
        
        // Check if item props exist, in case of dir posts
        if( $item_props ){
            $item_content = $this->repository->get_item_content( $item_props );

            // Some error in getting the item content
            if( !$item_content ){
                GIW_Utils::log( 'Cannot retrieve post content, skipping this' );
                $this->stats[ 'posts' ][ 'failed' ]++;
                return false;
            }

            $parsed_content = $this->parsedown->parse_content( $item_content );

            $front_matter = $parsed_content[ 'front_matter' ];
            $html = $this->parsedown->text( $parsed_content[ 'markdown' ] );
            $content = GIW_Utils::process_content_template( $this->content_template, $html );

            // Get post details
            $post_title = empty( $front_matter[ 'title' ] ) ? $item_slug : $front_matter[ 'title' ];
            $post_status = empty( $front_matter[ 'post_status' ] ) ? 'publish' : $front_matter[ 'post_status' ];
            $post_excerpt = empty( $front_matter[ 'post_excerpt' ] ) ? '' : $front_matter[ 'post_excerpt' ];
            $menu_order = empty( $front_matter[ 'menu_order' ] ) ? 0 : $front_matter[ 'menu_order' ];
            $taxonomy = $front_matter[ 'taxonomy' ];
            $custom_fields = $front_matter[ 'custom_fields' ];

            $sha = $item_props[ 'sha' ];
            $github_url = $item_props[ 'github_url' ];

        }else{

            $post_title = $item_slug;
            $post_status = 'publish';
            $post_excerpt = '';
            $menu_order = 0;
            $taxonomy = array();
            $custom_fields = array();

            $content = '';
            $sha = '';
            $github_url = '';
        }

        $meta_input = array_merge( $custom_fields, array(
            'sha' => $sha,
            'github_url' => $github_url
        ));

        $post_details = array(
            'ID' => $post_id,
            'post_title' => $post_title,
            'post_name' => $item_slug,
            'post_content' => $content,
            'post_type' => $this->post_type,
            'post_author' => $this->post_author,
            'post_status' => $post_status,
            'post_excerpt' => $post_excerpt,
            'post_parent' => $parent,
            'menu_order' => $menu_order,
            'meta_input' => $meta_input
        );

        $new_post_id = wp_insert_post( $post_details );

        if( is_wp_error( $new_post_id ) || empty( $new_post_id ) ){
            GIW_Utils::log( 'Failed to publish post - ' . $new_post_id->get_error_message() );
            $this->stats[ 'posts' ][ 'failed' ]++;
            return false;
        }else{
            GIW_Utils::log( '---------- Published post: ' . $new_post_id . ' ----------' );

            // Set the post taxonomy
            if( !empty( $taxonomy ) ){
                foreach( $taxonomy as $tax_name => $terms ){
                    GIW_Utils::log( 'Setting taxonomy to post - ' . $tax_name );
                    if( !taxonomy_exists( $tax_name ) ){
                        continue;
                    }
                    $set_tax = wp_set_object_terms( $new_post_id, $terms, $tax_name );
                    if( is_wp_error( $set_tax ) ){
                        GIW_Utils::log( 'Failed to set taxonomy - ' . $set_tax->get_error_message() );
                    }
                }
            }

            $stat_key = $new_post_id == $post_id ? 'updated' : 'new';
            $this->stats[ 'posts' ][ $stat_key ][ $new_post_id ] = get_post_permalink( $new_post_id );

            return $new_post_id;
        }

    }

    public function create_posts( $repo_structure, $parent ){

        $existing_posts = $this->get_posts_by_parent( $parent );

        foreach( $repo_structure as $item_slug => $item_props ){

            GIW_Utils::log( 'At repository item - ' . $item_slug);

            $first_character = substr( $item_slug, 0, 1 );
            if( in_array( $first_character, array( '_', '.' ) ) ){
                GIW_Utils::log( 'Items starting with _ . are skipped for publishing' );
                continue;
            }

            if( $item_props[ 'type' ] == 'file' ){

                if( $item_slug == 'index' ){
                    GIW_Utils::log( 'Skipping separate post for index' );
                    continue;
                }

                if( !in_array( $item_props[ 'file_type' ], $this->allowed_file_types ) ){
                    GIW_Utils::log( 'Skipping file as it is not an allowed file type' );
                    continue;
                }

                $post_id = array_key_exists( $item_slug, $existing_posts ) ? $existing_posts[ $item_slug ][ 'id' ] : 0;

                $this->create_post( $post_id, $item_slug, $item_props, $parent );

            }

            if( $item_props[ 'type' ] == 'directory' ){

                $directory_post = false;

                if( array_key_exists( $item_slug, $existing_posts ) ){
                    $directory_post = $existing_posts[ $item_slug ][ 'id' ];

                    $index_props = array_key_exists( 'index', $item_props[ 'items' ] ) ? $item_props[ 'items' ][ 'index' ] : false;
                    $this->create_post( $directory_post, $item_slug, $index_props, $parent );

                }else{
                    
                    // If index posts exists for the directory
                    if( array_key_exists( 'index', $item_props[ 'items' ] ) ){
                        $index_props = $item_props[ 'items' ][ 'index' ];
                        $directory_post = $this->create_post( 0, $item_slug, $index_props, $parent );
                    }else{
                        $directory_post = $this->create_post( 0, $item_slug, false, $parent );
                    }

                }

                $this->create_posts( $item_props[ 'items' ], $directory_post );

            }

        }

    }

    public function upload_images(){

        $uploaded_images = get_option( 'giw_uploaded_images', array() );

        if( !isset( $this->repository->structure[ '_images' ] ) || $this->repository->structure[ '_images' ][ 'type' ] != 'directory' ){
            GIW_Utils::log( 'No images directory in repository. Exiting' );
            return array();
        }

        $images_dir = $this->repository->structure[ '_images' ];
        $images = $images_dir[ 'items' ];

        foreach( $images as $image_slug => $image_props ){
            GIW_Utils::log( 'Starting image ' . $image_slug );
            if( array_key_exists( $image_slug, $uploaded_images ) ){
                GIW_Utils::log( $image_slug . ' is already uploaded' );
                continue;
            }

            GIW_Utils::log( 'Uploading image ' . $image_slug );
            GIW_Utils::log( $image_props );

            $uploaded_image_id = media_sideload_image( $image_props[ 'raw_url' ], 0, null, 'id' );
            $uploaded_image_url = wp_get_attachment_url( $uploaded_image_id );

            // Check if image is uploaded correctly and 
            if( !empty( $uploaded_image_url ) ){

                GIW_Utils::log( 'Image is uploaded ' . $uploaded_image_url . ' ' . $uploaded_image_id );

                $uploaded_images[ $image_slug ] = array(
                    'url' => $uploaded_image_url,
                    'id' => $uploaded_image_id
                );

                if( !update_option( 'giw_uploaded_images', $uploaded_images ) ){
                    GIW_Utils::log( 'Updated uploaded images cache' );
                }

                $this->stats[ 'images' ][ 'uploaded' ][ $uploaded_image_id ] = $uploaded_image_url;

            }else{
                GIW_Utils::log( 'Image upload failed for some reason' );
            }

        }

        // Update the parsedown uploaded images array
        $this->parsedown->uploaded_images = $uploaded_images;

        return $uploaded_images;

    }

    public function publish(){

        $repo_structure = $this->repository->structure;
        $folder = trim( $this->folder );

        if( $folder != '/' && !empty( $folder ) ){
            if( array_key_exists( $folder, $repo_structure ) ){
                $repo_structure = $repo_structure[ $folder ][ 'items' ];
            }else{
                return array(
                    'result' => 0,
                    'message' => sprintf( 'No folder %s exists in the repository', $folder ),
                    'stats' => $this->stats
                );
            }
        }

        GIW_Utils::log( $repo_structure );

        GIW_Utils::log( '++++++++++ Uploading images first ++++++++++' );
        $this->upload_images();
        GIW_Utils::log( '++++++++++ Done ++++++++++' );

        GIW_Utils::log( '++++++++++ Publishing posts ++++++++++' );
        GIW_Utils::log( 'Allowed file types - ' . implode( ', ', $this->allowed_file_types ) );
        $this->create_posts( $repo_structure, 0 );
        GIW_Utils::log( '++++++++++ Done ++++++++++' );

        $message = 'Successfully published posts';
        $result = 1;

        if( $this->stats[ 'posts' ][ 'failed' ] > 0 || $this->stats[ 'images' ][ 'failed' ] > 0 ){
            $result = 2;
            $message = 'One or more failures occurred while publishing';
        }

        if( count( $this->stats[ 'posts' ][ 'new' ] ) == 0 && count( $this->stats[ 'posts' ][ 'updated' ] ) == 0 ){
            $result = 3;
            $message = 'No new changed were made. All posts are up to date.';
        }

        $end_result = array(
            'result' => $result,
            'message' => $message,
            'stats' => $this->stats
        );

        GIW_Utils::log( $end_result );

        return $end_result;

    }

}

?>