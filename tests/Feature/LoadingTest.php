<?php

namespace Tests\Feature;

use App\Enums\LoadingStatus;
use App\Enums\TruckLoadingStatus;
use App\Models\Loading;
use App\Models\SampleDispatch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LoadingTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->user = User::factory()->employee()->create();
    }

    public function test_confirmed_dispatch_appears_in_loading_section(): void
    {
        $confirmed = SampleDispatch::factory()->confirmed()->create();
        $rejected = SampleDispatch::factory()->rejected()->create();
        $pending = SampleDispatch::factory()->create();

        $this->actingAs($this->user)->get(route('loading.index'))
            ->assertOk()
            ->assertSee($confirmed->dispatch_number)
            ->assertDontSee($rejected->dispatch_number)
            ->assertDontSee($pending->dispatch_number);
    }

    public function test_confirming_via_the_form_puts_the_order_into_loading(): void
    {
        $dispatch = SampleDispatch::factory()->create();

        $this->actingAs($this->user)->put(route('dispatches.respond.update', $dispatch), [
            'status' => 'confirmed', 'confirmed_quantity' => 300, 'confirmed_quantity_unit' => 'maund', 'rate' => 9000,
            'rate_unit' => 'per_maund', 'packing_type' => '25 kg', 'confirmation_date' => now()->toDateString(),
        ]);

        $this->get(route('loading.index', ['tab' => 'pending']))->assertSee($dispatch->dispatch_number);
    }

    public function test_rejected_or_pending_dispatch_cannot_enter_loading(): void
    {
        foreach ([SampleDispatch::factory()->rejected()->create(), SampleDispatch::factory()->create()] as $dispatch) {
            $this->actingAs($this->user)->get(route('loadings.create', $dispatch))->assertForbidden();
            $this->post(route('loadings.store', $dispatch), $this->payload())->assertForbidden();
        }

        $this->assertSame(0, Loading::count());
    }

    public function test_loading_details_and_photos_are_saved(): void
    {
        $dispatch = SampleDispatch::factory()->confirmed()->create();

        $this->actingAs($this->user)->post(route('loadings.store', $dispatch), $this->payload([
            'photos' => [UploadedFile::fake()->image('truck.jpg'), UploadedFile::fake()->image('bags.jpg')],
        ]))->assertSessionHasNoErrors();

        $loading = Loading::firstOrFail();
        $this->assertSame('LD-0001', $loading->loading_number);
        $this->assertSame('LES 4521', $loading->truck_number);
        $this->assertSame('250.00', $loading->quantity);
        $this->assertSame('maund', $loading->quantity_unit);
        $this->assertSame(500, $loading->bag_count);
        $this->assertSame('Bilal', $loading->receiver_name);
        $this->assertSame(TruckLoadingStatus::Pending, $loading->status);
        $this->assertCount(2, $loading->photos);
        $loading->photos->each(fn ($p) => Storage::disk('local')->assertExists($p->path));
        $this->assertSame(LoadingStatus::InProgress, $dispatch->fresh()->loading_status);

        $this->get(route('loadings.show', $loading))->assertOk()->assertSee('LES 4521');
        $this->get(route('loading-photos.show', $loading->photos->first()))->assertOk();
    }

    public function test_one_order_can_be_loaded_across_several_trucks_then_completed(): void
    {
        $dispatch = SampleDispatch::factory()->confirmed()->create(['confirmed_quantity' => 500]);

        $this->actingAs($this->user)->post(route('loadings.store', $dispatch), $this->payload(['truck_number' => 'TLE-1111', 'action' => 'loaded']));
        $this->assertSame(LoadingStatus::InProgress, $dispatch->fresh()->loading_status);

        $this->post(route('loadings.store', $dispatch), $this->payload(['truck_number' => 'TLE-2222', 'action' => 'complete']))
            ->assertRedirect(route('loading.index'));

        $dispatch->refresh()->load('loadings');
        $this->assertCount(2, $dispatch->loadings);
        $this->assertSame(LoadingStatus::Completed, $dispatch->loading_status);
        $this->assertNotNull($dispatch->loading_completed_at);
        $this->assertSame(['maund' => 500.0], $dispatch->loadedTotals());
        $this->assertSame(100, $dispatch->loadedPercent());

        // Completed orders accept no more trucks.
        $this->post(route('loadings.store', $dispatch), $this->payload())->assertForbidden();
        $this->get(route('loading.index', ['tab' => 'completed']))->assertSee($dispatch->dispatch_number);
    }

    public function test_receiver_and_unit_are_automatic_and_the_order_completes_when_fully_loaded(): void
    {
        $dispatch = SampleDispatch::factory()->confirmed()->create(['confirmed_quantity' => 1000]);
        $simple = fn (string $truck, int $qty) => ['truck_number' => $truck, 'loading_date' => now()->toDateString(), 'quantity' => $qty, 'packing_type' => '50 kg'];

        $this->actingAs($this->user)->from(route('loading.index'))->get(route('loadings.create', $dispatch))
            ->assertOk()->assertDontSee('name="receiver_name"', false)->assertDontSee('name="quantity_unit"', false)
            ->assertDontSee('still loading')->assertSee('Save truck');

        $this->post(route('loadings.store', $dispatch), $simple('TLE-1', 600) + ['return_to' => route('loading.index')])
            ->assertRedirect(route('loading.index'))->assertSessionHas('success', 'Truck TLE-1 saved. 600 maund of 1,000 maund loaded (400 maund left).');

        $first = Loading::firstOrFail();
        $this->assertSame('maund', $first->quantity_unit);
        $this->assertSame($dispatch->buyer->name, $first->receiver_name);
        $this->assertSame(TruckLoadingStatus::Completed, $first->status);
        $this->assertSame(LoadingStatus::InProgress, $dispatch->fresh()->loading_status);

        // The truck that brings the total to the confirmed quantity completes the order by itself.
        $this->post(route('loadings.store', $dispatch), $simple('TLE-2', 400))
            ->assertSessionHas('success', 'Truck TLE-2 saved. Order completed: 1,000 maund loaded.');
        $this->assertSame(LoadingStatus::Completed, $dispatch->fresh()->loading_status);
    }

    public function test_truck_recorder_can_still_add_photos_after_the_order_completed_itself(): void
    {
        $dispatch = SampleDispatch::factory()->confirmed()->create(['confirmed_quantity' => 100]);
        $this->actingAs($this->user)->post(route('loadings.store', $dispatch), ['truck_number' => 'TLE-9', 'loading_date' => now()->toDateString(), 'quantity' => 100]);
        $loading = Loading::firstOrFail();
        $this->assertTrue($dispatch->fresh()->isLoadingCompleted());

        $this->post(route('loadings.photos.store', $loading), ['photos' => [UploadedFile::fake()->image('late.jpg')]])->assertSessionHasNoErrors();
        $this->assertCount(1, $loading->photos()->get());

        $other = User::factory()->employee()->create();
        $this->actingAs($other)->post(route('loadings.photos.store', $loading), ['photos' => [UploadedFile::fake()->image('x.jpg')]])->assertForbidden();
    }

    public function test_order_cannot_be_completed_while_a_truck_is_still_loading(): void
    {
        $loading = Loading::factory()->create();
        $dispatch = $loading->dispatch;

        $this->actingAs($this->user)->patch(route('dispatches.complete-loading', $dispatch))->assertSessionHas('error');
        $this->assertNotSame(LoadingStatus::Completed, $dispatch->fresh()->loading_status);

        $this->patch(route('loadings.loaded', $loading))->assertRedirect();
        $this->patch(route('dispatches.complete-loading', $dispatch))->assertRedirect(route('dispatches.show', $dispatch));
        $this->assertSame(LoadingStatus::Completed, $dispatch->fresh()->loading_status);
    }

    public function test_order_without_trucks_cannot_be_completed(): void
    {
        $dispatch = SampleDispatch::factory()->confirmed()->create();

        $this->actingAs($this->user)->patch(route('dispatches.complete-loading', $dispatch))->assertForbidden();
    }

    public function test_loaded_order_cannot_be_reopened_or_rejected(): void
    {
        $loading = Loading::factory()->create();
        $dispatch = $loading->dispatch;

        $this->actingAs($this->user)->patch(route('dispatches.reopen', $dispatch))->assertForbidden();
        $this->put(route('dispatches.respond.update', $dispatch), [
            'status' => 'rejected', 'rejection_date' => now()->toDateString(),
        ])->assertSessionHas('error');

        $this->assertTrue($dispatch->fresh()->isConfirmed());
    }

    public function test_loading_validation(): void
    {
        $dispatch = SampleDispatch::factory()->confirmed()->create(['confirmation_date' => now()->subDay()]);

        $this->actingAs($this->user)->post(route('loadings.store', $dispatch), [
            'truck_number' => '<script>', 'loading_date' => now()->subDays(5)->toDateString(), 'quantity' => 0, 'quantity_unit' => 'barrels',
            'photos' => [UploadedFile::fake()->create('virus.exe', 10)],
        ])->assertSessionHasErrors(['truck_number', 'loading_date', 'quantity', 'quantity_unit', 'photos.0']);

        $this->assertSame(0, Loading::count());
    }

    public function test_photos_can_be_added_and_removed(): void
    {
        $loading = Loading::factory()->create();

        $this->actingAs($this->user)->post(route('loadings.photos.store', $loading), [
            'photos' => [UploadedFile::fake()->image('a.jpg'), UploadedFile::fake()->image('b.png')],
        ])->assertRedirect(route('loadings.show', $loading));
        $this->assertCount(2, $loading->photos()->get());

        $photo = $loading->photos()->first();
        $this->delete(route('loading-photos.destroy', $photo))->assertRedirect();
        Storage::disk('local')->assertMissing($photo->path);
        $this->assertCount(1, $loading->photos()->get());
    }

    public function test_completed_records_are_protected_from_employees(): void
    {
        $loading = Loading::factory()->loaded()->create();

        $this->actingAs($this->user)->get(route('loadings.edit', $loading))->assertForbidden();
        $this->delete(route('loadings.destroy', $loading))->assertForbidden();

        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->get(route('loadings.edit', $loading))->assertOk();
        $this->delete(route('loadings.destroy', $loading))->assertForbidden(); // loaded trucks are permanent
    }

    public function test_admin_can_remove_an_open_truck_record(): void
    {
        $loading = Loading::factory()->create();
        $dispatch = $loading->dispatch;
        $dispatch->forceFill(['loading_status' => LoadingStatus::InProgress])->save();

        $this->actingAs(User::factory()->admin()->create())->delete(route('loadings.destroy', $loading))->assertRedirect(route('dispatches.show', $dispatch));
        $this->assertModelMissing($loading);
        $this->assertSame(LoadingStatus::Pending, $dispatch->fresh()->loading_status);
    }

    public function test_buyer_history_shows_completed_orders(): void
    {
        $loading = Loading::factory()->loaded()->create();
        $dispatch = $loading->dispatch;
        $dispatch->forceFill(['loading_status' => LoadingStatus::Completed, 'loading_completed_at' => now()])->save();

        $this->actingAs($this->user)->get(route('buyers.show', [$dispatch->buyer, 'status' => 'completed']))
            ->assertOk()->assertSee($dispatch->dispatch_number);
        $this->get(route('dispatches.show', $dispatch))->assertOk()->assertSee($loading->truck_number);
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'truck_number' => 'les 4521', 'loading_date' => now()->toDateString(), 'quantity' => 250, 'quantity_unit' => 'maund',
            'bag_count' => 500, 'packing_type' => '50 kg', 'receiver_name' => 'Bilal', 'driver_name' => 'Aslam', 'driver_phone' => '03001234567',
            'action' => 'save',
        ], $overrides);
    }
}
