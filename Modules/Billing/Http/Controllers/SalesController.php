<?php

namespace Modules\Billing\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Billing\Services\SalesAccess;

class SalesController extends Controller
{
    public function quotations(Request $request)
    {
        return $this->listing($request, 'quotations');
    }

    public function invoices(Request $request)
    {
        return $this->listing($request, 'invoices');
    }

    private function listing(Request $request, string $type)
    {
        $access = app(SalesAccess::class);
        abort_unless($access->canView($request->user(), $type), 403);
        $statuses = $type === 'invoices' ? ['draft' => 'Draft', 'issued' => 'Issued', 'partially_paid' => 'Partially paid', 'paid' => 'Paid', 'void' => 'Void']
            : ['draft' => 'Draft', 'issued' => 'Issued', 'accepted' => 'Accepted', 'declined' => 'Declined', 'converted' => 'Converted'];
        $filters = $request->validate([
            'q' => 'nullable|string|max:100',
            'status' => 'nullable|in:' . implode(',', array_keys($statuses)),
            'page' => 'nullable|integer|min:1|max:100000',
            'per_page' => 'nullable|integer|in:10,25,50,100',
        ]);
        $ready = Schema::hasTable('billing_' . $type);
        $records = new LengthAwarePaginator([], 0, 25);
        if ($ready) {
            $query = $access->scope(DB::table('billing_' . $type), $request->user(), $type);
            if ($term = trim($filters['q'] ?? '')) {
                // Literal prefix search avoids wildcard-driven full scans of growing invoice history.
                $prefix = addcslashes($term, '\\%_') . '%';
                $query->where('number', 'like', $prefix);
            }
            if (!empty($filters['status'])) $query->where('status', $filters['status']);
            $records = $query->orderByDesc('id')->paginate((int) ($filters['per_page'] ?? 25))->withQueryString();
        }
        return view('billing::index', compact('records', 'statuses', 'ready', 'type') + ['title' => ucfirst($type)]);
    }
}
