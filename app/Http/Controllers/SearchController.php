<?php

namespace App\Http\Controllers;

use App\Models\Buyer;
use App\Models\Loading;
use App\Models\Mill;
use App\Models\Sample;
use App\Models\SampleBundle;
use App\Models\SampleDispatch;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** One search box for every kind of record. */
class SearchController extends Controller
{
    private const PER_GROUP = 8;

    public function __invoke(Request $request): View
    {
        $q = trim((string) $request->input('q'));
        $results = [];

        if (mb_strlen($q) >= 2) {
            $like = "%{$q}%";
            $results = [
                'Samples' => Sample::with(['mill:id,name,city', 'variety:id,name'])->filter(['q' => $q])->latest('id')->limit(self::PER_GROUP)->get()
                    ->map(fn ($s) => [route('samples.show', $s), $s->sample_number.' · '.$s->variety->name, $s->mill->name.', '.$s->mill->city, 'basket']),
                'Dispatches' => SampleDispatch::with(['buyer:id,name', 'sample:id,sample_number'])->filter(['q' => $q])->latest('id')->limit(self::PER_GROUP)->get()
                    ->map(fn ($d) => [route('dispatches.show', $d), $d->dispatch_number.' → '.$d->buyer->name, $d->sample->sample_number.' · '.$d->stageLabel(), 'send']),
                'Rice mills' => Mill::search($q)->orderBy('name')->limit(self::PER_GROUP)->get()
                    ->map(fn ($m) => [route('mills.show', $m), $m->name, $m->city, 'buildings']),
                'Buyers' => Buyer::search($q)->orderBy('name')->limit(self::PER_GROUP)->get()
                    ->map(fn ($b) => [route('buyers.show', $b), $b->name, $b->city, 'people']),
                'Bundles' => SampleBundle::where('bundle_number', 'like', $like)->latest('id')->limit(self::PER_GROUP)->get()
                    ->map(fn ($b) => [route('bundles.show', $b), $b->bundle_number, $b->bundle_date->format('d M Y'), 'boxes']),
                'Truck loadings' => Loading::with('dispatch.buyer:id,name')
                    ->where(fn ($w) => $w->where('truck_number', 'like', $like)->orWhere('loading_number', 'like', $like)->orWhere('driver_name', 'like', $like))
                    ->latest('id')->limit(self::PER_GROUP)->get()
                    ->map(fn ($l) => [route('loadings.show', $l), $l->truck_number.' · '.$l->loading_number, $l->dispatch->buyer->name.' · '.$l->loading_date->format('d M Y'), 'truck']),
            ];
            $results = array_filter($results, fn ($group) => $group->isNotEmpty());
        }

        return view('search', ['q' => $q, 'results' => $results]);
    }
}
