<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\CourseFeeSettings;
use App\Services\SettingsService;
use App\Services\StripeService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class CourseBillingDayTest extends TestCase
{
    use RefreshDatabase;

    protected Course $course;

    protected CourseFeeSettings $feeSettings;

    protected function setUp(): void
    {
        parent::setUp();

        $this->course = Course::factory()->create();
        $this->feeSettings = CourseFeeSettings::factory()->create([
            'course_id' => $this->course->id,
            'billing_day' => 15,
            'billing_cycle' => 'monthly',
        ]);
    }

    public function test_billing_day_field_is_fillable(): void
    {
        $feeSettings = CourseFeeSettings::create([
            'course_id' => $this->course->id,
            'fee_amount' => 100.00,
            'billing_cycle' => 'monthly',
            'billing_day' => 25,
            'is_recurring' => true,
        ]);

        expect($feeSettings->billing_day)->toBe(25);
    }

    public function test_has_billing_day_method(): void
    {
        // Test with valid billing day
        $this->feeSettings->billing_day = 15;
        expect($this->feeSettings->hasBillingDay())->toBeTrue();

        // Test with null billing day
        $this->feeSettings->billing_day = null;
        expect($this->feeSettings->hasBillingDay())->toBeFalse();

        // Test with invalid billing day (too low)
        $this->feeSettings->billing_day = 0;
        expect($this->feeSettings->hasBillingDay())->toBeFalse();

        // Test with invalid billing day (too high)
        $this->feeSettings->billing_day = 32;
        expect($this->feeSettings->hasBillingDay())->toBeFalse();
    }

    public function test_billing_day_label_generation(): void
    {
        // Test various billing days
        $testCases = [
            [1, '1st of each month'],
            [2, '2nd of each month'],
            [3, '3rd of each month'],
            [4, '4th of each month'],
            [11, '11th of each month'],
            [12, '12th of each month'],
            [13, '13th of each month'],
            [21, '21st of each month'],
            [22, '22nd of each month'],
            [23, '23rd of each month'],
            [31, '31st of each month'],
        ];

        foreach ($testCases as [$day, $expectedLabel]) {
            $this->feeSettings->billing_day = $day;
            expect($this->feeSettings->getBillingDayLabel())->toBe($expectedLabel);
        }

        // Test with null billing day
        $this->feeSettings->billing_day = null;
        expect($this->feeSettings->getBillingDayLabel())->toBe('Default (billing cycle start)');
    }

    public function test_validated_billing_day(): void
    {
        // Test with valid billing day
        $this->feeSettings->billing_day = 15;
        expect($this->feeSettings->getValidatedBillingDay())->toBe(15);

        // Test with billing day too high
        $this->feeSettings->billing_day = 35;
        expect($this->feeSettings->getValidatedBillingDay())->toBe(31);

        // Test with billing day too low
        $this->feeSettings->billing_day = -5;
        expect($this->feeSettings->getValidatedBillingDay())->toBe(1);

        // Test with null billing day
        $this->feeSettings->billing_day = null;
        expect($this->feeSettings->getValidatedBillingDay())->toBeNull();
    }

    public function test_billing_cycle_anchor_calculation_before_billing_day(): void
    {
        // Mock current date to be before billing day (5th of month, billing day is 15th)
        Carbon::setTestNow('2025-09-05 10:00:00');

        $stripeService = $this->createMockedStripeService();
        $reflection = new \ReflectionClass($stripeService);
        $method = $reflection->getMethod('calculateBillingCycleAnchor');
        $method->setAccessible(true);

        $anchor = $method->invokeArgs($stripeService, [$this->feeSettings]);
        $anchorDate = Carbon::createFromTimestamp($anchor, config('app.timezone'));

        // Should be 15th of current month
        expect($anchorDate->day)->toBe(15);
        expect($anchorDate->month)->toBe(9);
        expect($anchorDate->year)->toBe(2025);

        Carbon::setTestNow(); // Reset
    }

    public function test_billing_cycle_anchor_calculation_after_billing_day(): void
    {
        // Mock current date to be after billing day (20th of month, billing day is 15th)
        Carbon::setTestNow('2025-09-20 10:00:00');

        $stripeService = $this->createMockedStripeService();
        $reflection = new \ReflectionClass($stripeService);
        $method = $reflection->getMethod('calculateBillingCycleAnchor');
        $method->setAccessible(true);

        $anchor = $method->invokeArgs($stripeService, [$this->feeSettings]);
        $anchorDate = Carbon::createFromTimestamp($anchor, config('app.timezone'));

        // Should be 15th of next month
        expect($anchorDate->day)->toBe(15);
        expect($anchorDate->month)->toBe(10);
        expect($anchorDate->year)->toBe(2025);

        Carbon::setTestNow(); // Reset
    }

    public function test_billing_cycle_anchor_calculation_on_billing_day(): void
    {
        // Mock current date to be on billing day (15th of month, billing day is 15th)
        Carbon::setTestNow('2025-09-15 10:00:00');

        $stripeService = $this->createMockedStripeService();
        $reflection = new \ReflectionClass($stripeService);
        $method = $reflection->getMethod('calculateBillingCycleAnchor');
        $method->setAccessible(true);

        $anchor = $method->invokeArgs($stripeService, [$this->feeSettings]);
        $anchorDate = Carbon::createFromTimestamp($anchor, config('app.timezone'));

        // Should be 15th of next month since we're already on the billing day
        expect($anchorDate->day)->toBe(15);
        expect($anchorDate->month)->toBe(10);
        expect($anchorDate->year)->toBe(2025);

        Carbon::setTestNow(); // Reset
    }

    public function test_billing_cycle_anchor_handles_month_edge_cases(): void
    {
        // Test with billing day 31 in February
        Carbon::setTestNow('2025-02-15 10:00:00');

        $this->feeSettings->billing_day = 31;

        $stripeService = $this->createMockedStripeService();
        $reflection = new \ReflectionClass($stripeService);
        $method = $reflection->getMethod('calculateBillingCycleAnchor');
        $method->setAccessible(true);

        $anchor = $method->invokeArgs($stripeService, [$this->feeSettings]);
        $anchorDate = Carbon::createFromTimestamp($anchor, config('app.timezone'));

        // Should be last day of February (28th in non-leap year)
        expect($anchorDate->day)->toBe(28);
        expect($anchorDate->month)->toBe(2);

        Carbon::setTestNow(); // Reset
    }

    public function test_billing_cycle_anchor_returns_null_for_no_billing_day(): void
    {
        $this->feeSettings->billing_day = null;

        $stripeService = $this->createMockedStripeService();
        $reflection = new \ReflectionClass($stripeService);
        $method = $reflection->getMethod('calculateBillingCycleAnchor');
        $method->setAccessible(true);

        $anchor = $method->invokeArgs($stripeService, [$this->feeSettings]);

        expect($anchor)->toBeNull();
    }

    private function createMockedStripeService(): StripeService
    {
        // Mock the SettingsService to return fake Stripe keys
        $settingsServiceMock = Mockery::mock(SettingsService::class);
        $settingsServiceMock->shouldReceive('get')
            ->with('stripe_secret_key')
            ->andReturn('sk_test_fake_key_for_testing');
        $settingsServiceMock->shouldReceive('get')
            ->with('stripe_publishable_key')
            ->andReturn('pk_test_fake_key_for_testing');
        $settingsServiceMock->shouldReceive('get')
            ->withAnyArgs()
            ->andReturn(null);

        return new StripeService($settingsServiceMock);
    }
}
