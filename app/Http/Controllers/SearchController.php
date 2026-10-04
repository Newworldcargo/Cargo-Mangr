<?php

namespace App\Http\Controllers;

use App\Services\AdminGlobalSearch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Modules\Cargo\Entities\{Client, Shipment};

class SearchController extends Controller
{
    private function validateSearch(Request $request): string
    {
        abort_unless(auth()->check() && auth()->user()->can('use-global-search')
            && (auth()->user()->can('view-consignments') || auth()->user()->can('view-shipments')), 403);
        $request->validate(['q' => 'nullable|string|max:100', 'user_id' => 'nullable|integer|min:1',
            'shipments_page' => 'nullable|integer|min:1|max:10000', 'consignments_page' => 'nullable|integer|min:1|max:10000']);
        return trim((string) $request->input('q', ''));
    }

    public function index(Request $request)
    {
        if (!auth()->check()) return redirect()->route('signin');
        $query = $this->validateSearch($request);
        $results = [];
        if (mb_strlen($query) >= 2 || $request->filled('user_id')) $results = $this->search($request, $query, false);
        return view('search.index', compact('query', 'results'));
    }

    public function liveSearch(Request $request)
    {
        $query = $this->validateSearch($request);
        $results = [];
        if (mb_strlen($query) >= 2) {
            // Recheck permissions before cache access; no customer identities are exposed.
            $scope = [auth()->id(), auth()->user()->can('view-shipments'), auth()->user()->can('view-consignments'), app()->getLocale(), url('/')];
            $key = 'admin-search-v2:' . hash('sha256', json_encode([$scope, $query]));
            $results = Cache::remember($key, 10, fn () => $this->search($request, $query, true));
        }
        return response()->json(['success' => true, 'results' => $results,
            'total' => array_sum(array_map(fn ($section) => count($section['data']), $results)), 'query' => $query])
            ->header('Cache-Control', 'private, no-store');
    }

    private function search(Request $request, string $query, bool $quick): array
    {
        $service = app(AdminGlobalSearch::class);
        $results = [];
        $userId = $quick ? null : $request->input('user_id');
        // Preserve the existing company-shared operational scope, not user-management access.
        foreach (['consignments', 'shipments'] as $category) {
            if (!auth()->user()->can('view-' . $category) || ($userId && $category !== 'shipments')) continue;
            if ($userId) {
                $builder = $service->orderShipments(Shipment::whereIn('client_id', Client::where('user_id', $userId)->select('id')), '');
            } else {
                $builder = $service->$category($query, $quick);
            }
            $pageName = $category . '_page';
            $paginator = $builder->simplePaginate($quick ? 3 : 20, ['*'], $pageName, $quick ? 1 : (int) ($request->input($pageName) ?: 1));
            $paginator->appends($request->only(['q', 'user_id', 'shipments_page', 'consignments_page']));
            $data = $paginator->getCollection()->map(fn ($item) => $category === 'shipments' ? $service->shipmentResult($item) : $service->consignmentResult($item))->all();
            if (!$data && $paginator->currentPage() === 1) continue;
            $results[$category] = ['title' => $category === 'shipments' ? ($userId ? 'User Shipments' : 'Shipments') : 'Consignments',
                'icon' => $category === 'shipments' ? 'fa fa-box' : 'fa fa-ship', 'color' => $category === 'shipments' ? 'success' : 'warning',
                'data' => $data, 'hasMore' => $paginator->hasMorePages()];
            if (!$quick) $results[$category]['pagination'] = $paginator;
        }
        return $results;
    }
}
