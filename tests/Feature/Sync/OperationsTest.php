<?php

namespace Tests\Feature\Sync;

use App\Enums\DispatchStatus;
use App\Enums\LoadingStatus;
use App\Models\Buyer;
use App\Models\Loading;
use App\Models\LoadingPhoto;
use App\Models\Mill;
use App\Models\RiceVariety;
use App\Models\Sample;
use App\Models\SampleDispatch;
use App\Models\SyncOperation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class OperationsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->user = User::factory()->employee()->create();
        $this->actingAs($this->user);
    }

    private function op(string $type, array $payload, ?string $opUuid = null, ?UploadedFile $photo = null): TestResponse
    {
        $data = ['operation' => json_encode(['uuid' => $opUuid ?? (string) Str::uuid(), 'type' => $type, 'payload' => $payload])];
        if ($photo) {
            $data['photo'] = $photo;
        }

        return $this->post(route('sync.operations'), $data, ['Accept' => 'application/json']);
    }

    private function uuid(): string
    {
        return (string) Str::uuid();
    }

    public function test_requires_a_signed_in_session(): void
    {
        auth()->logout();
        $this->op('mill.create', ['uuid' => $this->uuid(), 'fields' => ['name' => 'X', 'city' => 'Y']])->assertUnauthorized();
        $this->assertSame(0, Mill::count());
    }

    public function test_scenario_1_offline_sample_reaches_the_database_once(): void
    {
        $mill = Mill::factory()->create();
        $variety = RiceVariety::factory()->create();
        $sampleUuid = $this->uuid();
        $opUuid = $this->uuid();
        $payload = ['uuid' => $sampleUuid, 'fields' => [
            'mill_id' => $mill->id, 'rice_variety_id' => $variety->uuid,
            'collection_date' => now()->toDateString(), 'quantity' => 500, 'quantity_unit' => 'g', 'notes' => 'offline',
        ]];

        $first = $this->op('sample.create', $payload, $opUuid)->assertOk()->assertJson(['status' => 'applied', 'replayed' => false]);
        $this->assertSame('samples', $first->json('records.0.type'));
        $this->assertSame($sampleUuid, $first->json('records.0.record.uuid'));
        $this->assertSame('SMP-0255', $first->json('records.0.record.sample_number'));

        // Retry of the same operation (response lost) and a re-send under a new op id: still one sample.
        $this->op('sample.create', $payload, $opUuid)->assertOk()->assertJson(['replayed' => true]);
        $this->op('sample.create', $payload)->assertOk();
        $this->assertSame(1, Sample::count());
        $this->assertTrue(Sample::first()->mill->is($mill));
        $this->assertDatabaseHas('activities', ['subject_type' => 'sample', 'event' => 'created']);
    }

    public function test_offline_chain_mill_sample_dispatch_response_loading_photo_in_dependency_order(): void
    {
        $variety = RiceVariety::factory()->create();
        $mill = $this->uuid();
        $buyer = $this->uuid();
        $sample = $this->uuid();
        $dispatch = $this->uuid();
        $loading = $this->uuid();

        $this->op('mill.create', ['uuid' => $mill, 'fields' => ['name' => 'Offline Mill', 'city' => 'Narowal']])->assertOk();
        $this->op('buyer.create', ['uuid' => $buyer, 'fields' => ['name' => 'Offline Buyer', 'city' => 'Karachi']])->assertOk();
        $this->op('sample.create', ['uuid' => $sample, 'fields' => ['mill_id' => $mill, 'rice_variety_id' => $variety->id, 'collection_date' => now()->toDateString()]])->assertOk();
        $this->op('dispatch.create', ['fields' => ['dispatch_date' => now()->toDateString()], 'pairs' => [['uuid' => $dispatch, 'sample' => $sample, 'buyer' => $buyer]]])->assertOk();
        $this->op('dispatch.respond', ['target' => $dispatch, 'base_version' => 1, 'fields' => [
            'status' => 'confirmed', 'confirmed_quantity' => 300, 'confirmed_quantity_unit' => 'maund', 'rate' => 9000,
            'rate_unit' => 'per_maund', 'packing_type' => '50 kg', 'confirmation_date' => now()->toDateString(),
        ]])->assertOk();
        $this->op('loading.create', ['uuid' => $loading, 'target' => $dispatch, 'fields' => [
            'truck_number' => 'tle 1', 'loading_date' => now()->toDateString(), 'quantity' => 300, 'quantity_unit' => 'maund', 'action' => 'loaded',
        ]])->assertOk();
        $photo = $this->uuid();
        $this->op('photo.add', ['uuid' => $photo, 'target_type' => 'loading', 'target' => $loading], photo: UploadedFile::fake()->image('t.jpg'))->assertOk()
            ->assertJsonPath('records.0.record.photos_count', 1);
        $this->op('order.complete', ['target' => $dispatch])->assertOk()->assertJsonPath('records.0.record.loading_status', 'completed');

        $d = SampleDispatch::where('uuid', $dispatch)->firstOrFail();
        $this->assertSame('Offline Mill', $d->sample->mill->name);
        $this->assertSame('Offline Buyer', $d->buyer->name);
        $this->assertSame(LoadingStatus::Completed, $d->loading_status);
        $this->assertSame('TLE 1', $d->loadings()->first()->truck_number);
        Storage::disk('local')->assertExists(LoadingPhoto::where('uuid', $photo)->firstOrFail()->path);

        // The records show up in the normal online pages.
        $this->get(route('mills.show', $d->sample->mill))->assertSee($d->sample->sample_number);
        $this->get(route('buyers.show', $d->buyer))->assertSee($d->dispatch_number);
        $this->get(route('loading.index', ['tab' => 'completed']))->assertSee($d->dispatch_number);
    }

    public function test_scenario_2_several_offline_samples_from_two_mills(): void
    {
        $millA = Mill::factory()->create();
        $millB = $this->uuid();
        $variety = RiceVariety::factory()->create();
        $this->op('mill.create', ['uuid' => $millB, 'fields' => ['name' => 'New Mill', 'city' => 'Kamoke']])->assertOk();

        $uuids = [];
        foreach ([$millA->id, $millA->id, $millB, $millB, $millB] as $mill) {
            $uuids[] = $uuid = $this->uuid();
            $this->op('sample.create', ['uuid' => $uuid, 'fields' => ['mill_id' => $mill, 'rice_variety_id' => $variety->uuid, 'collection_date' => now()->toDateString(), 'quantity' => 5, 'quantity_unit' => 'kg']])->assertOk();
        }

        $this->assertSame(2, Sample::where('mill_id', $millA->id)->count());
        $this->assertSame(3, Sample::where('mill_id', Mill::where('uuid', $millB)->value('id'))->count());
        $this->assertSame(5, Sample::whereIn('uuid', $uuids)->distinct()->count('sample_number'));
    }

    public function test_bundle_changes_saved_before_the_update_become_independent_samples(): void
    {
        $mill = Mill::factory()->create();
        $variety = RiceVariety::factory()->create();
        $rows = [];
        foreach (range(0, 2) as $i) {
            $rows["r{$i}"] = ['uuid' => $this->uuid(), 'mill_id' => $mill->id, 'rice_variety_id' => $variety->uuid, 'quantity' => 500, 'quantity_unit' => 'g', 'sample_number' => 'IGNORED'];
        }
        $payload = ['uuid' => $this->uuid(), 'fields' => ['bundle_number' => 'B-1', 'bundle_date' => now()->subDay()->toDateString(), 'samples' => $rows]];

        $res = $this->op('bundle.create', $payload)->assertOk();
        $this->assertCount(3, $res->json('records'));
        foreach ($rows as $row) {
            $this->assertDatabaseHas('samples', ['uuid' => $row['uuid'], 'mill_id' => $mill->id, 'collection_date' => now()->subDay()->startOfDay()]);
        }
        $this->assertSame(['SMP-0255', 'SMP-0256', 'SMP-0257'], Sample::orderBy('id')->pluck('sample_number')->all());

        // Retried: no duplicates.
        $this->op('bundle.create', $payload)->assertOk();
        $this->assertSame(3, Sample::count());
    }

    public function test_scenarios_3_and_4_one_sample_to_two_buyers_with_independent_responses(): void
    {
        $sample = Sample::factory()->create(['collection_date' => now()->subDay()]);
        [$a, $b] = Buyer::factory()->count(2)->create();
        $toA = $this->uuid();
        $toB = $this->uuid();

        $this->op('dispatch.create', ['fields' => ['dispatch_date' => now()->toDateString()], 'pairs' => [
            ['uuid' => $toA, 'sample' => $sample->uuid, 'buyer' => $a->uuid],
            ['uuid' => $toB, 'sample' => $sample->uuid, 'buyer' => $b->id],
        ]])->assertOk()->assertJsonCount(2, 'records');

        $this->op('dispatch.respond', ['target' => $toA, 'base_version' => 1, 'fields' => [
            'status' => 'confirmed', 'confirmed_quantity' => 100, 'confirmed_quantity_unit' => 'ton', 'rate' => 250000,
            'rate_unit' => 'per_ton', 'packing_type' => '25 kg', 'confirmation_date' => now()->toDateString(),
        ]])->assertOk();
        $this->op('dispatch.respond', ['target' => $toB, 'base_version' => 1, 'fields' => [
            'status' => 'rejected', 'rejection_date' => now()->toDateString(),
        ]])->assertOk();

        $this->assertSame(DispatchStatus::Confirmed, SampleDispatch::where('uuid', $toA)->first()->status);
        $this->assertSame(LoadingStatus::Pending, SampleDispatch::where('uuid', $toA)->first()->loading_status);
        $this->assertSame(DispatchStatus::Rejected, SampleDispatch::where('uuid', $toB)->first()->status);
        $this->assertNull(SampleDispatch::where('uuid', $toB)->first()->loading_status);
    }

    public function test_scenario_5_loading_photos_upload_once(): void
    {
        $loading = Loading::factory()->create();
        $photo = $this->uuid();
        $op = $this->uuid();

        $this->op('photo.add', ['uuid' => $photo, 'target_type' => 'loading', 'target' => $loading->uuid], $op, UploadedFile::fake()->image('a.jpg'))->assertOk();
        $this->op('photo.add', ['uuid' => $photo, 'target_type' => 'loading', 'target' => $loading->uuid], $op, UploadedFile::fake()->image('a.jpg'))->assertJson(['replayed' => true]);
        $this->op('photo.add', ['uuid' => $photo, 'target_type' => 'loading', 'target' => $loading->uuid], null, UploadedFile::fake()->image('a.jpg'))->assertOk();

        $this->assertSame(1, $loading->photos()->count());
        $this->assertCount(1, Storage::disk('local')->allFiles('loadings/'.$loading->id));
    }

    public function test_sample_photo_can_be_added_after_the_sample(): void
    {
        $sample = Sample::factory()->create();

        $this->op('photo.add', ['uuid' => $this->uuid(), 'target_type' => 'sample', 'target' => $sample->uuid], photo: UploadedFile::fake()->image('s.jpg'))
            ->assertOk()->assertJsonPath('records.0.record.has_photo', true);
        Storage::disk('local')->assertExists($sample->fresh()->photo_path);
    }

    public function test_scenario_6_interrupted_sync_retry_creates_no_duplicates(): void
    {
        $mill = Mill::factory()->create();
        $variety = RiceVariety::factory()->create();
        $ops = [];
        foreach (range(1, 3) as $i) {
            $ops[] = [$this->uuid(), ['uuid' => $this->uuid(), 'fields' => ['mill_id' => $mill->id, 'rice_variety_id' => $variety->id, 'collection_date' => now()->toDateString()]]];
        }

        // First attempt: only the first two got through before the connection dropped.
        $this->op('sample.create', $ops[0][1], $ops[0][0])->assertOk();
        $this->op('sample.create', $ops[1][1], $ops[1][0])->assertOk();
        // Device retries the whole queue.
        foreach ($ops as [$opUuid, $payload]) {
            $this->op('sample.create', $payload, $opUuid)->assertOk();
        }

        $this->assertSame(3, Sample::count());
        $this->assertSame(3, SyncOperation::count());
    }

    public function test_scenario_7_conflicting_mill_edit_is_not_silently_applied(): void
    {
        $mill = Mill::factory()->create(['name' => 'Original', 'city' => 'Lahore', 'phone' => '111']);
        // Another employee edits online after this device downloaded version 1.
        $mill->update(['phone' => '222']);

        $stale = ['target' => $mill->uuid, 'base_version' => 1, 'fields' => ['name' => 'Original', 'city' => 'Lahore', 'phone' => '333']];
        $this->op('mill.update', $stale)->assertStatus(409)
            ->assertJson(['status' => 'conflict'])->assertJsonPath('server.record.phone', '222');
        $this->assertSame('222', $mill->fresh()->phone);

        // User chooses "apply my changes".
        $this->op('mill.update', $stale + ['force' => true])->assertOk();
        $this->assertSame('333', $mill->fresh()->phone);
    }

    public function test_conflicting_buyer_response_is_reported_and_can_be_overridden(): void
    {
        $dispatch = SampleDispatch::factory()->create();
        $dispatch->forceFill(['status' => DispatchStatus::Rejected, 'rejection_reason' => 'Price', 'rejection_date' => now()])->save(); // version 2

        $mine = ['target' => $dispatch->uuid, 'base_version' => 1, 'fields' => [
            'status' => 'confirmed', 'confirmed_quantity' => 50, 'confirmed_quantity_unit' => 'ton', 'rate' => 1000,
            'rate_unit' => 'per_ton', 'packing_type' => '50 kg', 'confirmation_date' => now()->toDateString(),
        ]];
        $this->op('dispatch.respond', $mine)->assertStatus(409)->assertJsonPath('server.record.status', 'rejected');

        $this->op('dispatch.respond', $mine + ['force' => true])->assertOk();
        $this->assertSame(DispatchStatus::Confirmed, $dispatch->fresh()->status);
        $this->assertDatabaseHas('activities', ['subject_type' => 'dispatch', 'subject_id' => $dispatch->id, 'event' => 'reopened']);
    }

    public function test_rejected_or_pending_dispatch_cannot_be_loaded_through_sync(): void
    {
        foreach ([SampleDispatch::factory()->rejected()->create(), SampleDispatch::factory()->create()] as $dispatch) {
            $this->op('loading.create', ['uuid' => $this->uuid(), 'target' => $dispatch->uuid, 'fields' => [
                'truck_number' => 'ABC-1', 'loading_date' => now()->toDateString(), 'quantity' => 1, 'quantity_unit' => 'ton',
            ]])->assertForbidden()->assertJson(['status' => 'forbidden']);
        }
        $this->assertSame(0, Loading::count());
    }

    public function test_server_validation_applies_to_offline_changes(): void
    {
        Mill::factory()->create(['name' => 'Dup Mill', 'city' => 'Lahore']);

        $this->op('mill.create', ['uuid' => $this->uuid(), 'fields' => ['name' => 'Dup Mill', 'city' => 'Lahore']])
            ->assertStatus(422)->assertJson(['status' => 'rejected'])->assertJsonStructure(['errors' => ['name']]);
        $this->op('sample.create', ['uuid' => $this->uuid(), 'fields' => ['collection_date' => now()->addDay()->toDateString()]])
            ->assertStatus(422);
        $this->assertSame(1, Mill::count());
        $this->assertSame(0, SyncOperation::count()); // rejected ops are not logged, so they can be fixed and retried
    }

    public function test_reference_to_a_record_not_yet_on_the_server_is_reported(): void
    {
        $variety = RiceVariety::factory()->create();

        $this->op('sample.create', ['uuid' => $this->uuid(), 'fields' => [
            'mill_id' => $this->uuid(), 'rice_variety_id' => $variety->id, 'collection_date' => now()->toDateString(),
        ]])->assertStatus(424)->assertJson(['status' => 'missing_reference']);
    }

    public function test_operation_of_another_account_is_not_replayed(): void
    {
        $opUuid = $this->uuid();
        $this->op('mill.create', ['uuid' => $this->uuid(), 'fields' => ['name' => 'Mine', 'city' => 'Lahore']], $opUuid)->assertOk();

        $this->actingAs(User::factory()->employee()->create());
        $this->op('mill.create', ['uuid' => $this->uuid(), 'fields' => ['name' => 'Other', 'city' => 'Lahore']], $opUuid)
            ->assertStatus(422)->assertJson(['status' => 'rejected']);
        $this->assertSame(1, Mill::count());
    }

    public function test_malformed_operations_are_rejected(): void
    {
        $this->post(route('sync.operations'), ['operation' => '{bad json'], ['Accept' => 'application/json'])->assertStatus(422);
        $this->op('user.create', ['fields' => []])->assertStatus(422);
    }
}
