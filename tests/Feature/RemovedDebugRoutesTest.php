<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class RemovedDebugRoutesTest extends TestCase
{
    public function test_removed_debug_routes_return_not_found(): void
    {
        $this->get('/debug-gd')->assertNotFound();
        $this->get('/s3-test')->assertNotFound();
        $this->post('/s3-test')->assertNotFound();
        $this->get('/s3-test/signed-url')->assertNotFound();
    }

    public function test_removed_ses_and_line_test_routes_return_not_found_and_send_nothing(): void
    {
        Mail::fake();
        Http::fake();

        $this->get('/test-ses-mail?to=someone@example.com')->assertNotFound();
        $this->get('/test-line')->assertNotFound();

        Mail::assertNothingSent();
        Mail::assertNothingQueued();
        Http::assertNothingSent();
    }
}
