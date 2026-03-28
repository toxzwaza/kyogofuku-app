<?php

namespace Tests\Feature\Admin;

use App\Models\ConstraintTemplate;
use App\Models\Customer;
use App\Models\CustomerConstraint;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CustomerConstraintAttachmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_constraint_with_attachment_redirects_to_customer_show(): void
    {
        Storage::fake('s3_private');

        $user = User::factory()->create();
        $shop = Shop::create([
            'name' => 'Test Shop',
            'address' => null,
            'phone' => null,
            'is_active' => true,
        ]);
        $user->shops()->attach($shop->id);

        $template = ConstraintTemplate::create([
            'name' => '規約A',
            'body' => '本文',
            'is_active' => true,
        ]);
        $template->shops()->attach($shop->id);

        $customer = Customer::create([
            'name' => '山田太郎',
            'kana' => null,
        ]);

        $file = UploadedFile::fake()->create('agreement.pdf', 50, 'application/pdf');

        $this->actingAs($user)
            ->post(route('admin.customers.constraints.store', $customer), [
                'constraint_template_id' => $template->id,
                'signed_at' => '2026-03-28',
                'check_values' => json_encode([]),
                'attachment' => $file,
            ])
            ->assertRedirect(route('admin.customers.show', $customer))
            ->assertSessionHas('success');

        $constraint = CustomerConstraint::where('customer_id', $customer->id)->first();
        $this->assertNotNull($constraint);
        $this->assertSame($template->id, $constraint->constraint_template_id);
        $this->assertNotNull($constraint->attachment_path);
        $this->assertSame('s3', $constraint->attachment_disk);
        $this->assertSame('agreement.pdf', $constraint->attachment_original_name);

        Storage::disk('s3_private')->assertExists($constraint->attachment_path);
    }
}
