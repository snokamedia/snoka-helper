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
}
