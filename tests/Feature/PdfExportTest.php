<?php

namespace Tests\Feature;

use App\Models\Buyer;
use App\Models\Loading;
use App\Models\Mill;
use App\Models\Sample;
use App\Models\SampleDispatch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

    public function test_buyer_pdf_lists_all_samples_sent_and_trucks_loaded(): void
    {
        $buyer = Buyer::factory()->create(['name' => 'A.A Broker']);
        $confirmed = SampleDispatch::factory()->confirmed()->create(['buyer_id' => $buyer->id]);
        $pending = SampleDispatch::factory()->create(['buyer_id' => $buyer->id]);
        SampleDispatch::factory()->create(); // another buyer
        $truck = Loading::factory()->create(['sample_dispatch_id' => $confirmed->id]);

        $this->assertPdf($this->actingAs($this->user)->get(route('buyers.pdf', $buyer)), 'buyer-aa-broker');
        $this->assertEqualsCanonicalizing([$confirmed->id, $pending->id], $this->pdfData['dispatches']->modelKeys());
        $this->assertSame([$truck->id], $this->pdfData['loadings']->modelKeys());
        $this->assertSame('2', $this->pdfData['summary']['Samples sent']);

        // The status tab on the buyer page carries over to the PDF.
        $this->actingAs($this->user)->get(route('buyers.pdf', [$buyer, 'status' => 'pending']))->assertOk();
        $this->assertSame([$pending->id], $this->pdfData['dispatches']->modelKeys());
        $this->assertSame('Awaiting response', $this->pdfData['statusLabel']);
    }

    public function test_mill_pdf_lists_all_samples_and_buyer_responses(): void
    {
        $mill = Mill::factory()->create(['name' => 'Safa Rice Mills']);
        $samples = Sample::factory()->count(2)->create(['mill_id' => $mill->id]);
        Sample::factory()->create(); // another mill
        $dispatch = SampleDispatch::factory()->confirmed()->create(['sample_id' => $samples->first()->id]);

        $this->assertPdf($this->actingAs($this->user)->get(route('mills.pdf', $mill)), 'mill-safa-rice-mills');
        $this->assertEqualsCanonicalizing($samples->modelKeys(), $this->pdfData['samples']->modelKeys());
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

    public function test_dispatches_pdf_follows_the_tab_filters_and_dates(): void
    {
        $inRange = SampleDispatch::factory()->create(['dispatch_date' => '2026-11-10']);
        SampleDispatch::factory()->create(['dispatch_date' => '2026-11-01']); // before the range
        SampleDispatch::factory()->rejected()->create(['dispatch_date' => '2026-11-12']); // other tab

        $response = $this->actingAs($this->user)->get(route('dispatches.pdf', ['status' => 'pending', 'from' => '2026-11-05', 'to' => '2026-11-15']));

        $this->assertPdf($response, 'dispatches');
        $this->assertSame('Dispatch Register', $this->pdfData['title']);
        $this->assertSame([$inRange->id], $this->pdfData['records']->modelKeys());
        $this->assertSame('Awaiting response', $this->pdfData['criteria']['List']);
        $this->assertSame('05 Nov 2026 – 15 Nov 2026', $this->pdfData['criteria']['Dispatch date']);
        $this->assertSame('1', $this->pdfData['summary']['Records']);
    }

    public function test_samples_and_records_pdfs_print_only_the_chosen_dates(): void
    {
        $old = Sample::factory()->create(['collection_date' => '2026-10-01']);
        $recent = Sample::factory()->create(['collection_date' => '2026-11-18']);

        $this->actingAs($this->user)->get(route('samples.pdf', ['from' => '2026-11-01']))->assertOk();
        $this->assertSame([$recent->id], $this->pdfData['samples']->modelKeys());
        $this->assertSame('from 01 Nov 2026', $this->pdfData['criteria']['Collected']);

        $this->actingAs($this->user)->get(route('samples.pdf', ['to' => '2026-10-31']))->assertOk();
        $this->assertSame([$old->id], $this->pdfData['samples']->modelKeys());
        $this->assertSame('up to 31 Oct 2026', $this->pdfData['criteria']['Collected']);

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
        $this->actingAs($this->user)->get(route('buyers.show', $buyer))->assertSee(route('buyers.pdf', $buyer), false);
        $this->actingAs($this->user)->get(route('mills.show', $mill))->assertSee(route('mills.pdf', $mill), false);
        $this->actingAs($this->user)->get(route('samples.index', ['sent' => 'no']))->assertSee(route('samples.pdf', ['sent' => 'no']), false);
        $this->actingAs($this->user)->get(route('dispatches.index', ['status' => 'pending', 'from' => '2026-11-01']))
            ->assertSee(e(route('dispatches.pdf', ['status' => 'pending', 'from' => '2026-11-01'])), false)
            ->assertSee('From')->assertSee('To');
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
