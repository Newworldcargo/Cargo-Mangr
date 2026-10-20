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
        if ($type === 'invoices') $statuses['overdue']='Overdue';
        $filters = $request->validate([
            'q' => 'nullable|string|max:100',
            'status' => 'nullable|in:' . implode(',', array_keys($statuses)),
            'page' => 'nullable|integer|min:1|max:100000',
            'per_page' => 'nullable|integer|in:10,25,50,100',
            'from'=>'nullable|date_format:Y-m-d','until'=>'nullable|date_format:Y-m-d|after_or_equal:from',
            'branch_id'=>'nullable|integer','agent'=>'nullable|string|max:100',
        ]);
        $ready = Schema::hasTable('billing_' . $type);
        $records = new LengthAwarePaginator([], 0, 25);
        if ($ready) {
            $query = $access->scope(DB::table('billing_' . $type), $request->user(), $type);
            if ($term = trim($filters['q'] ?? '')) {
                // Literal prefix search avoids wildcard-driven full scans of growing invoice history.
                $prefix = addcslashes($term, '\\%_') . '%';
                $contains='%'.addcslashes($term,'\\%_').'%';
                $query->where(fn($search)=>$search->where('number','like',$prefix)->orWhere('customer_snapshot->name','like',$contains)->orWhere('customer_snapshot->email','like',$contains));
            }
            if (($filters['status']??'')==='overdue') $query->whereIn('status',['issued','partially_paid'])->where('due_date','<',now()->toDateString());
            elseif (!empty($filters['status'])) $query->where('status', $filters['status']);
            if(!empty($filters['from']))$query->where('issue_date','>=',$filters['from']);
            if(!empty($filters['until']))$query->where('issue_date','<=',$filters['until']);
            if(!empty($filters['branch_id']))$query->where('branch_id',$filters['branch_id']);
            if(!empty($filters['agent']))$query->where('document_details->sales_agent_name','like','%'.addcslashes($filters['agent'],'\\%_').'%');
            $records = $query->orderByDesc('id')->paginate((int) ($filters['per_page'] ?? 25))->withQueryString();
        }
        $branches=\Modules\Cargo\Entities\Branch::where('is_archived',0);
        if((int)$request->user()->role!==1 && !$request->user()->can('view-all-branch-billing-'.$type))$branches->whereIn('id',app(\Modules\Cargo\Services\BranchAccessService::class)->branchIdsFor($request->user()));
        return view('billing::index', compact('records', 'statuses', 'ready', 'type') + ['title' => ucfirst($type),'branches'=>$branches->orderBy('name')->get()]);
    }
}
