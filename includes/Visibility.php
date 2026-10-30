<?php

namespace FlyWP;

/**
 * Invisibility gate for the white-labelled plugin.
 *
 * Only the user named by the SNOKA_ADMIN_USER constant (set in wp-config.php)
 * may see this plugin: plugins list, dashboard page, admin bar, notices,
 * Site Health and update emails. Everyone else — including other
 * administrators — sees nothing. When the constant is empty or undefined,
 * nobody sees it (fail closed).
 *
 * Display-only: the control-plane sync (REST routes, bearer auth, cron data)
 * never consults this class.
 */
class Visibility {

    /**
     * Whether the current user may see the plugin.
     *
     * @return bool
     */
    public static function can_see() {
        if ( ! defined( 'SNOKA_ADMIN_USER' ) || (string) SNOKA_ADMIN_USER === '' ) {
            return false;
        }

        if ( ! function_exists( 'wp_get_current_user' ) ) {
            return false;
        }

        $user = wp_get_current_user();

        return isset( $user->user_login ) && $user->user_login === (string) SNOKA_ADMIN_USER;
    }
}
