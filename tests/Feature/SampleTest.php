<?php

namespace Tests\Feature;

use App\Models\Mill;
use App\Models\RiceVariety;
use App\Models\Sample;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SampleTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->user = User::factory()->employee()->create();
    }

    public function test_sample_is_created_with_auto_number_and_linked_to_mill(): void
    {
        $mill = Mill::factory()->create(['city' => 'Hafizabad']);
        $variety = RiceVariety::factory()->create(['name' => '1121']);

        $this->actingAs($this->user)->post(route('samples.store'), [
            'mill_id' => $mill->id, 'rice_variety_id' => $variety->id,
            'collection_date' => now()->toDateString(), 'quantity' => 500, 'quantity_unit' => 'g',
        ])->assertSessionHasNoErrors();

        $sample = Sample::firstOrFail();
        $this->assertSame('SMP-0001', $sample->sample_number);
        $this->assertTrue($sample->mill->is($mill));
        $this->assertSame('Hafizabad', $sample->city());
        $this->assertSame($this->user->id, $sample->created_by);
        $this->assertDatabaseHas('activities', ['subject_type' => 'sample', 'subject_id' => $sample->id, 'event' => 'created']);
    }

    public function test_reference_numbers_are_sequential_and_skip_manual_numbers(): void
    {
        $mill = Mill::factory()->create();
        $variety = RiceVariety::factory()->create();
        $payload = ['mill_id' => $mill->id, 'rice_variety_id' => $variety->id, 'collection_date' => now()->toDateString()];

        $this->actingAs($this->user)->post(route('samples.store'), $payload + ['sample_number' => 'smp-0002']);
        $this->post(route('samples.store'), $payload);
        $this->post(route('samples.store'), $payload);

        $this->assertEqualsCanonicalizing(['SMP-0002', 'SMP-0001', 'SMP-0003'], Sample::pluck('sample_number')->all());
    }

    public function test_duplicate_sample_number_is_rejected(): void
    {
        $existing = Sample::factory()->create();

        $this->actingAs($this->user)->post(route('samples.store'), [
            'sample_number' => $existing->sample_number, 'mill_id' => $existing->mill_id,
            'rice_variety_id' => $existing->rice_variety_id, 'collection_date' => now()->toDateString(),
        ])->assertSessionHasErrors('sample_number');
    }

    public function test_sample_requires_mill_variety_and_valid_date(): void
    {
        $this->actingAs($this->user)->post(route('samples.store'), [
            'collection_date' => now()->addDays(3)->toDateString(), 'quantity' => 10,
        ])->assertSessionHasErrors(['mill_id', 'rice_variety_id', 'collection_date', 'quantity_unit']);

        $this->assertSame(0, Sample::count());
    }

    public function test_sample_photo_is_stored_privately_and_served_to_signed_in_users_only(): void
    {
        $mill = Mill::factory()->create();
        $variety = RiceVariety::factory()->create();

        $this->actingAs($this->user)->post(route('samples.store'), [
            'mill_id' => $mill->id, 'rice_variety_id' => $variety->id, 'collection_date' => now()->toDateString(),
            'photo' => UploadedFile::fake()->image('rice.jpg', 1200, 900),
        ])->assertSessionHasNoErrors();

        $sample = Sample::firstOrFail();
        $this->assertNotNull($sample->photo_path);
        Storage::disk('local')->assertExists($sample->photo_path);
        $this->assertStringStartsWith('samples/', $sample->photo_path);

        $this->get(route('samples.photo', $sample))->assertOk();
        $this->post(route('logout'));
        $this->get(route('samples.photo', $sample))->assertRedirect(route('login'));
    }

    public function test_non_image_uploads_are_rejected(): void
    {
        $mill = Mill::factory()->create();
        $variety = RiceVariety::factory()->create();

        foreach ([UploadedFile::fake()->create('shell.php', 10, 'application/x-php'), UploadedFile::fake()->create('x.svg', 5, 'image/svg+xml')] as $file) {
            $this->actingAs($this->user)->post(route('samples.store'), [
                'mill_id' => $mill->id, 'rice_variety_id' => $variety->id, 'collection_date' => now()->toDateString(), 'photo' => $file,
            ])->assertSessionHasErrors('photo');
        }

        $this->assertSame(0, Sample::count());
    }

    public function test_replacing_photo_removes_old_file(): void
    {
        $mill = Mill::factory()->create();
        $variety = RiceVariety::factory()->create();
        $this->actingAs($this->user)->post(route('samples.store'), [
            'mill_id' => $mill->id, 'rice_variety_id' => $variety->id, 'collection_date' => now()->toDateString(),
            'photo' => UploadedFile::fake()->image('one.jpg'),
        ]);
        $sample = Sample::firstOrFail();
        $old = $sample->photo_path;

        $this->put(route('samples.update', $sample), [
            'sample_number' => $sample->sample_number, 'mill_id' => $mill->id, 'rice_variety_id' => $variety->id,
            'collection_date' => now()->toDateString(), 'photo' => UploadedFile::fake()->image('two.jpg'),
        ])->assertSessionHasNoErrors();

        Storage::disk('local')->assertMissing($old);
        Storage::disk('local')->assertExists($sample->fresh()->photo_path);
    }

    public function test_samples_appear_in_their_mills_history(): void
    {
        $millA = Mill::factory()->create(['name' => 'ABC Rice Mill']);
        $millB = Mill::factory()->create(['name' => 'XYZ Rice Mill']);
        $first = Sample::factory()->for($millA)->create(['collection_date' => now()->subWeek()]);
        $second = Sample::factory()->for($millA)->create(['collection_date' => now()]);
        $other = Sample::factory()->for($millB)->create();

        $this->actingAs($this->user)->get(route('mills.show', $millA))
            ->assertOk()
            ->assertSee($first->sample_number)
            ->assertSee($second->sample_number)
            ->assertDontSee($other->sample_number);

        $this->assertSame(1, Mill::where('name', 'ABC Rice Mill')->count());
    }

    public function test_samples_can_be_filtered(): void
    {
        $v1121 = RiceVariety::factory()->create(['name' => '1121']);
        $v1509 = RiceVariety::factory()->create(['name' => '1509']);
        $a = Sample::factory()->create(['rice_variety_id' => $v1121->id]);
        $b = Sample::factory()->create(['rice_variety_id' => $v1509->id]);

        $this->actingAs($this->user)->get(route('samples.index', ['variety_id' => $v1509->id]))
            ->assertSee($b->sample_number)->assertDontSee($a->sample_number);
        $this->get(route('samples.index', ['q' => $a->mill->name]))
            ->assertSee($a->sample_number)->assertDontSee($b->sample_number);
    }

    public function test_mill_with_samples_cannot_be_deleted(): void
    {
        $sample = Sample::factory()->create();

        $this->actingAs(User::factory()->admin()->create())->delete(route('mills.destroy', $sample->mill))->assertForbidden();
        $this->assertModelExists($sample->mill);
    }

    public function test_only_admin_can_delete_undispatched_sample(): void
    {
        $sample = Sample::factory()->create();

        $this->actingAs($this->user)->delete(route('samples.destroy', $sample))->assertForbidden();
        $this->actingAs(User::factory()->admin()->create())->delete(route('samples.destroy', $sample))->assertRedirect(route('samples.index'));
        $this->assertModelMissing($sample);
    }

    public function test_sample_pages_render(): void
    {
        $sample = Sample::factory()->create();

        $this->actingAs($this->user);
        $this->get(route('samples.index'))->assertOk()->assertSee($sample->sample_number);
        $this->get(route('samples.create'))->assertOk();
        $this->get(route('samples.show', $sample))->assertOk()->assertSee($sample->mill->name);
        $this->get(route('samples.edit', $sample))->assertOk();
    }
}
