<?php

if( ! defined( 'ABSPATH' ) ) exit;

class GIW_Publisher{

    public $repository;

    public $branch;

    public $folder;

    public $post_type;

    public $post_author;

    public $content_template;

    public $uploaded_images;

    public $parsedown;

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

        $this->uploaded_images = GIW_Utils::get_uploaded_images();

        $this->parsedown = new GIW_Parsedown();
        $this->parsedown->uploaded_images = $this->uploaded_images;

        $this->allowed_file_types = Git_It_Write::allowed_file_types();

    }

    public function get_posts_by_parent( $parent ){

        $result = array();
        $posts = get_posts(array(
            'post_type' => $this->post_type,
            'posts_per_page' => -1,
            'post_status' => 'any',
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
        if( $post_id && $item_props ){

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
            $page_template = empty( $front_matter[ 'page_template' ] ) ? '' : $front_matter[ 'page_template' ];
            $comment_status = empty( $front_matter[ 'comment_status' ] ) ? '' : $front_matter[ 'comment_status' ];
            $stick_post = empty( $front_matter[ 'stick_post' ] ) ? '' : $front_matter[ 'stick_post' ];
            $skip_file = empty( $front_matter[ 'skip_file' ] ) ? '' : $front_matter[ 'skip_file' ];
            $taxonomy = $front_matter[ 'taxonomy' ];
            $custom_fields = $front_matter[ 'custom_fields' ];

            $post_date = '';
            if( !empty( $front_matter[ 'post_date' ] ) ){
                $post_date = GIW_Utils::process_date( $front_matter[ 'post_date' ] );
            }

            if( !empty( $front_matter[ 'featured_image' ] ) && !array_key_exists( '_thumbnail_id', $custom_fields ) ){
                $ft_image_path = trim( $front_matter[ 'featured_image' ] );
                $ft_image_path = ltrim( $ft_image_path, '/' );
                GIW_Utils::log( 'Featured image for the post [' . $ft_image_path . ']' );
                if( array_key_exists( $ft_image_path, $this->uploaded_images ) ){
                    $custom_fields[ '_thumbnail_id' ] = $this->uploaded_images[ $ft_image_path ][ 'id' ];
                }
            }

            $sha = $item_props[ 'sha' ];
            $github_url = $item_props[ 'github_url' ];

        }else{

            $post_title = $item_slug;
            $post_status = 'publish';
            $post_excerpt = '';
            $post_date = '';
            $menu_order = 0;
            $page_template = '';
            $comment_status = '';
            $stick_post = '';
            $skip_file = '';
            $taxonomy = array();
            $custom_fields = array();

            $content = '';
            $sha = '';
            $github_url = '';
        }

        if( $skip_file == 'yes' ){
            GIW_Utils::log( 'Skipping file [' . $item_props[ 'github_url' ] . '], skip_file option is set' );
            return false;
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
            'post_date' => $post_date,
            'page_template' => $page_template,
            'comment_status' => $comment_status,
            'menu_order' => $menu_order,
            'meta_input' => $meta_input
        );

        $new_post_id = wp_insert_post( $post_details );

        if( is_wp_error( $new_post_id ) || empty( $new_post_id ) ){
            GIW_Utils::log( 'Failed to publish post - ' . $new_post_id );
            $this->stats[ 'posts' ][ 'failed' ]++;
            return false;
        }else{
            GIW_Utils::log( '---------- Published post: ' . $new_post_id . ' ----------' );

            // Set the post taxonomy
            if( !empty( $taxonomy ) ){
                foreach( $taxonomy as $tax_name => $terms ){
                    GIW_Utils::log( 'Setting taxonomy [' . $tax_name . '] to post.' );
                    if( !taxonomy_exists( $tax_name ) ){
                        GIW_Utils::log( 'Skipping taxonomy [' . $tax_name . '] - does not exist.' );
                        continue;
                    }

                    $set_tax = wp_set_object_terms( $new_post_id, $terms, $tax_name );
                    if( is_wp_error( $set_tax ) ){
                        GIW_Utils::log( 'Failed to set taxonomy [' . $set_tax->get_error_message() . ']' );
                    }
                }
            }

            if( $stick_post == 'yes' ){
                GIW_Utils::log( 'Marking post [' . $new_post_id . '] as sticky' );
                stick_post( $new_post_id );
            }

            if( $stick_post == 'no' ){
                GIW_Utils::log( 'Removing post [' . $new_post_id . '] as sticky' );
                unstick_post( $new_post_id );
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

                $item_slug_clean = sanitize_title( $item_slug );
                $post_id = array_key_exists( $item_slug_clean, $existing_posts ) ? $existing_posts[ $item_slug_clean ][ 'id' ] : 0;

                $this->create_post( $post_id, $item_slug, $item_props, $parent );

            }

            if( $item_props[ 'type' ] == 'directory' ){

                $directory_post = false;
                $item_slug_clean = sanitize_title( $item_slug );

                if( array_key_exists( $item_slug_clean, $existing_posts ) ){
                    $directory_post = $existing_posts[ $item_slug_clean ][ 'id' ];

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

        $uploaded_images = GIW_Utils::get_uploaded_images();

        if( !isset( $this->repository->structure[ '_images' ] ) || $this->repository->structure[ '_images' ][ 'type' ] != 'directory' ){
            GIW_Utils::log( 'No images directory in repository. Exiting' );
            return array();
        }

        $images_dir = $this->repository->structure[ '_images' ];
        $images = $images_dir[ 'items' ];

        $this->upload_images_recursive( $images, $uploaded_images );

        // Update the uploaded images cache
        $this->uploaded_images = $uploaded_images;
        $this->parsedown->uploaded_images = $uploaded_images;

        return $uploaded_images;

    }

    public function upload_images_recursive( $images, &$uploaded_images ){

        foreach( $images as $image_slug => $image_props ){

            if( $image_props[ 'type' ] == 'directory' ){
                $sub_images_dir = $image_props[ 'items' ];
                GIW_Utils::log( $image_slug . ' is an images folder' );
                $this->upload_images_recursive( $sub_images_dir, $uploaded_images );
                continue;
            }

            $image_path = $image_props[ 'rel_url' ];

            GIW_Utils::log( 'Starting image ' . $image_path );
            if( array_key_exists( $image_path, $uploaded_images ) && !is_null( get_post( $uploaded_images[ $image_path ][ 'id' ] ) ) ){
                GIW_Utils::log( $image_path . ' is already uploaded' );
                continue;
            }

            GIW_Utils::log( 'Uploading image ' . $image_path );

            // So we use our patched version
            $uploaded_image_id = $this->upload_image( $image_props, 0, null, 'id' );

            if( is_wp_error( $uploaded_image_id ) ){
                GIW_Utils::log( 'Failed to upload image. Error [' . $uploaded_image_id->get_error_message() . ']' );
                continue;
            }

            $uploaded_image_url = wp_get_attachment_url( $uploaded_image_id );

            // Check if image is uploaded correctly and 
            if( !empty( $uploaded_image_url ) ){

                GIW_Utils::log( 'Image is uploaded [' . $uploaded_image_url . ']. ID: ' . $uploaded_image_id );

                $uploaded_images[ $image_path ] = array(
                    'url' => $uploaded_image_url,
                    'id' => $uploaded_image_id
                );

                if( !update_option( 'giw_uploaded_images', $uploaded_images ) ){
                    GIW_Utils::log( 'Failed to update uploaded images cache' );
                }

                $this->stats[ 'images' ][ 'uploaded' ][ $uploaded_image_id ] = $uploaded_image_url;

            }else{
                GIW_Utils::log( 'Failed to the uploaded attachment image URL' );
            }

        }
        
    }

    /**
     * Uploads image from a URL. A modified version of `media_sideload_image` function 
     * to honor authentication while fetching image data with GET from private repositories
     */
    public function upload_image( $image_props, $post_id = 0, $desc = null, $return_type = 'html' ) {

        $file = $image_props['raw_url'];

        if ( ! empty( $file ) ) {

            $allowed_extensions = array( 'jpg', 'jpeg', 'jpe', 'png', 'gif', 'webp' );
            $allowed_extensions = apply_filters( 'image_sideload_extensions', $allowed_extensions, $file );
            $allowed_extensions = array_map( 'preg_quote', $allowed_extensions );

            // Set variables for storage, fix file filename for query strings.
            preg_match( '/[^\?]+\.(' . implode( '|', $allowed_extensions ) . ')\b/i', $file, $matches );

            if ( ! $matches ) {
                return new WP_Error( 'image_sideload_failed', __( 'Unsupported image format.' ) );
            }
    
            $file_array = array();
            $file_array['name'] = wp_basename( $matches[0] );

            $url_path = parse_url( $file, PHP_URL_PATH );
            $url_filename = '';
            if ( is_string( $url_path ) && '' !== $url_path ) {
                $url_filename = basename( $url_path );
            }

            $temp_file_path = wp_tempnam( $url_filename );
            if ( ! $temp_file_path ) {
                return new WP_Error( 'http_no_file', __( 'Could not create temporary file.' ) );
            }

            $contents = $this->repository->get_item_content($image_props);
            file_put_contents($temp_file_path, $contents);

            // Download file to temp location.
            $file_array['tmp_name'] = $temp_file_path;

            // If error storing temporarily, return the error.
            if ( is_wp_error( $file_array['tmp_name'] ) ) {
                return $file_array['tmp_name'];
            }
    
            // Loads the downloaded image file to the library. Temporary file is deleted here after upload.
            $id = media_handle_sideload( $file_array, $post_id, $desc );
    
            // If error storing permanently, unlink.
            if ( is_wp_error( $id ) ) {
                @unlink( $file_array['tmp_name'] );
                return $id;
            }
    
            // Store the original attachment source in meta.
            add_post_meta( $id, '_source_url', $file );
    
            // If attachment ID was requested, return it.
            if ( 'id' === $return_type ) {
                return $id;
            }
    
            $src = wp_get_attachment_url( $id );
        }
    
        // Finally, check to make sure the file has been saved, then return the HTML.
        if ( ! empty( $src ) ) {
            if ( 'src' === $return_type ) {
                return $src;
            }
    
            $alt = isset( $desc ) ? esc_attr( $desc ) : '';
            $html = "<img src='$src' alt='$alt' />";
    
            return $html;
        } else {
            return new WP_Error( 'image_sideload_failed' );
        }
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