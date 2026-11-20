<?php

namespace Tests\Feature;

use App\Enums\DispatchStatus;
use App\Enums\LoadingStatus;
use App\Models\Buyer;
use App\Models\Sample;
use App\Models\SampleDispatch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DispatchTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->employee()->create();
    }

    public function test_one_sample_can_be_dispatched_to_multiple_buyers(): void
    {
        $sample = Sample::factory()->create(['collection_date' => now()->subDays(2)]);
        [$buyerA, $buyerB] = Buyer::factory()->count(2)->create();

        $this->actingAs($this->user)->post(route('dispatches.store'), [
            'sample_ids' => [$sample->id], 'buyer_ids' => [$buyerA->id, $buyerB->id], 'dispatch_date' => now()->toDateString(),
        ])->assertSessionHasNoErrors();

        $dispatches = SampleDispatch::where('sample_id', $sample->id)->orderBy('id')->get();
        $this->assertCount(2, $dispatches);
        $this->assertEquals([$buyerA->id, $buyerB->id], $dispatches->pluck('buyer_id')->all());
        $this->assertEquals(['DSP-0001', 'DSP-0002'], $dispatches->pluck('dispatch_number')->all());
        $this->assertTrue($dispatches->every(fn ($d) => $d->status === DispatchStatus::Pending));
        $this->assertNull($dispatches->first()->loading_status);
    }

    public function test_several_samples_to_several_buyers_creates_every_pair(): void
    {
        $samples = Sample::factory()->count(3)->create(['collection_date' => now()->subDay()]);
        $buyers = Buyer::factory()->count(2)->create();

        $this->actingAs($this->user)->post(route('dispatches.store'), [
            'sample_ids' => $samples->pluck('id')->all(), 'buyer_ids' => $buyers->pluck('id')->all(), 'dispatch_date' => now()->toDateString(),
        ])->assertRedirect(route('dispatches.index', ['status' => 'pending']));

        $this->assertSame(6, SampleDispatch::count());
        $this->assertSame(2, $samples->first()->dispatches()->count());
    }

    public function test_buyer_responses_remain_independent(): void
    {
        $sample = Sample::factory()->create();
        $toA = SampleDispatch::factory()->for($sample)->create();
        $toB = SampleDispatch::factory()->for($sample)->create();

        $this->actingAs($this->user)->put(route('dispatches.respond.update', $toA), $this->confirmation())->assertSessionHasNoErrors();
        $this->put(route('dispatches.respond.update', $toB), $this->rejection())->assertSessionHasNoErrors();

        $toA->refresh();
        $toB->refresh();
        $this->assertSame(DispatchStatus::Confirmed, $toA->status);
        $this->assertSame('500.00', $toA->confirmed_quantity);
        $this->assertSame('9500.00', $toA->rate);
        $this->assertSame(LoadingStatus::Pending, $toA->loading_status);
        $this->assertSame(DispatchStatus::Rejected, $toB->status);
        $this->assertNull($toB->rejection_reason); // no reason is asked any more
        $this->assertSame(now()->toDateString(), $toB->rejection_date->toDateString());
        $this->assertNull($toB->loading_status);
        $this->assertNull($toB->confirmed_quantity);
    }

    public function test_confirmation_requires_order_details(): void
    {
        $dispatch = SampleDispatch::factory()->create();

        $this->actingAs($this->user)->put(route('dispatches.respond.update', $dispatch), ['status' => 'confirmed'])
            ->assertSessionHasErrors(['confirmed_quantity', 'confirmed_quantity_unit', 'rate', 'rate_unit', 'packing_type', 'confirmation_date']);

        $this->assertTrue($dispatch->fresh()->isPending());

        $this->put(route('dispatches.respond.update', $dispatch), ['status' => 'pending'])->assertSessionHasErrors('status');
        $this->assertTrue($dispatch->fresh()->isPending());
    }

    public function test_rejection_needs_no_reason_and_defaults_to_today(): void
    {
        $dispatch = SampleDispatch::factory()->create(['dispatch_date' => now()->subDays(2)]);

        $this->actingAs($this->user)->from(route('dispatches.index'))
            ->put(route('dispatches.respond.update', $dispatch), ['status' => 'rejected'])
            ->assertSessionHasNoErrors()->assertRedirect(route('dispatches.index'));

        $dispatch->refresh();
        $this->assertTrue($dispatch->isRejected());
        $this->assertNull($dispatch->rejection_reason);
        $this->assertSame(now()->toDateString(), $dispatch->rejection_date->toDateString());
        $this->assertDatabaseHas('activities', ['subject_type' => 'dispatch', 'subject_id' => $dispatch->id, 'description' => "{$dispatch->buyer->name} rejected the sample"]);
    }

    public function test_response_popup_is_offered_on_dispatch_pages(): void
    {
        $pending = SampleDispatch::factory()->create();

        $this->actingAs($this->user);
        $this->get(route('dispatches.index'))->assertOk()->assertSee('id="respondModal"', false)->assertSee('data-respond=', false)
            ->assertDontSee('rejection_reason', false);
        $this->get(route('dashboard'))->assertOk()->assertSee('data-respond=', false);
        $this->get(route('dispatches.show', $pending))->assertOk()->assertSee('data-respond-auto', false);

        // Old links open the dispatch with the popup.
        $this->get(route('dispatches.respond', $pending))->assertRedirect(route('dispatches.show', [$pending, 'respond' => 1]));

        // Invalid input comes back with the popup reopened for the same dispatch.
        $this->from(route('dispatches.index'))->put(route('dispatches.respond.update', $pending), ['status' => 'confirmed', 'respond_dispatch_id' => $pending->id])
            ->assertRedirect(route('dispatches.index'));
        $this->get(route('dispatches.index'))->assertSee('data-reopen=', false)->assertSee('The confirmed quantity field is required.');
    }

    public function test_response_date_cannot_precede_dispatch_date(): void
    {
        $dispatch = SampleDispatch::factory()->create(['dispatch_date' => now()->subDay()]);

        $this->actingAs($this->user)->put(route('dispatches.respond.update', $dispatch), $this->rejection(['rejection_date' => now()->subDays(5)->toDateString()]))
            ->assertSessionHasErrors('rejection_date');
    }

    public function test_rejected_dispatch_cannot_be_switched_to_confirmed_without_reopening(): void
    {
        $dispatch = SampleDispatch::factory()->rejected()->create();

        $this->actingAs($this->user)->put(route('dispatches.respond.update', $dispatch), $this->confirmation())
            ->assertSessionHas('error');

        $this->assertTrue($dispatch->fresh()->isRejected());
    }

    public function test_reopen_returns_dispatch_to_pending_and_keeps_history(): void
    {
        $dispatch = SampleDispatch::factory()->rejected()->create();

        $this->actingAs($this->user)->patch(route('dispatches.reopen', $dispatch))->assertRedirect(route('dispatches.show', $dispatch));

        $dispatch->refresh();
        $this->assertTrue($dispatch->isPending());
        $this->assertNull($dispatch->rejection_reason);
        $activity = $dispatch->activities()->where('event', 'reopened')->firstOrFail();
        $this->assertSame('Quality not matched', $activity->properties['previous']['rejection_reason']);

        // Now a different answer can be recorded.
        $this->put(route('dispatches.respond.update', $dispatch), $this->confirmation())->assertSessionHasNoErrors();
        $this->assertTrue($dispatch->fresh()->isConfirmed());
    }

    public function test_confirmation_details_can_be_corrected(): void
    {
        $dispatch = SampleDispatch::factory()->confirmed()->create();

        $this->actingAs($this->user)->put(route('dispatches.respond.update', $dispatch), $this->confirmation(['rate' => 9800]))
            ->assertSessionHasNoErrors();

        $this->assertSame('9800.00', $dispatch->fresh()->rate);
        $this->assertSame(LoadingStatus::Pending, $dispatch->fresh()->loading_status);
    }

    public function test_duplicate_active_dispatch_to_same_buyer_is_blocked(): void
    {
        $existing = SampleDispatch::factory()->create();

        $this->actingAs($this->user)->post(route('dispatches.store'), [
            'sample_ids' => [$existing->sample_id], 'buyer_ids' => [$existing->buyer_id], 'dispatch_date' => now()->toDateString(),
        ])->assertSessionHasErrors('buyer_ids');

        $this->assertSame(1, SampleDispatch::count());
    }

    public function test_sample_can_be_resent_to_buyer_after_rejection(): void
    {
        $existing = SampleDispatch::factory()->rejected()->create();

        $this->actingAs($this->user)->post(route('dispatches.store'), [
            'sample_ids' => [$existing->sample_id], 'buyer_ids' => [$existing->buyer_id], 'dispatch_date' => now()->toDateString(),
        ])->assertSessionHasNoErrors();

        $this->assertSame(2, SampleDispatch::count());
    }

    public function test_dispatch_date_cannot_precede_collection_date(): void
    {
        $sample = Sample::factory()->create(['collection_date' => now()]);
        $buyer = Buyer::factory()->create();

        $this->actingAs($this->user)->post(route('dispatches.store'), [
            'sample_ids' => [$sample->id], 'buyer_ids' => [$buyer->id], 'dispatch_date' => now()->subDays(3)->toDateString(),
        ])->assertSessionHasErrors('dispatch_date');
    }

    public function test_buyer_history_shows_all_dispatch_outcomes(): void
    {
        $buyer = Buyer::factory()->create();
        $pending = SampleDispatch::factory()->for($buyer)->create();
        $confirmed = SampleDispatch::factory()->for($buyer)->confirmed()->create();
        $rejected = SampleDispatch::factory()->for($buyer)->rejected()->create();
        $otherBuyers = SampleDispatch::factory()->create();

        $this->actingAs($this->user)->get(route('buyers.show', $buyer))
            ->assertOk()
            ->assertSee($pending->dispatch_number)
            ->assertSee($confirmed->dispatch_number)->assertSee('500 maund')->assertSee('Rs 9,500')
            ->assertSee($rejected->dispatch_number)
            ->assertDontSee($otherBuyers->dispatch_number);

        $this->get(route('buyers.show', [$buyer, 'status' => 'rejected']))
            ->assertSee($rejected->dispatch_number)->assertDontSee($confirmed->dispatch_number);
    }

    public function test_dispatched_sample_and_buyer_cannot_be_deleted(): void
    {
        $dispatch = SampleDispatch::factory()->create();
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->delete(route('samples.destroy', $dispatch->sample))->assertForbidden();
        $this->delete(route('buyers.destroy', $dispatch->buyer))->assertForbidden();
    }

    public function test_only_admin_can_delete_pending_dispatch(): void
    {
        $pending = SampleDispatch::factory()->create();
        $confirmed = SampleDispatch::factory()->confirmed()->create();

        $this->actingAs($this->user)->delete(route('dispatches.destroy', $pending))->assertForbidden();
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->delete(route('dispatches.destroy', $confirmed))->assertForbidden();
        $this->delete(route('dispatches.destroy', $pending))->assertRedirect(route('dispatches.index'));
        $this->assertModelMissing($pending);
    }

    public function test_dispatch_pages_render(): void
    {
        $dispatch = SampleDispatch::factory()->confirmed()->create();
        SampleDispatch::factory()->for($dispatch->sample)->rejected()->create();

        $this->actingAs($this->user);
        $this->get(route('dispatches.index'))->assertOk()->assertSee($dispatch->dispatch_number);
        $this->get(route('dispatches.index', ['status' => 'rejected']))->assertOk()->assertDontSee($dispatch->dispatch_number);
        $this->get(route('dispatches.create', ['sample_ids' => [$dispatch->sample_id]]))->assertOk()->assertSee($dispatch->sample->sample_number);
        $this->get(route('dispatches.show', $dispatch))->assertOk()->assertSee('Same sample, other buyers');
        $this->get(route('dispatches.respond', $dispatch))->assertRedirect();
        $this->get(route('samples.show', $dispatch->sample))->assertOk()->assertSee($dispatch->buyer->name);
        $this->getJson(route('lookup.samples', ['q' => $dispatch->sample->sample_number]))->assertOk()->assertJsonCount(1, 'data');
    }

    private function confirmation(array $overrides = []): array
    {
        return array_merge([
            'status' => 'confirmed', 'confirmed_quantity' => 500, 'confirmed_quantity_unit' => 'maund',
            'rate' => 9500, 'rate_unit' => 'per_maund', 'packing_type' => 'PP bag',
            'packing_details' => '50 kg bags', 'confirmation_date' => now()->toDateString(),
        ], $overrides);
    }

    private function rejection(array $overrides = []): array
    {
        return array_merge(['status' => 'rejected', 'rejection_date' => now()->toDateString()], $overrides);
    }
}
