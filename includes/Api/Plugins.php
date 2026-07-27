<?php

namespace FlyWP\Api;

class Plugins {

    /**
     * API constructor.
     */
    public function __construct() {
        flywp()->router->get( 'plugins', [ $this, 'respond' ] );
    }

    /**
     * Handle request.
     *
     * @return void
     */
    public function respond( $args ) {
        $valid_statuses = [ 'all', 'active', 'inactive', 'upgrade' ];
        $status         = isset( $args['status'] ) && in_array( $args['status'], $valid_statuses, true ) ? $args['status'] : 'all';

        if ( ! function_exists( 'get_plugins' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        if ( ! function_exists( 'get_plugin_updates' ) ) {
            require_once ABSPATH . 'wp-admin/includes/update.php';
        }

        if ( isset( $args['force'] ) && $args['force'] === 'true' ) {
            wp_update_plugins();
        }

        $response     = [];
        $plugins      = get_plugins();
        $updates      = get_plugin_updates();
        $auto_updates = get_site_option( 'auto_update_plugins', [] );

        foreach ( $plugins as $file => $details ) {
            $plugin_status = $this->get_status( $file );
            $update        = $this->get_update( $file, $updates );

            if ( 'all' !== $status && $status !== $plugin_status && ( 'upgrade' !== $status || ! $update ) ) {
                continue;
            }

            $response[] = [
                'name'             => $details['Name'],
                'version'          => $details['Version'],
                'url'              => $details['PluginURI'],
                'update_available' => $update ? true : false,
                'new_version'      => $update ? $update['new_version'] : null,
                'author'           => $details['Author'],
                'file'             => $file,
                'status'           => $plugin_status,
                'textdomain'       => $details['TextDomain'],
                'description'      => $details['Description'],
                'php'              => $details['RequiresPHP'],
                'auto_update'      => in_array( $file, $auto_updates, true ),
            ];
        }

        wp_send_json( $response );
    }

    private function get_status( $file ) {
        return is_plugin_active( $file ) ? 'active' : 'inactive';
    }

    private function get_update( $plugin_file, $updates ) {
        if ( isset( $updates[ $plugin_file ] ) && isset( $updates[ $plugin_file ]->update ) ) {
            return [
                'new_version' => $updates[ $plugin_file ]->update->new_version,
                'package'     => $updates[ $plugin_file ]->update->package,
            ];
        }

        return false;
    }
}
