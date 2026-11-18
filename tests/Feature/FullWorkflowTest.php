<?php

namespace Tests\Feature;

use App\Enums\DispatchStatus;
use App\Enums\LoadingStatus;
use App\Models\Buyer;
use App\Models\Loading;
use App\Models\Mill;
use App\Models\RiceVariety;
use App\Models\Sample;
use App\Models\SampleBundle;
use App\Models\SampleDispatch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The client's whole story, end to end, by an employee account:
 * collect samples at two mills in one bundle → send one sample to two
 * buyers → A confirms, B rejects → load A's order on two trucks with
 * photos → complete → every history page still shows everything.
 */
class FullWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_complete_sample_to_loading_lifecycle(): void
    {
        Storage::fake('local');
        $employee = User::factory()->employee()->create();
        $millA = Mill::factory()->create(['name' => 'ABC Rice Mill', 'city' => 'Gujranwala']);
        $millB = Mill::factory()->create(['name' => 'XYZ Rice Mill', 'city' => 'Hafizabad']);
        $v1121 = RiceVariety::factory()->create(['name' => '1121']);
        $buyerA = Buyer::factory()->create(['name' => 'Buyer A']);
        $buyerB = Buyer::factory()->create(['name' => 'Buyer B']);
        $this->actingAs($employee);

        // Bundle #001: two samples from mill A, three from mill B.
        $rows = [];
        foreach ([$millA, $millA, $millB, $millB, $millB] as $mill) {
            $rows[] = ['mill_id' => $mill->id, 'rice_variety_id' => $v1121->id, 'quantity' => 500, 'quantity_unit' => 'g'];
        }
        $this->post(route('bundles.store'), ['bundle_date' => now()->subDays(5)->toDateString(), 'samples' => $rows])->assertSessionHasNoErrors();
        $bundle = SampleBundle::firstOrFail();
        $this->assertSame(2, $millA->samples()->count());
        $this->assertSame(3, $millB->samples()->count());

        // Sample SMP-0001 is sent to Buyer A and Buyer B.
        $sample = Sample::where('sample_number', 'SMP-0001')->firstOrFail();
        $this->post(route('dispatches.store'), [
            'sample_ids' => [$sample->id], 'buyer_ids' => [$buyerA->id, $buyerB->id], 'dispatch_date' => now()->subDays(4)->toDateString(),
        ])->assertSessionHasNoErrors();
        $toA = SampleDispatch::where('buyer_id', $buyerA->id)->firstOrFail();
        $toB = SampleDispatch::where('buyer_id', $buyerB->id)->firstOrFail();

        // Buyer A confirms, Buyer B rejects.
        $this->put(route('dispatches.respond.update', $toA), [
            'status' => 'confirmed', 'confirmed_quantity' => 1000, 'confirmed_quantity_unit' => 'maund', 'rate' => 9500,
            'rate_unit' => 'per_maund', 'packing_type' => 'PP bag', 'packing_details' => '50 kg bags', 'confirmation_date' => now()->subDays(3)->toDateString(),
        ])->assertSessionHasNoErrors();
        $this->put(route('dispatches.respond.update', $toB), [
            'status' => 'rejected', 'rejection_reason' => 'Price not agreed', 'rejection_date' => now()->subDays(3)->toDateString(),
        ])->assertSessionHasNoErrors();

        // Only A's order is in the Loading section.
        $this->get(route('loading.index'))->assertSee($toA->dispatch_number)->assertDontSee($toB->dispatch_number);
        $this->get(route('loadings.create', $toB))->assertForbidden();

        // Loaded on two trucks with photos; the second completes the order.
        $this->post(route('loadings.store', $toA), [
            'truck_number' => 'TLE-1001', 'loading_date' => now()->subDays(2)->toDateString(), 'quantity' => 600, 'quantity_unit' => 'maund',
            'bag_count' => 1200, 'receiver_name' => 'Bilal', 'action' => 'loaded', 'photos' => [UploadedFile::fake()->image('t1.jpg')],
        ])->assertSessionHasNoErrors();
        $this->post(route('loadings.store', $toA), [
            'truck_number' => 'TLE-1002', 'loading_date' => now()->subDay()->toDateString(), 'quantity' => 400, 'quantity_unit' => 'maund',
            'action' => 'complete', 'photos' => [UploadedFile::fake()->image('t2.jpg'), UploadedFile::fake()->image('t3.jpg')],
        ])->assertSessionHasNoErrors();

        $toA->refresh()->load('loadings.photos');
        $this->assertSame(LoadingStatus::Completed, $toA->loading_status);
        $this->assertCount(2, $toA->loadings);
        $this->assertSame(3, $toA->loadings->sum(fn (Loading $l) => $l->photos->count()));
        $this->assertSame(100, $toA->loadedPercent());
        $this->assertSame(DispatchStatus::Rejected, $toB->fresh()->status);

        // Permanent history is intact everywhere.
        $this->get(route('mills.show', $millA))->assertSee('SMP-0001')->assertSee('SMP-0002');
        $this->get(route('buyers.show', $buyerA))->assertSee($toA->dispatch_number)->assertSee('1,000 maund');
        $this->get(route('buyers.show', $buyerB))->assertSee($toB->dispatch_number)->assertSee('Price not agreed');
        $this->get(route('samples.show', $sample))->assertSee('Buyer A')->assertSee('Buyer B');
        $this->get(route('bundles.show', $bundle))->assertSee('ABC Rice Mill')->assertSee('XYZ Rice Mill');
        $this->get(route('records.index', ['type' => 'completed']))->assertSee($toA->dispatch_number);
        $this->get(route('records.index', ['type' => 'rejections']))->assertSee($toB->dispatch_number);
        $this->get(route('records.index', ['type' => 'loadings']))->assertSee('TLE-1001')->assertSee('TLE-1002');
        $this->get(route('dispatches.show', $toA))->assertSee('Order completed');

        // History is protected: nothing in this chain can be deleted by the employee.
        $this->delete(route('samples.destroy', $sample))->assertForbidden();
        $this->delete(route('mills.destroy', $millA))->assertForbidden();
        $this->delete(route('buyers.destroy', $buyerA))->assertForbidden();
        $this->delete(route('loadings.destroy', $toA->loadings->first()))->assertForbidden();
    }
}
