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

    private function row(Mill $mill, RiceVariety $variety, array $extra = []): array
    {
        return ['mill_id' => $mill->id, 'rice_variety_id' => $variety->id, 'collection_date' => now()->toDateString(), ...$extra];
    }

    public function test_sample_is_created_with_auto_number_and_linked_to_mill(): void
    {
        $mill = Mill::factory()->create(['city' => 'Hafizabad']);
        $variety = RiceVariety::factory()->create(['name' => '1121']);

        $this->actingAs($this->user)->post(route('samples.store'), [
            'samples' => [$this->row($mill, $variety, ['quantity' => 5, 'quantity_unit' => 'kg'])],
        ])->assertSessionHasNoErrors();

        $sample = Sample::firstOrFail();
        $this->assertSame('SMP-0001', $sample->sample_number);
        $this->assertTrue($sample->mill->is($mill));
        $this->assertSame('Hafizabad', $sample->city());
        $this->assertSame('kg', $sample->quantity_unit);
        $this->assertSame($this->user->id, $sample->created_by);
        $this->assertDatabaseHas('activities', ['subject_type' => 'sample', 'subject_id' => $sample->id, 'event' => 'created']);
    }

    public function test_several_independent_samples_are_saved_from_one_form(): void
    {
        $millA = Mill::factory()->create();
        $millB = Mill::factory()->create();
        $v1121 = RiceVariety::factory()->create();
        $v1509 = RiceVariety::factory()->create();

        $this->actingAs($this->user)->post(route('samples.store'), ['samples' => [
            'r0' => $this->row($millA, $v1121, ['quantity' => 2, 'quantity_unit' => 'kg']),
            'r3' => $this->row($millB, $v1509, ['notes' => 'second']),
        ]])->assertRedirect(route('samples.index'))->assertSessionHas('success', '2 samples saved (SMP-0001 – SMP-0002).');

        $this->assertSame(['SMP-0001', 'SMP-0002'], Sample::orderBy('id')->pluck('sample_number')->all());
        $this->assertSame([$millA->id, $millB->id], Sample::orderBy('id')->pluck('mill_id')->all());
        $this->assertNull(Sample::where('mill_id', $millB->id)->value('quantity'));
    }

    public function test_one_invalid_row_saves_nothing(): void
    {
        $mill = Mill::factory()->create();
        $variety = RiceVariety::factory()->create();

        $this->actingAs($this->user)->post(route('samples.store'), ['samples' => [
            'r0' => $this->row($mill, $variety),
            'r1' => ['collection_date' => now()->addDays(3)->toDateString(), 'quantity' => 10],
        ]])->assertSessionHasErrors(['samples.r1.mill_id', 'samples.r1.rice_variety_id', 'samples.r1.collection_date', 'samples.r1.quantity_unit']);

        $this->assertSame(0, Sample::count());
    }

    public function test_sample_numbers_are_always_automatic(): void
    {
        $mill = Mill::factory()->create();
        $variety = RiceVariety::factory()->create();

        $this->actingAs($this->user)->post(route('samples.store'), ['samples' => [$this->row($mill, $variety, ['sample_number' => 'MY-OWN', 'photo' => UploadedFile::fake()->image('x.jpg')])]]);
        $this->post(route('samples.store'), ['samples' => [$this->row($mill, $variety)]]);

        $this->assertSame(['SMP-0001', 'SMP-0002'], Sample::orderBy('id')->pluck('sample_number')->all());
        $this->assertNull(Sample::first()->photo_path); // sample photos are no longer taken
        Storage::disk('local')->assertDirectoryEmpty('/');

        // Editing never changes the number either.
        $sample = Sample::first();
        $this->put(route('samples.update', $sample), [...$this->row($mill, $variety), 'sample_number' => 'CHANGED'])->assertSessionHasNoErrors();
        $this->assertSame('SMP-0001', $sample->fresh()->sample_number);
    }

    public function test_existing_sample_photo_is_served_to_signed_in_users_only(): void
    {
        Storage::disk('local')->put('samples/old.jpg', 'jpeg-bytes');
        $sample = Sample::factory()->create(['photo_path' => 'samples/old.jpg']);

        $this->actingAs($this->user)->get(route('samples.photo', $sample))->assertOk();
        $this->post(route('logout'));
        $this->get(route('samples.photo', $sample))->assertRedirect(route('login'));
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
        $this->get(route('samples.create'))->assertOk()->assertSee('Add another sample')
            ->assertDontSee('name="samples[0][sample_number]"', false)->assertDontSee('Take photo')->assertDontSee('bundle');
        $this->get(route('samples.show', $sample))->assertOk()->assertSee($sample->mill->name);
        $this->get(route('samples.edit', $sample))->assertOk();
    }
}
