<?php

namespace Tests\Feature\Sync;

use App\Models\Loading;
use App\Models\Mill;
use App\Models\Sample;
use App\Models\SampleDispatch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class SyncableTest extends TestCase
{
    use RefreshDatabase;

    public function test_records_get_a_uuid_and_keep_a_given_one(): void
    {
        $mill = Mill::factory()->create();
        $this->assertTrue(Str::isUuid($mill->uuid));

        $uuid = (string) Str::uuid();
        $this->assertSame($uuid, Mill::factory()->create(['uuid' => $uuid])->uuid);

        foreach ([Sample::factory()->create(), SampleDispatch::factory()->create(), Loading::factory()->create()] as $model) {
            $this->assertTrue(Str::isUuid($model->uuid), $model::class);
        }
    }

    public function test_version_increments_only_on_real_changes(): void
    {
        $mill = Mill::factory()->create();
        $this->assertSame(1, (int) $mill->fresh()->version);

        $mill->update(['notes' => 'changed']);
        $this->assertSame(2, (int) $mill->fresh()->version);

        $mill->save(); // nothing dirty
        $this->assertSame(2, (int) $mill->fresh()->version);
    }
}
