<?php

namespace Tests\Feature;

use App\Models\Buyer;
use App\Models\Loading;
use App\Models\Mill;
use App\Models\Sample;
use App\Models\SampleDispatch;
use App\Models\User;
use App\Support\PhotoStorage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class PdfExportTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    /** Data handed to the most recently rendered PDF view. */
    private array $pdfData = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->employee()->create();
        View::creator('pdf.*', fn ($view) => $this->pdfData = $view->getData());
    }

    private function assertPdf(TestResponse $response, string $filePrefix): void
    {
        $response->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $response->getContent());
        $this->assertStringContainsString('attachment; filename="'.$filePrefix.'-'.now()->format('Y-m-d').'.pdf"', $response->headers->get('Content-Disposition'));
    }

    public function test_records_pdf_contains_every_row_of_the_current_tab_and_filters(): void
    {
        $mill = Mill::factory()->create();
        $mine = SampleDispatch::factory()->confirmed()->count(30)->create(['sample_id' => Sample::factory()->create(['mill_id' => $mill->id])->id]);
        SampleDispatch::factory()->confirmed()->create(); // other mill
        SampleDispatch::factory()->rejected()->create(['sample_id' => $mine->first()->sample_id]); // other tab

        $response = $this->actingAs($this->user)->get(route('records.pdf', ['type' => 'confirmations', 'mill_id' => $mill->id]));

        $this->assertPdf($response, 'records-confirmations');
        $this->assertSame('Confirmed Orders', $this->pdfData['title']);
        $this->assertCount(30, $this->pdfData['records']); // not limited to one page of 25
        $this->assertEqualsCanonicalizing($mine->modelKeys(), $this->pdfData['records']->modelKeys());
        $this->assertSame($mill->label(), $this->pdfData['criteria']['Mill']);
        $this->assertSame('30', $this->pdfData['summary']['Confirmed']);
    }

    public function test_records_pdf_of_truck_loadings(): void
    {
        $loading = Loading::factory()->create(['quantity' => 120, 'quantity_unit' => 'maund', 'bag_count' => 240]);

        $response = $this->actingAs($this->user)->get(route('records.pdf', ['type' => 'loadings']));

        $this->assertPdf($response, 'records-loadings');
        $this->assertSame([$loading->id], $this->pdfData['records']->modelKeys());
        $this->assertSame('120 maund', $this->pdfData['summary']['Loaded']);
        $this->assertSame('240', $this->pdfData['summary']['Bags']);
    }

    public function test_buyer_pdf_shows_either_samples_sent_or_truck_loadings_with_photos(): void
    {
        Storage::fake(PhotoStorage::DISK);
        $buyer = Buyer::factory()->create(['name' => 'A.A Broker']);
        $confirmed = SampleDispatch::factory()->confirmed()->create(['buyer_id' => $buyer->id]);
        $pending = SampleDispatch::factory()->create(['buyer_id' => $buyer->id]);
        SampleDispatch::factory()->create(); // another buyer
        $truck = Loading::factory()->create(['sample_dispatch_id' => $confirmed->id, 'quantity' => 300, 'quantity_unit' => 'maund', 'bag_count' => 480]);
        Storage::disk(PhotoStorage::DISK)->put('loadings/a.jpg', UploadedFile::fake()->image('a.jpg', 900, 600)->getContent());
        $truck->photos()->create(['path' => 'loadings/a.jpg', 'original_name' => 'a.jpg', 'mime' => 'image/jpeg', 'size' => 1000]);

        // Samples sent (the default)
        $response = $this->actingAs($this->user)->get(route('buyers.pdf', [$buyer, 'part' => 'samples']));
        $this->assertPdf($response, 'buyer-aa-broker-samples');
        $this->assertSame('Samples Sent', $this->pdfData['title']);
        $this->assertSame('samples', $this->pdfData['part']);
        $this->assertEqualsCanonicalizing([$confirmed->id, $pending->id], $this->pdfData['dispatches']->modelKeys());
        $this->assertSame('2', $this->pdfData['summary']['Samples sent']);

        // Only the sample number, no mill or city under it.
        $html = view('pdf.buyer', $this->pdfData)->render();
        $this->assertStringContainsString($confirmed->sample->sample_number, $html);
        $this->assertStringNotContainsString($confirmed->sample->mill->name, $html);

        // Truck loadings with their photos
        $response = $this->actingAs($this->user)->get(route('buyers.pdf', [$buyer, 'part' => 'loadings']));
        $this->assertPdf($response, 'buyer-aa-broker-trucks');
        $this->assertSame('Truck Loadings', $this->pdfData['title']);
        $this->assertSame([$truck->id], $this->pdfData['loadings']->modelKeys());
        $this->assertSame(['Trucks' => '1', 'Loaded' => '300 maund', 'Bags' => '480', 'Orders' => '1', 'Photos' => '1'], $this->pdfData['summary']);
        $html = view('pdf.buyer', $this->pdfData)->render();
        $this->assertStringContainsString('Truck photos', $html);
        $this->assertStringContainsString('src="data:image/jpeg;base64,', $html);
        $this->assertStringNotContainsString($confirmed->sample->mill->name, $html);

        // The status tab on the buyer page carries over to the PDF.
        $this->actingAs($this->user)->get(route('buyers.pdf', [$buyer, 'status' => 'pending']))->assertOk();
        $this->assertSame([$pending->id], $this->pdfData['dispatches']->modelKeys());
        $this->assertSame('Awaiting response', $this->pdfData['statusLabel']);
    }

    public function test_mill_pdf_shows_either_samples_or_buyer_responses(): void
    {
        $mill = Mill::factory()->create(['name' => 'Safa Rice Mills']);
        $samples = Sample::factory()->count(2)->create(['mill_id' => $mill->id]);
        Sample::factory()->create(); // another mill
        $dispatch = SampleDispatch::factory()->confirmed()->create(['sample_id' => $samples->first()->id]);

        $this->assertPdf($this->actingAs($this->user)->get(route('mills.pdf', [$mill, 'part' => 'samples'])), 'mill-safa-rice-mills-samples');
        $this->assertSame('Mill Samples', $this->pdfData['title']);
        $this->assertEqualsCanonicalizing($samples->modelKeys(), $this->pdfData['samples']->modelKeys());
        $this->assertSame(['Samples' => '2', 'Varieties' => (string) $samples->pluck('rice_variety_id')->unique()->count(), 'Sent to buyers' => '1', 'Not sent yet' => '1'], $this->pdfData['summary']);

        $this->assertPdf($this->actingAs($this->user)->get(route('mills.pdf', [$mill, 'part' => 'responses'])), 'mill-safa-rice-mills-responses');
        $this->assertSame('Buyer Responses', $this->pdfData['title']);
        $this->assertSame([$dispatch->id], $this->pdfData['dispatches']->modelKeys());
        $this->assertSame('1', $this->pdfData['summary']['Confirmed']);
    }

    public function test_samples_pdf_follows_the_list_filters(): void
    {
        $mill = Mill::factory()->create();
        $sent = Sample::factory()->create(['mill_id' => $mill->id]);
        $notSent = Sample::factory()->create(['mill_id' => $mill->id]);
        Sample::factory()->create(); // another mill
        SampleDispatch::factory()->create(['sample_id' => $sent->id]);

        $this->assertPdf($this->actingAs($this->user)->get(route('samples.pdf', ['mill_id' => $mill->id])), 'samples');
        $this->assertSame('Sample Register', $this->pdfData['title']);
        $this->assertEqualsCanonicalizing([$sent->id, $notSent->id], $this->pdfData['samples']->modelKeys());
        $this->assertSame(['Samples' => '2', 'Mills' => '1', 'Varieties' => (string) collect([$sent, $notSent])->pluck('rice_variety_id')->unique()->count(), 'Sent to buyers' => '1', 'Not sent yet' => '1'], $this->pdfData['summary']);

        $this->actingAs($this->user)->get(route('samples.pdf', ['mill_id' => $mill->id, 'sent' => 'no']))->assertOk();
        $this->assertSame([$notSent->id], $this->pdfData['samples']->modelKeys());
        $this->assertSame('Not sent yet', $this->pdfData['criteria']['Showing']);
    }

    public function test_dispatches_pdf_follows_the_tab_and_dispatch_number_range(): void
    {
        foreach (['DSP-0003', 'DSP-0005', 'DSP-0009', 'DSP-0012'] as $number) {
            SampleDispatch::factory()->create(['dispatch_number' => $number]);
        }
        SampleDispatch::factory()->rejected()->create(['dispatch_number' => 'DSP-0006']); // other tab

        $response = $this->actingAs($this->user)->get(route('dispatches.pdf', ['status' => 'pending', 'from_no' => '5', 'to_no' => 'DSP-0010']));

        $this->assertPdf($response, 'dispatches');
        $this->assertSame('Dispatch Register', $this->pdfData['title']);
        $this->assertSame(['DSP-0009', 'DSP-0005'], $this->pdfData['records']->pluck('dispatch_number')->sort()->reverse()->values()->all());
        $this->assertSame('Awaiting response', $this->pdfData['criteria']['List']);
        $this->assertSame('DSP-0005 – DSP-0010', $this->pdfData['criteria']['Dispatch no.']);
        $this->assertSame('2', $this->pdfData['summary']['Records']);
    }

    public function test_samples_list_and_pdf_use_a_sample_number_range(): void
    {
        foreach (['SMP-0255', 'SMP-0260', 'SMP-0999', 'SMP-1000', 'SMP-10000'] as $number) {
            Sample::factory()->create(['sample_number' => $number]);
        }

        // Numbers compare as numbers (SMP-0999 < SMP-1000 < SMP-10000), typed with or without the prefix.
        $this->actingAs($this->user)->get(route('samples.pdf', ['from_no' => '260', 'to_no' => 'smp-1000']))->assertOk();
        $this->assertEqualsCanonicalizing(['SMP-0260', 'SMP-0999', 'SMP-1000'], $this->pdfData['samples']->pluck('sample_number')->all());
        $this->assertSame('SMP-0260 – SMP-1000', $this->pdfData['criteria']['Sample no.']);

        $this->actingAs($this->user)->get(route('samples.pdf', ['from_no' => '1000']))->assertOk();
        $this->assertEqualsCanonicalizing(['SMP-1000', 'SMP-10000'], $this->pdfData['samples']->pluck('sample_number')->all());
        $this->assertSame('from SMP-1000', $this->pdfData['criteria']['Sample no.']);

        $this->actingAs($this->user)->get(route('samples.index', ['to_no' => '0260']))->assertOk()
            ->assertSee('SMP-0255')->assertSee('SMP-0260')->assertDontSee('SMP-0999')
            ->assertSee(e(route('samples.pdf', ['to_no' => '0260'])), false);
    }

    public function test_records_pdf_prints_only_the_chosen_dates(): void
    {
        $rejected = SampleDispatch::factory()->rejected()->create(['rejection_date' => '2026-11-03']);
        SampleDispatch::factory()->rejected()->create(['rejection_date' => '2026-10-03']);
        $this->actingAs($this->user)->get(route('records.pdf', ['type' => 'rejections', 'from' => '2026-11-01', 'to' => '2026-11-30']))->assertOk();
        $this->assertSame([$rejected->id], $this->pdfData['records']->modelKeys());
        $this->assertSame('01 Nov 2026 – 30 Nov 2026', $this->pdfData['criteria']['Rejection date']);
    }

    public function test_pages_offer_the_export_button(): void
    {
        $buyer = Buyer::factory()->create();
        $mill = Mill::factory()->create();

        $this->actingAs($this->user)->get(route('records.index', ['type' => 'rejections']))->assertSee(route('records.pdf', ['type' => 'rejections']), false);
        $this->actingAs($this->user)->get(route('buyers.show', $buyer))
            ->assertSee(e(route('buyers.pdf', ['buyer' => $buyer, 'part' => 'samples'])), false)
            ->assertSee(e(route('buyers.pdf', ['buyer' => $buyer, 'part' => 'loadings'])), false);
        $this->actingAs($this->user)->get(route('mills.show', $mill))
            ->assertSee(e(route('mills.pdf', ['mill' => $mill, 'part' => 'samples'])), false)
            ->assertSee(e(route('mills.pdf', ['mill' => $mill, 'part' => 'responses'])), false);
        $this->actingAs($this->user)->get(route('samples.index', ['sent' => 'no']))->assertSee(route('samples.pdf', ['sent' => 'no']), false);
        $this->actingAs($this->user)->get(route('dispatches.index', ['status' => 'pending', 'from_no' => '5']))
            ->assertSee(e(route('dispatches.pdf', ['status' => 'pending', 'from_no' => '5'])), false)
            ->assertSee('name="from_no"', false)->assertSee('name="to_no"', false)->assertDontSee('type="date" name="from"', false);
    }

    public function test_guests_cannot_export(): void
    {
        $this->get(route('records.pdf'))->assertRedirect(route('login'));
        $this->get(route('buyers.pdf', Buyer::factory()->create()))->assertRedirect(route('login'));
        $this->get(route('mills.pdf', Mill::factory()->create()))->assertRedirect(route('login'));
        $this->get(route('samples.pdf'))->assertRedirect(route('login'));
        $this->get(route('dispatches.pdf'))->assertRedirect(route('login'));
    }
}
