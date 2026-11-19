<?php

namespace Tests\Feature\Sync;

use App\Models\Loading;
use App\Models\Mill;
use App\Models\Sample;
use App\Models\SampleDispatch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SnapshotTest extends TestCase
{
    use RefreshDatabase;

    public function test_status_reports_session_and_never_redirects(): void
    {
        $this->getJson(route('sync.status'))->assertOk()
            ->assertJson(['authenticated' => false, 'user' => null])
            ->assertJsonStructure(['csrf', 'server_time'])
            ->assertHeader('Cache-Control', 'no-store, private');

        $user = User::factory()->employee()->create();
        $this->actingAs($user)->getJson(route('sync.status'))
            ->assertJson(['authenticated' => true, 'user' => ['id' => $user->id, 'role' => 'employee']]);
    }

    public function test_snapshot_requires_authentication(): void
    {
        $this->getJson(route('sync.snapshot'))->assertUnauthorized();
    }

    public function test_snapshot_contains_records_linked_by_uuid(): void
    {
        $loading = Loading::factory()->create();
        $dispatch = $loading->dispatch;
        $sample = $dispatch->sample;

        $data = $this->actingAs(User::factory()->create())->getJson(route('sync.snapshot'))->assertOk()->json();

        $this->assertFalse($data['incremental']);
        $s = collect($data['samples'])->firstWhere('uuid', $sample->uuid);
        $this->assertSame($sample->mill->uuid, $s['mill_uuid']);
        $this->assertSame($sample->variety->uuid, $s['variety_uuid']);
        $d = collect($data['dispatches'])->firstWhere('uuid', $dispatch->uuid);
        $this->assertSame($sample->uuid, $d['sample_uuid']);
        $this->assertSame($dispatch->buyer->uuid, $d['buyer_uuid']);
        $this->assertSame('confirmed', $d['status']);
        $l = collect($data['loadings'])->firstWhere('uuid', $loading->uuid);
        $this->assertSame($dispatch->uuid, $l['dispatch_uuid']);
        $this->assertContains($loading->uuid, $data['keep']['loadings']);
        $this->assertArrayNotHasKey('photo_path', $s);
    }

    public function test_incremental_snapshot_only_returns_changes(): void
    {
        $old = Mill::factory()->create();
        $old->forceFill(['updated_at' => now()->subDays(2)])->saveQuietly();
        $new = Mill::factory()->create();

        $data = $this->actingAs(User::factory()->create())
            ->getJson(route('sync.snapshot', ['since' => now()->subHour()->toIso8601String()]))->json();

        $uuids = collect($data['mills'])->pluck('uuid');
        $this->assertTrue($data['incremental']);
        $this->assertContains($new->uuid, $uuids);
        $this->assertNotContains($old->uuid, $uuids);
        $this->assertContains($old->uuid, $data['keep']['mills']); // still exists, so the device keeps it
    }

    public function test_history_window_keeps_open_work_regardless_of_age(): void
    {
        config(['offline.history_days' => 30]);
        $oldClosed = SampleDispatch::factory()->rejected()->create(['dispatch_date' => now()->subDays(200)]);
        $oldClosed->sample->forceFill(['collection_date' => now()->subDays(210)])->saveQuietly();
        $oldOpen = SampleDispatch::factory()->create(['dispatch_date' => now()->subDays(200)]);
        $oldOpen->sample->forceFill(['collection_date' => now()->subDays(210)])->saveQuietly();

        $data = $this->actingAs(User::factory()->create())->getJson(route('sync.snapshot'))->json();
        $dispatchUuids = collect($data['dispatches'])->pluck('uuid');

        $this->assertContains($oldOpen->uuid, $dispatchUuids);
        $this->assertContains($oldOpen->sample->uuid, collect($data['samples'])->pluck('uuid'));
        $this->assertNotContains($oldClosed->uuid, $dispatchUuids);
    }

    public function test_deleted_records_drop_out_of_the_keep_list(): void
    {
        $sample = Sample::factory()->create();
        $uuid = $sample->uuid;
        $sample->delete();

        $data = $this->actingAs(User::factory()->create())->getJson(route('sync.snapshot'))->json();
        $this->assertNotContains($uuid, $data['keep']['samples']);
    }
}
