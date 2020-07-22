<?php

class G2W{

    public $repo;

    public $user;

    public $post_type;

    public $parsedown;

    public $repo_structure = array();

    public $existing_posts = array();

    public $default_post_meta = array(
        'sha' => '',
        'github_url' => ''
    );

    public function __construct( $user, $repo, $post_type ){

        $this->user = $user;
        $this->repo = $repo;
        $this->post_type = $post_type;
        $this->parsedown = new G2W_Parsedown();

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
        return 'https://api.github.com/repos/' . $this->user . '/' . $this->repo . '/git/trees/master?recursive=1';
    }

    public function raw_url( $file_path ){
        return 'https://raw.githubusercontent.com/' . $this->user . '/' . $this->repo . '/master/' . $file_path;
    }

    public function github_url( $file_path ){
        return 'https://github.com/' . $this->user . '/' . $this->repo . '/blob/master/' . $file_path;
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

    public function add_to_structure( $structure, $path_split, $item ){
        
        if( count( $path_split ) == 1 ){

            $full_file_name = $path_split[0];

            $file_slug = explode( '.', $full_file_name );
            array_pop( $file_slug );
            $file_slug = implode( '', $file_slug );
            $is_markdown = substr( $full_file_name, -3 ) == '.md';

            if( !$is_markdown ){
                return $structure;
            }

            $structure[ $file_slug ] = array(
                'type' => 'file',
                'raw_url' => $this->raw_url( $item->path ),
                'github_url' => $this->github_url( $item->path ),
                'sha' => $item->sha,
                'markdown' => $is_markdown
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

            $structure[ $first_dir ][ 'items' ] = $this->add_to_structure( $structure[$first_dir]['items'], $path_split, $item );
            return $structure;
        }

    }

    public function build_structure(){

        $tree_url = $this->tree_url();
        $data = $this->get_json( $tree_url );

        if( !$data ){
            do_log( 'No data' );
            return false;
        }

        foreach( $data->tree as $item ){
            if( $item->type == 'tree' ){
                continue;
            }

            $path = $item->path;
            $path_split = explode( '/', $path );
            $this->repo_structure = $this->add_to_structure( $this->repo_structure, $path_split, $item );
        }

        print_r( $this->repo_structure );

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

    public function get_item_content( $item_props ){
        do_log('in item content');
        do_log( $item_props );
        $content = $this->get( $item_props[ 'raw_url' ] );
        if( !$content ){
            do_log( 'Failed to get item content' );
            return false;
        }

        return $this->parsedown->text( $content );

    }

    public function create_post( $post_id, $item_slug, $item_props, $parent ){

        do_log( '--------' );
        do_log( 'Creating post ' . $post_id );
        do_log( $item_slug );
        do_log( $item_props );
        do_log( $parent );
        
        // $newp = random_int( 2000, 3000 );
        // do_log( 'created post - '  . $newp );
        // do_log( '--------' );
        // return $newp;

        // If post exists, check if it has changed and proceed further
        if( $post_id ){

            $post_meta = $this->get_post_meta( $post_id );

            if( $post_meta[ 'sha' ] == $item_props[ 'sha' ] ){
                do_log( 'Post is unchanged' );
                return $post_id;
            }

        }
        
        // Check if item props exist, in case of dir posts
        if( $item_props ){
            $item_content = $this->get_item_content( $item_props );

            // Some error in getting the item content
            if( !$item_content ){
                do_log( 'Cannot retrieve post content, skipping this' );
                return false;
            }

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

        if( is_wp_error( $new_post_id ) ){
            do_log( 'Failed to insert post' );
            return false;
        }else{
            do_log('created post - ' . $new_post_id);
            do_log( '--------' );
            return $new_post_id;
        }

    }

    public function create_posts( $repo_structure, $parent ){

        $existing_posts = $this->get_posts_by_parent( $parent );

        foreach( $repo_structure as $item_slug => $item_props ){

            do_log($item_slug);

            if( $item_props['type'] == 'file' ){

                if( $item_slug == 'index' ){
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

}

?>