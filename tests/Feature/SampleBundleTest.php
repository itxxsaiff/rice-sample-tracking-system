<?php

namespace Tests\Feature;

use App\Models\Mill;
use App\Models\RiceVariety;
use App\Models\Sample;
use App\Models\SampleBundle;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SampleBundleTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->user = User::factory()->employee()->create();
    }

    public function test_bundle_can_contain_samples_from_different_mills(): void
    {
        $millA = Mill::factory()->create();
        $millB = Mill::factory()->create();
        $variety = RiceVariety::factory()->create();
        $row = fn (Mill $mill) => ['mill_id' => $mill->id, 'rice_variety_id' => $variety->id, 'quantity' => 500, 'quantity_unit' => 'g'];

        $this->actingAs($this->user)->post(route('bundles.store'), [
            'bundle_date' => now()->toDateString(),
            // Non-sequential keys, as produced after removing rows in the builder.
            'samples' => [0 => $row($millA), 2 => $row($millA), 3 => $row($millB), 7 => $row($millB), 9 => $row($millB) + ['photo' => UploadedFile::fake()->image('s.jpg')]],
        ])->assertSessionHasNoErrors();

        $bundle = SampleBundle::firstOrFail();
        $this->assertSame('BND-0001', $bundle->bundle_number);
        $this->assertCount(5, $bundle->samples);
        $this->assertSame(2, $millA->samples()->where('sample_bundle_id', $bundle->id)->count());
        $this->assertSame(3, $millB->samples()->where('sample_bundle_id', $bundle->id)->count());
        $this->assertSame(['SMP-0001', 'SMP-0002', 'SMP-0003', 'SMP-0004', 'SMP-0005'], $bundle->samples->pluck('sample_number')->all());
        $this->assertNotNull($bundle->samples->last()->photo_path);
        $this->assertTrue($bundle->samples->every(fn ($s) => $s->collection_date->isToday()));

        // Each sample shows up under its own mill's history.
        $this->get(route('mills.show', $millA))->assertSee('SMP-0001')->assertSee('SMP-0002')->assertDontSee('SMP-0003');
        $this->get(route('mills.show', $millB))->assertSee('SMP-0005')->assertDontSee('SMP-0001');
        $this->get(route('bundles.show', $bundle))->assertOk()->assertSee($millA->name)->assertSee($millB->name);
    }

    public function test_invalid_row_rolls_back_the_whole_bundle(): void
    {
        $mill = Mill::factory()->create();
        $variety = RiceVariety::factory()->create();

        $this->actingAs($this->user)->post(route('bundles.store'), [
            'bundle_date' => now()->toDateString(),
            'samples' => [
                0 => ['mill_id' => $mill->id, 'rice_variety_id' => $variety->id],
                1 => ['rice_variety_id' => $variety->id],
            ],
        ])->assertSessionHasErrors('samples.1.mill_id');

        $this->assertSame(0, SampleBundle::count());
        $this->assertSame(0, Sample::count());
    }

    public function test_bundle_requires_at_least_one_sample_and_unique_numbers(): void
    {
        $mill = Mill::factory()->create();
        $variety = RiceVariety::factory()->create();

        $this->actingAs($this->user)->post(route('bundles.store'), ['bundle_date' => now()->toDateString(), 'samples' => []])
            ->assertSessionHasErrors('samples');

        $row = ['mill_id' => $mill->id, 'rice_variety_id' => $variety->id, 'sample_number' => 'A-1'];
        $this->post(route('bundles.store'), ['bundle_date' => now()->toDateString(), 'samples' => [$row, $row]])
            ->assertSessionHasErrors(['samples.0.sample_number', 'samples.1.sample_number']);
    }

    public function test_samples_can_be_added_to_an_existing_bundle(): void
    {
        $bundle = SampleBundle::factory()->create(['bundle_date' => now()->subDays(2)]);
        Sample::factory()->create(['sample_bundle_id' => $bundle->id]);
        $mill = Mill::factory()->create();
        $variety = RiceVariety::factory()->create();

        $this->actingAs($this->user)->post(route('bundles.samples.store', $bundle), [
            'samples' => [['mill_id' => $mill->id, 'rice_variety_id' => $variety->id]],
        ])->assertRedirect(route('bundles.show', $bundle));

        $this->assertCount(2, $bundle->fresh()->samples);
        $this->assertTrue($bundle->samples()->reorder()->latest('id')->first()->collection_date->isSameDay($bundle->bundle_date));
    }

    public function test_single_sample_can_be_attached_to_bundle(): void
    {
        $bundle = SampleBundle::factory()->create();
        $mill = Mill::factory()->create();
        $variety = RiceVariety::factory()->create();

        $this->actingAs($this->user)->post(route('samples.store'), [
            'mill_id' => $mill->id, 'rice_variety_id' => $variety->id, 'collection_date' => now()->toDateString(), 'sample_bundle_id' => $bundle->id,
        ]);

        $this->assertSame($bundle->id, Sample::firstOrFail()->sample_bundle_id);
    }

    public function test_bundle_with_samples_cannot_be_deleted(): void
    {
        $bundle = SampleBundle::factory()->create();
        Sample::factory()->create(['sample_bundle_id' => $bundle->id]);

        $this->actingAs(User::factory()->admin()->create())->delete(route('bundles.destroy', $bundle))->assertForbidden();
    }

    public function test_bundle_pages_render_including_old_input(): void
    {
        $bundle = SampleBundle::factory()->create();
        $mill = Mill::factory()->create(['name' => 'Remembered Mill']);

        $this->actingAs($this->user)->get(route('bundles.index'))->assertOk()->assertSee($bundle->bundle_number);
        $this->get(route('bundles.create'))->assertOk()->assertSee('Add another sample');
        $this->get(route('bundles.edit', $bundle))->assertOk();
        $this->get(route('bundles.samples.create', $bundle))->assertOk();

        // After a validation error the builder re-renders submitted rows with mill names.
        $this->from(route('bundles.create'))->post(route('bundles.store'), [
            'bundle_date' => now()->toDateString(), 'samples' => [4 => ['mill_id' => $mill->id]],
        ]);
        $this->get(route('bundles.create'))->assertSee('Remembered Mill')->assertSee('samples[4][mill_id]', false);
    }
}
