<?php

namespace FlyWP\Tests;

use FlyWP\Visibility;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/stubs/wordpress.php';

/**
 * Only the user named by SNOKA_ADMIN_USER (csadmin in tests) may see the plugin.
 */
class VisibilityTest extends TestCase {

    protected function setUp(): void {
        $GLOBALS['flywp_test_user_login'] = 'csadmin';
    }

    public function test_designated_user_can_see() {
        $this->assertTrue( Visibility::can_see() );
    }

    public function test_other_user_cannot_see() {
        $GLOBALS['flywp_test_user_login'] = 'clientadmin';

        $this->assertFalse( Visibility::can_see() );
    }

    public function test_logged_out_visitor_cannot_see() {
        $GLOBALS['flywp_test_user_login'] = '';

        $this->assertFalse( Visibility::can_see() );
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function test_wp_cli_sees_without_a_user() {
        define( 'WP_CLI', true );
        $GLOBALS['flywp_test_user_login'] = '';

        $this->assertTrue( Visibility::can_see() );
    }
}
