<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Crypt, DB};

class AccountRecoveryReviewController extends Controller
{
    public function index(Request $request)
    {
        abort_unless($request->user()->can('manage-customers'), 403);
        $requests = DB::table('customer_account_recovery_requests')->orderByRaw("CASE WHEN status = 'pending' THEN 0 ELSE 1 END")->orderBy('created_at')->paginate(30);
        foreach ($requests as $item) $item->details = json_decode(Crypt::decryptString($item->encrypted_details), true, 512, JSON_THROW_ON_ERROR);
        return view('account-recovery.index', compact('requests'));
    }

    public function update(Request $request, string $reference)
    {
        abort_unless($request->user()->can('manage-customers'), 403);
        $data = $request->validate(['status' => 'required|in:pending,contacted,resolved,rejected', 'note' => 'required|string|min:5|max:2000']);
        DB::transaction(function () use ($request, $reference, $data) {
            $item = DB::table('customer_account_recovery_requests')->where('reference', $reference)->lockForUpdate()->first();
            abort_unless($item, 404);
            DB::table('customer_account_recovery_requests')->where('id', $item->id)->update([
                'status' => $data['status'], 'review_note' => $data['note'], 'reviewed_by' => $request->user()->id, 'updated_at' => now(),
            ]);
            \App\Models\AuditLog::create(['user_id' => $request->user()->id, 'event' => 'account_recovery_reviewed',
                'auditable_type' => 'customer_account_recovery_requests', 'auditable_id' => $item->id,
                'description' => 'Account recovery review status updated. No account ownership changed.',
                'old_values' => ['status' => $item->status], 'new_values' => ['status' => $data['status']]]);
        });
        return back()->with('success', 'Review saved. Account access has not been changed.');
    }
}
