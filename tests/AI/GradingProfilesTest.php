<?php

namespace Tests\AI;

use App\Infrastructure\AI\GradingProfiles;
use DomainException;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\ConfiguresGrading;
use Tests\TestCase;

class GradingProfilesTest extends TestCase
{
    use ConfiguresGrading;

    public function test_grading_strictness_defaults_to_balanced_and_rejects_unknown_values(): void
    {
        $profiles = app(GradingProfiles::class);
        $this->assertSame('balanced', $profiles->options([])['grading_strictness']);
        $this->assertSame('strict', $profiles->options(['grading_strictness' => 'strict'])['grading_strictness']);

        $this->expectException(ValidationException::class);
        $profiles->options(['grading_strictness' => 'unknown']);
    }

    public function test_profile_requires_explicit_prices_capabilities_quota_and_cost_ceiling(): void
    {
        $this->configureGrading();
        $original = config('ai_grading.profiles.balanced');
        foreach (['enabled' => false, 'structured_output' => false, 'input_price' => null, 'output_price' => null,
            'supported_efforts' => [], 'model' => '', 'max_output_tokens' => 100] as $field => $value) {
            config(['ai_grading.profiles.balanced' => $original]);
            foreach ([0, 1] as $index) config(["ai_grading.profiles.balanced.destinations.$index.$field" => $value]);
            try {
                app(GradingProfiles::class)->destinations('balanced', 'medium', 1000, 1);
                $this->fail('Invalid field accepted: '.$field);
            } catch (DomainException) {
                $this->addToAssertionCount(1);
            }
        }
        config(['ai_grading.profiles.balanced' => $original, 'ai_grading.profiles.balanced.cost_ceiling_usd' => 0.00001]);
        $this->expectException(DomainException::class);
        app(GradingProfiles::class)->destinations('balanced', 'medium', 1000, 1);
    }

    public function test_oversized_input_cannot_bypass_token_quota(): void
    {
        $this->configureGrading();
        $this->expectException(DomainException::class);
        app(GradingProfiles::class)->destinations('balanced', 'medium', 1000000, 1);
    }

    public function test_preflight_checks_configuration_without_any_http_call(): void
    {
        $this->configureGrading();
        \Illuminate\Support\Facades\Http::preventStrayRequests();
        $this->artisan('ai:preflight')->assertSuccessful();
        \Illuminate\Support\Facades\Http::assertNothingSent();
    }
}
