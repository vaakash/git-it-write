<?php

class G2W_Publisher{

    public $repository;

    public $post_type;

    public $folder;

    public $existing_posts = array();

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

    public function __construct( $repository, $post_type, $folder ){

        $this->repository = $repository;
        $this->post_type = $post_type;
        $this->folder = $folder;
        
        $this->parsedown = new G2W_Parsedown();
        $this->parsedown->uploaded_images = get_option( 'g2w_uploaded_images', array() );
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

        G2W_Utils::log( sprintf( '---------- Checking post %s under parent %s ----------', $post_id, $parent ) );

        // If post exists, check if it has changed and proceed further
        if( $post_id ){

            $post_meta = $this->get_post_meta( $post_id );

            if( $post_meta[ 'sha' ] == $item_props[ 'sha' ] ){
                G2W_Utils::log( 'Post is unchanged. Checking next.' );
                return $post_id;
            }

        }
        
        // Check if item props exist, in case of dir posts
        if( $item_props ){
            $item_content = $this->repository->get_item_content( $item_props );

            // Some error in getting the item content
            if( !$item_content ){
                G2W_Utils::log( 'Cannot retrieve post content, skipping this' );
                $this->stats[ 'posts' ][ 'failed' ]++;
                return false;
            }

            $item_content = $this->parsedown->text( $item_content );
            $front_matter = $item_content[ 'front_matter' ];
            $post_title = empty( $front_matter[ 'title' ] ) ? $item_slug : $front_matter[ 'title' ];
            $content = $item_content[ 'html' ];
            $sha = $item_props[ 'sha' ];
            $github_url = $item_props[ 'github_url' ];

        }else{
            $post_title = $item_slug;
            $content = '';
            $sha = '';
            $github_url = '';
        }

        $post_details = array(
            'ID' => $post_id,
            'post_title' => $post_title,
            'post_name' => $item_slug,
            'post_content' => $content,
            'post_type' => $this->post_type,
            'post_status' => 'publish',
            'post_parent' => $parent,
            'meta_input' => array(
                'sha' => $sha,
                'github_url' => $github_url
            )
        );

        $new_post_id = wp_insert_post( $post_details );

        if( is_wp_error( $new_post_id ) || empty( $new_post_id ) ){
            G2W_Utils::log( 'Failed to publish post' );
            $this->stats[ 'posts' ][ 'failed' ]++;
            return false;
        }else{
            G2W_Utils::log( '---------- Published post: ' . $new_post_id . ' ----------' );

            $stat_key = $new_post_id == $post_id ? 'updated' : 'new';
            $this->stats[ 'posts' ][ $stat_key ][ $new_post_id ] = get_post_permalink( $new_post_id );

            return $new_post_id;
        }

    }

    public function create_posts( $repo_structure, $parent ){

        $existing_posts = $this->get_posts_by_parent( $parent );

        foreach( $repo_structure as $item_slug => $item_props ){

            G2W_Utils::log( 'At repository item - ' . $item_slug);

            if( $item_props['type'] == 'file' ){

                if( $item_slug == 'index' ){
                    G2W_Utils::log( 'Skipping separate post for index' );
                    continue;
                }

                $post_id = array_key_exists( $item_slug, $existing_posts ) ? $existing_posts[ $item_slug ][ 'id' ] : 0;

                $this->create_post( $post_id, $item_slug, $item_props, $parent );

            }

            if( $item_props[ 'type' ] == 'directory' ){

                if( $item_slug == '_images' ){
                    G2W_Utils::log( 'Skipping post for _images directory' );
                    continue;
                }

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

        $uploaded_images = get_option( 'g2w_uploaded_images', array() );

        if( !isset( $this->repository->structure[ '_images' ] ) || $this->repository->structure[ '_images' ][ 'type' ] != 'directory' ){
            G2W_Utils::log( 'No images directory in repository. Exiting' );
            return array();
        }

        $images_dir = $this->repository->structure[ '_images' ];
        $images = $images_dir[ 'items' ];

        foreach( $images as $image_slug => $image_props ){
            G2W_Utils::log( 'Starting image ' . $image_slug );
            if( array_key_exists( $image_slug, $uploaded_images ) ){
                G2W_Utils::log( $image_slug . ' is already uploaded' );
                continue;
            }

            G2W_Utils::log( 'Uploading image ' . $image_slug );
            G2W_Utils::log( $image_props );

            $uploaded_image_id = media_sideload_image( $image_props[ 'raw_url' ], 0, null, 'id' );
            $uploaded_image_url = wp_get_attachment_url( $uploaded_image_id );

            // Check if image is uploaded correctly and 
            if( !empty( $uploaded_image_url ) ){

                G2W_Utils::log( 'Image is uploaded ' . $uploaded_image_url . ' ' . $uploaded_image_id );

                $uploaded_images[ $image_slug ] = array(
                    'url' => $uploaded_image_url,
                    'id' => $uploaded_image_id
                );

                if( !update_option( 'g2w_uploaded_images', $uploaded_images ) ){
                    G2W_Utils::log( 'Updated uploaded images cache' );
                }

                $this->stats[ 'images' ][ 'uploaded' ][ $uploaded_image_id ] = $uploaded_image_url;

            }else{
                G2W_Utils::log( 'Image upload failed for some reason' );
            }

        }

        // Update the parsedown uploaded images array
        $this->parsedown->uploaded_images = $uploaded_images;

        return $uploaded_images;

    }

    public function publish(){

        $repo_structure = $this->repository->structure;

        if( $this->folder != '/' ){
            if( array_key_exists( $this->folder, $repo_structure ) ){
                $repo_structure = $repo_structure[ $this->folder ][ 'items' ];
            }else{
                return array(
                    'result' => 0,
                    'message' => sprintf( 'No folder %s exists in the repository', $this->folder ),
                    'stats' => $this->stats
                );
            }
        }

        G2W_Utils::log( $repo_structure );

        G2W_Utils::log( '++++++++++ Uploading images first ++++++++++' );
        $this->upload_images();
        G2W_Utils::log( '++++++++++ Done ++++++++++' );

        G2W_Utils::log( '++++++++++ Publishing posts ++++++++++' );
        $this->create_posts( $repo_structure, 0 );
        G2W_Utils::log( '++++++++++ Done ++++++++++' );

        $message = 'Successfully published posts';
        $result = 1;

        if( $this->stats[ 'posts' ][ 'failed' ] > 0 || $this->stats[ 'images' ][ 'failed' ] > 0 ){
            $result = 2;
            $message = 'One or more failures occurred while publishing';
        }

        $end_result = array(
            'result' => $result,
            'message' => $message,
            'stats' => $this->stats
        );

        G2W_Utils::log( $end_result );

        return $end_result;

    }

}

?>