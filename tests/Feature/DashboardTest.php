<?php

namespace Tests\Feature;

use App\Enums\LoadingStatus;
use App\Models\Buyer;
use App\Models\Loading;
use App\Models\Mill;
use App\Models\Sample;
use App\Models\SampleDispatch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_shows_correct_statistics(): void
    {
        Mill::factory()->count(2)->create();
        Buyer::factory()->count(3)->create();
        Sample::factory()->count(4)->create();
        SampleDispatch::factory()->count(2)->create();
        SampleDispatch::factory()->rejected()->create();
        SampleDispatch::factory()->confirmed()->create();
        $done = Loading::factory()->loaded()->create()->dispatch;
        $done->forceFill(['loading_status' => LoadingStatus::Completed])->save();

        $response = $this->actingAs(User::factory()->employee()->create())->get(route('dashboard'))->assertOk();

        $stats = $response->viewData('stats');
        $this->assertSame(2, $stats['pending']);
        $this->assertSame(1, $stats['rejected']);
        $this->assertSame(2, $stats['confirmed']);
        $this->assertSame(1, $stats['loading_pending']);
        $this->assertSame(1, $stats['loading_completed']);
        $this->assertSame(5, $stats['dispatches']);
        $this->assertSame(Mill::count(), $stats['mills']);
        $this->assertSame(Buyer::count(), $stats['buyers']);
        $this->assertSame(Sample::count(), $stats['samples']);
    }

    public function test_dashboard_lists_recent_activity_with_links(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post(route('mills.store'), ['name' => 'Feed Mill', 'city' => 'Kamoke']);

        $this->get(route('dashboard'))->assertOk()
            ->assertSee('Added rice mill Feed Mill, Kamoke')
            ->assertSee(route('mills.show', Mill::first()), false);
    }

    public function test_empty_dashboard_renders(): void
    {
        $this->actingAs(User::factory()->create())->get(route('dashboard'))->assertOk()->assertSee('No responses pending');
    }
}
