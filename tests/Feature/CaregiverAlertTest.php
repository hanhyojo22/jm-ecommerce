<?php

namespace Tests\Feature;

use App\Models\CaregiverLink;
use App\Models\MedicationPlan;
use App\Models\User;
use App\Services\PushNotifier;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class CaregiverAlertTest extends TestCase
{
    use RefreshDatabase;

    public function test_invite_accept_and_remove(): void
    {
        $patient = $this->patient();
        $caregiver = User::factory()->create(['email' => 'anak@example.com', 'email_verified_at' => now()]);

        $linkId = $this->actingAs($patient)->postJson(route('api.v1.caregivers.store'), ['email' => 'Anak@Example.com'])
            ->assertCreated()
            ->assertJsonPath('data.status', 'pending')
            ->json('data.id');

        $this->actingAs($caregiver)->getJson(route('api.v1.caregivers.index'))
            ->assertOk()
            ->assertJsonPath('data.invitations.0.id', $linkId)
            ->assertJsonPath('data.invitations.0.patient_name', 'Maria Santos');
        $this->postJson(route('api.v1.caregivers.accept', $linkId))->assertOk()->assertJsonPath('data.status', 'accepted');

        $this->actingAs($patient)->getJson(route('api.v1.caregivers.index'))->assertJsonPath('data.caregivers.0.status', 'accepted');
        $this->deleteJson(route('api.v1.caregivers.destroy', $linkId))->assertNoContent();
        $this->assertDatabaseCount('caregiver_links', 0);
    }

    public function test_only_the_invited_user_can_accept(): void
    {
        $patient = $this->patient();
        $linkId = $this->actingAs($patient)->postJson(route('api.v1.caregivers.store'), ['email' => 'anak@example.com'])->json('data.id');
        $stranger = User::factory()->create(['email_verified_at' => now()]);

        $this->actingAs($stranger)->postJson(route('api.v1.caregivers.accept', $linkId))->assertNotFound();
        $this->deleteJson(route('api.v1.caregivers.destroy', $linkId))->assertNotFound();
        $this->assertDatabaseHas('caregiver_links', ['id' => $linkId, 'status' => 'pending']);
    }

    public function test_one_alert_per_missed_slot(): void
    {
        [$patient, $caregiver] = $this->linkedPair();
        $this->plan($patient);
        $this->travelTo(CarbonImmutable::parse('2026-12-10 09:30', 'Asia/Manila'));
        $this->expectPush($caregiver, "Maria Santos hasn't marked their 8:00 AM medicine as taken.");

        $this->artisan('medications:check-missed')->assertSuccessful();
        $this->artisan('medications:check-missed')->assertSuccessful();

        $this->assertDatabaseCount('caregiver_alerts', 1);
    }

    public function test_medicine_name_hidden_unless_shared(): void
    {
        [$patient, $caregiver] = $this->linkedPair(shareNames: true);
        $this->plan($patient);
        $this->travelTo(CarbonImmutable::parse('2026-12-10 09:30', 'Asia/Manila'));
        $this->expectPush($caregiver, "Maria Santos hasn't marked Amlodipine 5 mg Tablet (8:00 AM) as taken.");

        $this->artisan('medications:check-missed')->assertSuccessful();
    }

    public function test_logged_or_recent_slots_do_not_alert(): void
    {
        [$patient] = $this->linkedPair();
        $plan = $this->plan($patient, ['08:00', '09:00']);
        $plan->doseLogs()->create([
            'status' => 'taken', 'quantity' => 1, 'client_uuid' => (string) Str::uuid(),
            'scheduled_for' => CarbonImmutable::parse('2026-12-10 08:00', 'Asia/Manila'), 'logged_at' => CarbonImmutable::parse('2026-12-10 08:05', 'Asia/Manila'),
        ]);
        $this->travelTo(CarbonImmutable::parse('2026-12-10 09:30', 'Asia/Manila'));
        $this->mock(PushNotifier::class, fn (MockInterface $notifier) => $notifier->shouldNotReceive('sendToUser'));

        $this->artisan('medications:check-missed')->assertSuccessful();

        $this->assertDatabaseCount('caregiver_alerts', 0);
    }

    private function patient(): User
    {
        return User::factory()->create(['name' => 'Maria Santos', 'email_verified_at' => now(), 'medication_reminders_consent_at' => now()]);
    }

    /**
     * @return array{User, User}
     */
    private function linkedPair(bool $shareNames = false): array
    {
        $patient = $this->patient();
        $caregiver = User::factory()->create(['email_verified_at' => now()]);
        CaregiverLink::query()->forceCreate([
            'patient_user_id' => $patient->id, 'caregiver_user_id' => $caregiver->id, 'invited_email' => $caregiver->email,
            'status' => 'accepted', 'share_medicine_names' => $shareNames,
        ]);

        return [$patient, $caregiver];
    }

    /**
     * @param  list<string>  $times
     */
    private function plan(User $patient, array $times = ['08:00']): MedicationPlan
    {
        return $patient->medicationPlans()->create([
            'product_name' => 'Amlodipine 5 mg Tablet', 'medicine_kind' => 'maintenance', 'dose_quantity' => 1, 'dose_unit' => 'tablet',
            'dose_times' => $times, 'starts_on' => '2026-12-01', 'units_anchor' => 30, 'anchored_at' => CarbonImmutable::parse('2026-12-01', 'Asia/Manila'),
        ]);
    }

    private function expectPush(User $caregiver, string $body): void
    {
        $this->mock(PushNotifier::class, function (MockInterface $notifier) use ($caregiver, $body): void {
            $notifier->shouldReceive('sendToUser')->once()->with(Mockery::on(fn (User $user): bool => $user->is($caregiver)), 'Medicine reminder', $body, ['type' => 'medication']);
        });
    }
}
