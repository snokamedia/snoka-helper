<?php

namespace FlyWP;

use WP_Error;

class Fastcgi_Cache {

    const CACHE_PATH = '/var/run/nginx-cache';

    const SETTINGS_KEY = 'flywp_fastcgi_cache';

    public function __construct() {
        add_action( 'save_post', [ $this, 'on_save_post' ], 10, 2 );
        add_action( 'delete_post', [ $this, 'wp_trash_post' ], 10, 2 );
    }

    public function purge_cache_url() {
        return wp_nonce_url(
            add_query_arg(
                [ 'flywp-action' => 'purge-fastcgi-cache' ],
                admin_url( 'index.php?page=flywp' )
            ),
            'fly-fastcgi-purge-cache'
        );
    }

    public function enabled() {
        return $this->settings() && $this->settings()['enabled'];
    }

    public function settings() {
        $default = [
            'enabled'         => true,
            'home_created'    => true,
            'home_deleted'    => true,
        ];

        return get_option( self::SETTINGS_KEY, $default );
    }

    public function get_setting( $key ) {
        return isset( $this->settings()[ $key ] ) ? $this->settings()[ $key ] : false;
    }

    public function clear_cache() {
        global $wp_filesystem;

        if ( ! is_object( $wp_filesystem ) ) {
            require_once ABSPATH . '/wp-admin/includes/file.php';
            WP_Filesystem();
        }

        if ( ! $wp_filesystem->exists( self::CACHE_PATH ) ) {
            return new WP_Error( 'cache_not_exists', __( 'Cache directory does not exist.', 'flywp' ) );
        }

        if ( ! $wp_filesystem->is_dir( self::CACHE_PATH ) ) {
            return new WP_Error( 'cache_not_dir', __( 'Cache directory is not a directory.', 'flywp' ) );
        }

        if ( ! $wp_filesystem->is_writable( self::CACHE_PATH ) ) {
            return new WP_Error( 'cache_not_writable', __( 'Cache directory is not writable.', 'flywp' ) );
        }

        $wp_filesystem->rmdir( self::CACHE_PATH, true );
        $wp_filesystem->mkdir( self::CACHE_PATH );

        return true;
    }

    public function purge_cache_by_url( $url ) {
        $parsed = wp_parse_url( $url );
        $path   = isset( $parsed['path'] ) ? $parsed['path'] : '';

        if ( substr( $path, -1 ) !== '/' ) {
            $path .= '/';
        }

        $hash = md5( 'httpGET' . $parsed['host'] . $path );

        $cache_path = self::CACHE_PATH . '/' . substr( $hash, -1 ) . '/' . substr( $hash, -3, 2 ) . '/' . $hash;

        if ( file_exists( $cache_path ) ) {
            unlink( $cache_path );
        }
    }

    public function purge_home_cache() {
        $this->purge_cache_by_url( home_url() );
    }

    public function on_save_post( $post_id, $post ) {
        if ( $post->post_status !== 'publish' ) {
            return;
        }

        if ( $this->get_setting( 'home_created' ) ) {
            $this->purge_home_cache();
        }

        $this->purge_cache_by_url( get_permalink( $post ) );
    }

    public function wp_trash_post( $post_id ) {
        $post = get_post( $post_id );

        if ( $this->get_setting( 'single_modified' ) ) {
            $this->purge_cache_by_url( get_permalink( $post ) );
        }

        if ( $this->get_setting( 'home_created' ) && $post->post_status === 'publish' ) {
            $this->purge_home_cache();
        }
    }
}
