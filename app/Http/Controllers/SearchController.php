<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\Consignment;
use App\Models\User;
use Modules\Cargo\Entities\Shipment;
use Illuminate\Support\Facades\DB;

class SearchController extends Controller
{
    /**
     * Display the search page
     */
    public function index(Request $request)
    {
        // Check if user is authenticated
        if (!auth()->check()) {
            return redirect()->route('signin');
        }
        abort_unless($this->canUseGlobalSearch(auth()->user()), 403);
        
        $query = $request->get('q', '');
        $userId = $request->get('user_id', null);
        $results = [];
        
        if ($query || $userId) {
            $results = $this->performSearch($query, 50, $userId, auth()->user());
        }
        
        return view('search.index', compact('results', 'query'));
    }

    /**
     * Perform live search via AJAX
     */
    public function liveSearch(Request $request): JsonResponse
    {
        abort_unless(auth()->check() && $this->canUseGlobalSearch(auth()->user()), 403);
        $query = trim($request->get('q', ''));

        if (strlen($query) < 2) {
            return response()->json([
                'success' => true,
                'results' => [],
                'total' => 0
            ]);
        }
        $results = $this->performSearch($query, 10, null, auth()->user());
        return response()->json([
            'success' => true,
            'results' => $results,
            'total' => count($results),
            'query' => $query
        ]);
    }

    /**
     * Perform comprehensive search across all models
     */
    private function performSearch(string $query, int $limit, $userId, User $viewer): array
    {
        $results = [];
        $searchTerms = $query ? explode(' ', $query) : [];
        
        // If userId is provided, only search shipments for that user
        if ($userId && $viewer->can('view-shipments')) {
            $shipments = $this->searchShipmentsByUser($userId, $limit);
            if (!empty($shipments)) {
                $results['shipments'] = [
                    'title' => 'User Shipments',
                    'icon' => 'fa fa-box',
                    'color' => 'success',
                    'data' => $shipments
                ];
            }
            return $results;
        }
        
        // Search Consignments
        $consignments = $viewer->can('view-consignments')
            ? $this->searchConsignments($searchTerms, $limit)
            : [];
        if ($consignments) {
            $results['consignments'] = [
                'title' => 'Consignments',
                'icon' => 'fa fa-ship',
                'color' => 'warning',
                'data' => $consignments
            ];
        }

        // Search Shipments
        $shipments = $viewer->can('view-shipments')
            ? $this->searchShipments($searchTerms, $limit)
            : [];
        if ($shipments) {
            $results['shipments'] = [
                'title' => 'Shipments',
                'icon' => 'fa fa-box',
                'color' => 'success',
                'data' => $shipments
            ];
        }

        return $results;
    }

    /**
     * Global search only covers company-shared operational records. User records
     * remain in the branch-scoped User Management module.
     */
    private function canUseGlobalSearch(User $user): bool
    {
        return $user->can('use-global-search')
            && ($user->can('view-consignments') || $user->can('view-shipments'));
    }

    /**
     * Search in Consignments table
     */
    private function searchConsignments(array $searchTerms, int $limit): array
    {
        $query = Consignment::query();
        
        foreach ($searchTerms as $term) {
            $query->where(function($q) use ($term) {
                $q->where('consignment_code', 'LIKE', "%{$term}%")
                  ->orWhere('name', 'LIKE', "%{$term}%")
                  ->orWhere('desc', 'LIKE', "%{$term}%")
                  ->orWhere('source', 'LIKE', "%{$term}%")
                  ->orWhere('destination', 'LIKE', "%{$term}%")
                  ->orWhere('released_by', 'LIKE', "%{$term}%")
                  ->orWhere('tracker', 'LIKE', "%{$term}%")
                  ->orWhere('voyage_no', 'LIKE', "%{$term}%")
                  ->orWhere('shipping_line', 'LIKE', "%{$term}%")
                  ->orWhere('cargo_type', 'LIKE', "%{$term}%")
                  ->orWhere('consignee', 'LIKE', "%{$term}%")
                  ->orWhere('job_num', 'LIKE', "%{$term}%")
                  ->orWhere('mawb_num', 'LIKE', "%{$term}%")
                  ->orWhere('hawb_num', 'LIKE', "%{$term}%")
                  ->orWhere('status', 'LIKE', "%{$term}%");
            });
        }

        return $query->select([
            'id',
            'consignment_code',
            'name',
            'source',
            'destination',
            'status',
            'cargo_type',
            'created_at'
        ])
        ->orderBy('created_at', 'desc')
        ->limit($limit)
        ->get()
        ->map(function($item) {
            return [
                'id' => $item->id,
                'title' => $item->name ?: $item->consignment_code,
                'subtitle' => "Code: {$item->consignment_code}",
                'description' => "From {$item->source} to {$item->destination}",
                'status' => $item->status,
                'type' => $item->cargo_type,
                'date' => $item->created_at->format('M d, Y'),
                'url' => route('consignment.show', $item->id),
                'icon' => $item->cargo_type === 'sea' ? 'fa fa-ship' : 'fa fa-plane'
            ];
        })
        ->toArray();
    }

