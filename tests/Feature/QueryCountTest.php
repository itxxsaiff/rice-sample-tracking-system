<?php

namespace Tests\Feature;

use App\Models\Loading;
use App\Models\SampleBundle;
use App\Models\Sample;
use App\Models\SampleDispatch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Guards against N+1 queries: list pages must use a bounded number of
 * queries regardless of how many rows they show. (Lazy loading also throws
 * outside production, so any missing eager load fails these tests.)
 */
class QueryCountTest extends TestCase
{
    use RefreshDatabase;

    public function test_list_pages_do_not_issue_per_row_queries(): void
    {
        $bundle = SampleBundle::factory()->create();
        Sample::factory()->count(6)->create(['sample_bundle_id' => $bundle->id]);
        SampleDispatch::factory()->count(4)->create();
        SampleDispatch::factory()->count(4)->confirmed()->create();
        SampleDispatch::factory()->count(3)->rejected()->create();
        Loading::factory()->count(4)->create();
        $buyer = SampleDispatch::first()->buyer;
        $mill = Sample::first()->mill;

        $this->actingAs(User::factory()->admin()->create());

        $pages = [
            route('dashboard'), route('samples.index'), route('bundles.index'), route('bundles.show', $bundle),
            route('dispatches.index'), route('loading.index'), route('loading.index', ['tab' => 'in_progress']),
            route('records.index'), route('records.index', ['type' => 'loadings']), route('buyers.show', $buyer),
            route('mills.show', $mill), route('mills.index'), route('buyers.index'), route('search', ['q' => 'a']),
        ];

        foreach ($pages as $url) {
            DB::enableQueryLog();
            DB::flushQueryLog();
            $this->get($url)->assertOk();
            $count = count(DB::getQueryLog());
            $this->assertLessThan(30, $count, "{$url} ran {$count} queries");
        }
    }
}
