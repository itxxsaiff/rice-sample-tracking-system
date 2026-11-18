<?php

namespace Tests\Feature;

use App\Enums\LoadingStatus;
use App\Models\Loading;
use App\Models\Mill;
use App\Models\Sample;
use App\Models\SampleDispatch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecordsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->employee()->create();
    }

    public function test_records_tabs_show_the_right_records(): void
    {
        $pending = SampleDispatch::factory()->create();
        $confirmed = SampleDispatch::factory()->confirmed()->create();
        $rejected = SampleDispatch::factory()->rejected()->create();
        $loading = Loading::factory()->loaded()->create(['truck_number' => 'TRK-9988']);
        $completed = $loading->dispatch;
        $completed->forceFill(['loading_status' => LoadingStatus::Completed, 'loading_completed_at' => now()])->save();

        $this->actingAs($this->user);
        $this->get(route('records.index'))->assertOk()
            ->assertSee($pending->dispatch_number)->assertSee($confirmed->dispatch_number)->assertSee($rejected->dispatch_number);
        $this->get(route('records.index', ['type' => 'confirmations']))
            ->assertSee($confirmed->dispatch_number)->assertSee($completed->dispatch_number)->assertDontSee($rejected->dispatch_number)->assertDontSee($pending->dispatch_number);
        $this->get(route('records.index', ['type' => 'rejections']))
            ->assertSee($rejected->dispatch_number)->assertDontSee($confirmed->dispatch_number);
        $this->get(route('records.index', ['type' => 'completed']))
            ->assertSee($completed->dispatch_number)->assertDontSee($confirmed->dispatch_number);
        $this->get(route('records.index', ['type' => 'loadings']))->assertSee('TRK-9988');
    }

    public function test_records_filter_by_mill_and_date(): void
    {
        $mill = Mill::factory()->create();
        $mine = SampleDispatch::factory()->for(Sample::factory()->for($mill))->create(['dispatch_date' => now()->subDays(10)]);
        $other = SampleDispatch::factory()->create(['dispatch_date' => now()]);

        $this->actingAs($this->user)->get(route('records.index', ['mill_id' => $mill->id]))
            ->assertSee($mine->dispatch_number)->assertDontSee($other->dispatch_number);
        $this->get(route('records.index', ['from' => now()->subDay()->toDateString()]))
            ->assertSee($other->dispatch_number)->assertDontSee($mine->dispatch_number);
    }

    public function test_global_search_finds_records_of_every_type(): void
    {
        $mill = Mill::factory()->create(['name' => 'Zarghoon Rice Mill']);
        $sample = Sample::factory()->for($mill)->create(['sample_number' => 'SMP-7777']);
        $loading = Loading::factory()->create(['truck_number' => 'ZAR-1234']);

        $this->actingAs($this->user);
        $this->get(route('search', ['q' => 'Zarghoon']))->assertOk()->assertSee('Zarghoon Rice Mill')->assertSee('SMP-7777');
        $this->get(route('search', ['q' => 'ZAR-12']))->assertSee('ZAR-1234');
        $this->get(route('search', ['q' => 'nothing-matches-this']))->assertSee('No results');
    }
}
