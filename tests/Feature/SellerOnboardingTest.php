<?php

namespace Tests\Feature;

use App\Actions\ApproveSellerApplication;
use App\Actions\SuspendStore;
use App\Models\AuditLog;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class SellerOnboardingTest extends TestCase
{
    use RefreshDatabase;

    public function test_buyer_saves_resumes_and_submits_all_steps(): void
    {
        Storage::fake('chat_attachments');
        Storage::fake('public');
        $buyer = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($buyer)->post(route('seller-application.step', 1), ['seller_type' => 'individual'])->assertSessionHasNoErrors();
        $application = $buyer->sellerApplications()->firstOrFail();
        $this->assertSame('draft', $application->status);
        $this->actingAs($buyer)->get(route('seller-application.create', ['step' => 2]))->assertOk()->assertSee('Step 1');
        $this->actingAs($buyer)->post(route('seller-application.step', 2), ['seller_name' => 'Ava Seller', 'phone' => '+639171234567', 'date_of_birth' => '1990-01-01'])->assertSessionHasNoErrors();
        $this->actingAs($buyer)->post(route('seller-application.step', 3), ['store_name' => 'Ava Goods', 'store_slug' => 'Ava Goods', 'store_description' => 'Useful goods', 'store_category' => 'Home', 'support_contact' => 'help@example.test', 'logo' => UploadedFile::fake()->image('logo.jpg')])->assertSessionHasNoErrors();
        $this->actingAs($buyer)->post(route('seller-application.step', 4), ['region' => 'NCR', 'province' => 'Metro Manila', 'city' => 'Manila', 'barangay' => 'Ermita', 'address_line' => '10 Main St', 'postal_code' => '1000', 'pickup_address' => '10 Main St', 'return_address' => '10 Main St', 'processing_days' => 2, 'fulfillment_methods' => 'Courier', 'delivery_areas' => 'Philippines'])->assertSessionHasNoErrors();
        $this->actingAs($buyer)->post(route('seller-application.step', 5), ['id_type' => 'passport', 'id_number' => 'P123456', 'id_front' => UploadedFile::fake()->image('front.jpg'), 'selfie' => UploadedFile::fake()->image('selfie.jpg')])->assertSessionHasNoErrors();
        $this->actingAs($buyer)->post(route('seller-application.step', 6), ['method' => 'bank', 'provider' => 'Test Bank', 'holder_name' => 'Ava Seller', 'account_number' => '123456789012'])->assertSessionHasNoErrors();
        $application->refresh();
        Storage::disk('chat_attachments')->assertExists($application->documents()->where('type', 'id_front')->firstOrFail()->path);
        $this->assertSame('ava-goods', $application->store_slug);
        $this->assertSame([1, 2, 3, 4, 5, 6], $application->completed_steps);
        $this->assertNotSame('123456789012', $application->payoutProfile->getRawOriginal('account_number'));
        $this->actingAs($buyer)->post(route('seller-application.step', 6), ['method' => 'bank', 'provider' => 'Test Bank', 'holder_name' => 'Ava Seller', 'account_number' => ''])->assertSessionHasNoErrors();
        $this->assertSame('123456789012', $application->payoutProfile()->firstOrFail()->account_number);
        $this->actingAs($buyer)->get(route('seller-application.create', ['step' => 3]))->assertOk()->assertSee(Storage::disk('public')->url($application->logo_path), false);
        $this->actingAs($buyer)->get(route('seller-application.create', ['step' => 7]))->assertOk()->assertSee('•••• 9012')->assertDontSee('123456789012');
        $policies = array_fill_keys(['seller_agreement', 'terms', 'privacy', 'prohibited_products', 'returns_refunds', 'fees'], '1');
        $this->actingAs($buyer)->post(route('seller-application.store'), ['agreements' => $policies])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('seller_applications', ['id' => $application->id, 'status' => 'submitted']);
        $this->assertSame(5, $application->policyAcceptances()->where('version', '1.0')->count());
        $this->assertTrue($application->policyAcceptances()->where('policy_type', 'fees')->where('version', '2.0')->exists());
        $this->assertStringNotContainsString('123456789012', json_encode(AuditLog::query()->firstOrFail()->toArray()));
        $this->actingAs($buyer)->post(route('seller-application.store'), ['agreements' => $policies])->assertForbidden();
    }

    public function test_business_applicant_reviews_business_details_before_submitting(): void
    {
        $buyer = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($buyer)->post(route('seller-application.step', 1), ['seller_type' => 'business'])->assertSessionHasNoErrors();
        $this->actingAs($buyer)->post(route('seller-application.step', 2), ['seller_name' => 'Ava Seller', 'phone' => '+639171234567', 'business_name' => 'Ava Trading Corp', 'registration_number' => 'DTI-778899', 'representative_name' => 'Ava Seller'])->assertSessionHasNoErrors();

        $this->actingAs($buyer)->get(route('seller-application.create', ['step' => 5]))
            ->assertOk()
            ->assertSee('name="id_number" type="text" value=""', false);
        $this->actingAs($buyer)->get(route('seller-application.create', ['step' => 7]))
            ->assertOk()
            ->assertSee('Ava Trading Corp')
            ->assertSee('DTI-778899');
    }

    public function test_business_fields_and_corrections_are_enforced(): void
    {
        $buyer = User::factory()->create(['email_verified_at' => now()]);
        $admin = User::factory()->administrator()->create();
        $this->actingAs($buyer)->post(route('seller-application.step', 1), ['seller_type' => 'business'])->assertSessionHasNoErrors();
        $this->actingAs($buyer)->post(route('seller-application.step', 2), ['seller_name' => 'Ava', 'phone' => '+639171234567'])->assertSessionHasErrors(['business_name', 'registration_number', 'representative_name']);
        $this->actingAs($buyer)->post(route('seller-application.step', 5), ['id_type' => 'passport', 'id_number' => 'P123456', 'id_front' => UploadedFile::fake()->image('front.jpg')])->assertSessionHasErrors('business_registration');
        $application = $buyer->sellerApplications()->firstOrFail();
        $application->update(['status' => 'pending']);
        $this->actingAs($admin)->post(route('admin.seller-applications.review', $application))->assertRedirect();
        $this->actingAs($admin)->post(route('admin.seller-applications.request-information', $application), ['reason' => 'Please update the address.', 'steps' => [4]])->assertRedirect();
        $this->assertSame('more_information_required', $application->fresh()->status);
        $this->actingAs($buyer)->post(route('seller-application.step', 2), ['seller_name' => 'Changed', 'phone' => '+639171234567'])->assertForbidden();
        $this->assertDatabaseHas('seller_application_status_histories', ['seller_application_id' => $application->id, 'to_status' => 'more_information_required']);
    }

    public function test_requesting_seller_type_correction_reopens_dependent_steps_from_form_values(): void
    {
        $buyer = User::factory()->create(['email_verified_at' => now()]);
        $admin = User::factory()->administrator()->create();
        $application = $buyer->sellerApplications()->create(['store_name' => 'Test', 'status' => 'submitted']);
        $this->actingAs($admin)->post(route('admin.seller-applications.request-information', $application), ['reason' => 'Please correct your seller type.', 'steps' => ['1']])->assertRedirect();
        $this->assertSame([1, 2, 5, 6], $application->fresh()->correction_steps);
    }

    public function test_browser_cannot_forge_status_and_changing_seller_type_reopens_dependent_steps(): void
    {
        $buyer = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($buyer)->post(route('seller-application.step', 1), ['seller_type' => 'individual', 'status' => 'approved'])->assertSessionHasNoErrors();
        $application = $buyer->sellerApplications()->firstOrFail();
        $this->assertSame('draft', $application->status);
        $application->update(['completed_steps' => [1, 2, 3, 4, 5, 6]]);
        $this->actingAs($buyer)->post(route('seller-application.step', 1), ['seller_type' => 'business'])->assertSessionHasNoErrors();
        $this->assertSame([1, 3, 4], $application->fresh()->completed_steps);
        $this->actingAs($buyer)->post(route('seller-application.store'), ['agreements' => array_fill_keys(['seller_agreement', 'terms', 'privacy', 'prohibited_products', 'returns_refunds', 'fees'], '1')])->assertSessionHasErrors('application');
    }

    public function test_async_step_save_returns_redirect_and_inline_validation_errors(): void
    {
        $buyer = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($buyer)->postJson(route('seller-application.step', 1), ['seller_type' => 'individual'])
            ->assertOk()->assertJsonPath('redirect', route('seller-application.create', ['step' => 2]));
        $this->actingAs($buyer)->postJson(route('seller-application.step', 2), ['seller_name' => 'Ava'])
            ->assertUnprocessable()->assertJsonValidationErrors(['phone', 'date_of_birth']);
        $this->assertSame([1], $buyer->sellerApplications()->firstOrFail()->completed_steps);
    }

    public function test_private_document_access_is_limited_to_admins(): void
    {
        Storage::fake('chat_attachments');
        $buyer = User::factory()->create(['email_verified_at' => now()]);
        $admin = User::factory()->administrator()->create();
        $application = $buyer->sellerApplications()->create(['store_name' => 'Test', 'status' => 'draft']);
        $document = $application->documents()->create(['type' => 'id_front', 'path' => 'seller-applications/test/front.jpg', 'mime_type' => 'image/jpeg']);
        Storage::disk('chat_attachments')->put($document->path, 'private image');
        $this->actingAs($buyer)->get(route('admin.seller-applications.document', [$application, $document]))->assertForbidden();
        $this->actingAs($admin)->get(route('admin.seller-applications.document', [$application, $document]))->assertOk()->assertHeader('Cache-Control', 'no-store, private')->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->actingAs($admin)->get(route('admin.seller-applications.show', $application))->assertOk()->assertDontSee($document->path);
    }

    public function test_review_notes_are_admin_only_and_audited(): void
    {
        $buyer = User::factory()->create(['email_verified_at' => now()]);
        $admin = User::factory()->administrator()->create();
        $application = $buyer->sellerApplications()->create(['store_name' => 'Test', 'status' => 'submitted']);
        $this->actingAs($buyer)->post(route('admin.seller-applications.note', $application), ['note' => 'Review the address.'])->assertForbidden();
        $this->actingAs($admin)->post(route('admin.seller-applications.note', $application), ['note' => 'Review the address.'])->assertRedirect();
        $this->assertDatabaseHas('seller_application_reviews', ['seller_application_id' => $application->id, 'note' => 'Review the address.']);
        $this->assertDatabaseHas('audit_logs', ['entity_id' => $application->id, 'action' => 'seller_application.note']);
    }

    public function test_approval_rolls_back_store_membership_and_status_when_store_creation_fails(): void
    {
        $admin = User::factory()->administrator()->create();
        $buyer = User::factory()->create(['email_verified_at' => now()]);
        $application = $buyer->sellerApplications()->create(['store_name' => 'Rollback Goods', 'seller_name' => 'Ava', 'phone' => '+639171234567', 'address_line' => '10 Main St', 'city' => 'Manila', 'status' => 'submitted']);
        Store::creating(function (): void {
            throw new RuntimeException('Store creation failed');
        });
        try {
            app(ApproveSellerApplication::class)->handle($admin, $application);
            $this->fail('The injected failure should abort approval.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Store creation failed', $exception->getMessage());
        } finally {
            Store::flushEventListeners();
        }
        $this->assertDatabaseCount('tenants', 0);
        $this->assertDatabaseCount('stores', 0);
        $this->assertDatabaseCount('tenant_members', 0);
        $this->assertSame('submitted', $application->fresh()->status);
    }

    public function test_approval_starts_platform_fees_when_the_applicant_accepted_the_current_fee_terms(): void
    {
        $admin = User::factory()->administrator()->create();
        $buyer = User::factory()->create(['email_verified_at' => now()]);
        $application = $buyer->sellerApplications()->create(['store_name' => 'Fee Goods', 'seller_name' => 'Ava', 'phone' => '+639171234567', 'address_line' => '10 Main St', 'city' => 'Manila', 'status' => 'submitted']);
        $application->policyAcceptances()->create(['user_id' => $buyer->id, 'policy_type' => 'fees', 'version' => '2.0', 'accepted_at' => now()]);

        $tenant = app(ApproveSellerApplication::class)->handle($admin, $application);

        $this->assertNotNull($tenant->fresh()->platform_fee_accepted_at);
    }

    public function test_approval_leaves_platform_fees_off_when_the_applicant_accepted_older_fee_terms(): void
    {
        $admin = User::factory()->administrator()->create();
        $buyer = User::factory()->create(['email_verified_at' => now()]);
        $application = $buyer->sellerApplications()->create(['store_name' => 'Old Terms Goods', 'seller_name' => 'Ava', 'phone' => '+639171234567', 'address_line' => '10 Main St', 'city' => 'Manila', 'status' => 'submitted']);
        $application->policyAcceptances()->create(['user_id' => $buyer->id, 'policy_type' => 'fees', 'version' => '1.0', 'accepted_at' => now()]);

        $tenant = app(ApproveSellerApplication::class)->handle($admin, $application);

        $this->assertNull($tenant->fresh()->platform_fee_accepted_at);
    }

    public function test_suspension_blocks_product_creation_and_preserves_records(): void
    {
        $admin = User::factory()->administrator()->create();
        $buyer = User::factory()->create(['email_verified_at' => now()]);
        $application = $buyer->sellerApplications()->create(['store_name' => 'Ava Goods', 'seller_name' => 'Ava', 'phone' => '+639171234567', 'address_line' => '10 Main St', 'city' => 'Manila', 'status' => 'submitted']);
        $tenant = app(ApproveSellerApplication::class)->handle($admin, $application);
        $this->assertTrue($buyer->fresh()->can('create', Product::class));
        app(SuspendStore::class)->handle($admin, $tenant->store, 'Serious marketplace policy violation.');
        $this->assertSame('suspended', $application->fresh()->status);
        $this->assertFalse($buyer->fresh()->can('create', Product::class));
        $this->actingAs($buyer)->post(route('seller.products.store'), ['name' => 'Blocked', 'sku' => 'BLOCK', 'price' => '100.00', 'stock_quantity' => 1])->assertForbidden();
        $this->assertDatabaseHas('stores', ['id' => $tenant->store->id, 'status' => 'suspended']);
    }
}