    /**
     * Search in Shipments table
     */
    private function searchShipments(array $searchTerms, int $limit): array
    {
        $query = Shipment::query();
        
        foreach ($searchTerms as $term) {
            $query->where(function($q) use ($term) {
                $q->where('code', 'LIKE', "%{$term}%") // tracking number
                  ->orWhere('client_phone', 'LIKE', "%{$term}%")
                  ->orWhere('client_address', 'LIKE', "%{$term}%")
                  ->orWhere('shipping_date', 'LIKE', "%{$term}%")
                  ->orWhere('shipping_cost', 'LIKE', "%{$term}%")
                  ->orWhere('dest_port', 'LIKE', "%{$term}%")
                  ->orWhere('salesman', 'LIKE', "%{$term}%")
                  ->orWhere('volume', 'LIKE', "%{$term}%");
            });
        }

        return $query->select([
            'id',
            'code',
            'client_phone',
            'client_address',
            'shipping_date',
            'shipping_cost',
            'dest_port',
            'salesman',
            'volume',
            'status_id',
            'created_at'
        ])
        ->orderBy('created_at', 'desc')
        ->limit($limit)
        ->get()
        ->map(function($item) {
            return [
                'id' => $item->id,
                'title' => $item->code ?: $item->code,
                'subtitle' => "Ref: " . ($item->code ?: 'N/A'),
                'description' => "Phone: {$item->client_phone} | Address: {$item->client_address} | Port: {$item->dest_port}",
                'status' => $item->getStatus(),
                'type' => $item->getTypeAttribute($item->type),
                'date' => $item->created_at->format('M d, Y'),
                'url' => url('admin/shipments/shipments/' . $item->id),
                'icon' => 'fas fa-box'
            ];
        })
        ->toArray();
    }

    /**
     * Search in Users table
     */
    private function searchUsers(array $searchTerms, int $limit): array
    {
        $query = User::query();
        
        foreach ($searchTerms as $term) {
            $query->where(function($q) use ($term) {
                $q->where('name', 'LIKE', "%{$term}%")
                  ->orWhere('email', 'LIKE', "%{$term}%");
            });
        }

        return $query->select([
            'id',
            'name',
            'email',
            'role',
            'created_at'
        ])
        ->orderBy('created_at', 'desc')
        ->limit($limit)
        ->get()
        ->map(function($item) {
            return [
                'id' => $item->id,
                'title' => $item->name,
                'subtitle' => $item->email,
                'description' => "Phone: {$item->name} | Address: {$item->address}, {$item->city}",
                'status' => $item->verified ? 'Verified' : 'Unverified',
                'type' => $item->getUserRoleAttribute(),
                'date' => $item->created_at->format('M d, Y'),
                'url' => route('search.index', ['q' => $item->name, 'user_id' => $item->id]),
                'icon' => 'fas fa-user'
            ];
        })
        ->toArray();
    }

    /**
     * Search shipments by user ID
     */
    private function searchShipmentsByUser($userId, int $limit): array
    {
        $query = Shipment::query();
        
        // Get user details
        $user = User::find($userId);
        if (!$user) {
            return [];
        }

        // Filter shipments by user role and ID
        $user_role = $user->role;
        
        // if ($user_role == 3) { // User Branch
        //     $branchId = \Modules\Cargo\Entities\Branch::where('user_id', $userId)->pluck('id')->first();
        //     $query->where('branch_id', $branchId);
        // } elseif ($user_role == 4) { // User Client
        $clientId = \Modules\Cargo\Entities\Client::where('user_id', $userId)->pluck('id')->first();
        $query->where('client_id', $clientId);
        // } elseif ($user->can('manage-shipments') && $user_role == 0) { // User Staff
        //     $branchId = \Modules\Cargo\Entities\Staff::where('user_id', $userId)->pluck('branch_id')->first();
        //     $query->where('branch_id', $branchId);
        // }

        return $query->select([
            'shipments.id',
            'shipments.code',
            'shipments.client_phone',
            'shipments.client_address',
            'shipments.shipping_date',
            'shipments.shipping_cost',
            'shipments.dest_port',
            'shipments.salesman',
            'shipments.volume',
            'shipments.status_id',
            'shipments.created_at'
        ])
        ->join('consignments', 'shipments.consignment_id', '=', 'consignments.id')
        ->orderBy('shipments.created_at', 'desc')
        ->limit($limit)
        ->get()
        ->map(function($item) use ($user) {
            return [
                'id' => $item->id,
                'title' => $item->code ?: 'Shipment #' . $item->id,
                'subtitle' => "Ref: " . ($item->code ?: 'N/A'),
                'description' => "Phone: {$item->client_phone} | Address: {$item->client_address} | Port: {$item->dest_port}",
                'status' => $item->getStatus(),
                'type' => $item->getTypeAttribute($item->type),
                'date' => $item->created_at->format('M d, Y'),
                'url' => url('admin/shipments/shipments/' . $item->id),
                'icon' => 'fas fa-box'
            ];
        })
        ->toArray();
    }
}
