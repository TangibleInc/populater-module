<?php

declare(strict_types=1);

namespace Tangible\Populater\Tests\Unit\LMS;

use Tangible\Populater\LMS\LifterLMS\LifterLmsEnrollmentSetup;
use Tangible\Populater\Tests\Support\InMemoryPostMeta;
use Brain\Monkey\Functions;

class LifterLmsEnrollmentSetupTest extends \WPTestCase
{
    private InMemoryPostMeta $meta;

    protected function setUp(): void
    {
        parent::setUp();
        LifterLmsEnrollmentSetup::resetCheckoutState();
        $this->meta = (new InMemoryPostMeta())->install();
    }

    public function test_ensure_checkout_page_creates_page_when_option_missing(): void
    {
        Functions\when('get_option')->alias(function (string $key, $default = false) {
            return $key === 'lifterlms_checkout_page_id' ? 0 : $default;
        });
        Functions\expect('wp_insert_post')
            ->once()
            ->with(\Mockery::on(function (array $args): bool {
                $this->assertSame('page', $args['post_type'] ?? '');
                $this->assertSame('purchase', $args['post_name'] ?? '');
                $this->assertStringContainsString('[lifterlms_checkout]', (string) ($args['post_content'] ?? ''));

                return true;
            }))
            ->andReturn(99);
        Functions\expect('update_option')
            ->once()
            ->with('lifterlms_checkout_page_id', 99)
            ->andReturn(true);
        Functions\when('is_wp_error')->justReturn(false);

        $this->assertSame(99, LifterLmsEnrollmentSetup::ensureCheckoutPage());
    }

    public function test_ensure_checkout_page_reuses_state_within_seed_run(): void
    {
        Functions\when('get_option')->alias(function (string $key, $default = false) {
            return $key === 'lifterlms_checkout_page_id' ? 99 : $default;
        });
        Functions\when('get_post_status')->justReturn('publish');
        Functions\expect('wp_insert_post')->never();

        $this->assertSame(99, LifterLmsEnrollmentSetup::ensureCheckoutPage());
        $this->assertSame(99, LifterLmsEnrollmentSetup::ensureCheckoutPage());
    }

    public function test_create_free_access_plan_creates_plan_with_meta(): void
    {
        Functions\when('taxonomy_exists')->justReturn(true);
        Functions\when('wp_set_object_terms')->justReturn([]);
        Functions\expect('wp_insert_post')
            ->once()
            ->with(\Mockery::on(fn(array $args): bool => ($args['post_type'] ?? '') === 'llms_access_plan'))
            ->andReturn(200);
        Functions\when('is_wp_error')->justReturn(false);

        $planId = LifterLmsEnrollmentSetup::createFreeAccessPlan(5, 2);

        $this->assertSame(200, $planId);
        $this->assertSame(5, $this->meta->getValue(200, '_llms_product_id'));
        $this->assertSame('yes', $this->meta->getValue(200, '_llms_is_free'));
        $this->assertSame(0, $this->meta->getValue(200, '_llms_price'));
    }

    public function test_seed_course_enrollment_creates_checkout_and_plan(): void
    {
        Functions\when('get_option')->justReturn(0);
        Functions\when('taxonomy_exists')->justReturn(true);
        Functions\when('wp_set_object_terms')->justReturn([]);
        Functions\expect('wp_insert_post')
            ->twice()
            ->andReturnUsing(static function (array $args): int {
                return ($args['post_type'] ?? '') === 'page' ? 99 : 200;
            });
        Functions\expect('update_option')->once();
        Functions\when('is_wp_error')->justReturn(false);

        $this->assertSame(200, LifterLmsEnrollmentSetup::seedCourseEnrollment(5, 1));
    }
}
