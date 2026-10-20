<?php

namespace Modules\Billing\Http\Controllers;

use App\Http\Controllers\Controller;
use DomainException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Billing\Services\{InvoiceLedger, QuotationLedger, SalesAccess};
use Modules\Cargo\Entities\{Branch, Client};
use Modules\Cargo\Services\BranchAccessService;

class DocumentController extends Controller
{
    public function create(Request $request, string $type)
    {
        $this->authorizeAction($request, $type, 'create');
        return $this->form($request, $type);
    }

    public function edit(Request $request, string $type, int $id)
    {
        $this->authorizeAction($request, $type, 'edit');
        $document = $this->document($request, $type, $id);
        abort_unless($document->status === 'draft', 409, 'Only drafts can be edited.');
        return $this->form($request, $type, $document);
    }

    private function form(Request $request, string $type, $document = null)
    {
        $branches = Branch::query();
        if ((int) $request->user()->role !== 1) $branches->whereIn('id', app(BranchAccessService::class)->branchIdsFor($request->user()));
        $items = $document ? $this->items($type, $document) : [];
        $lines = array_map(fn ($line) => [
            'description' => $line->description, 'quantity' => number_format($line->quantity_hundredths / 100, 2, '.', ''),
            'unit_price' => number_format($line->unit_price_minor / 100, 2, '.', ''),
            'discount' => number_format($line->discount_minor / 100, 2, '.', ''),
            'tax_percent' => number_format($line->tax_basis_points / 100, 2, '.', ''),
        ], $items);
        return view('billing::form', [
            'type' => $type, 'document' => $document, 'branches' => $branches->orderBy('name')->get(),
            'lines' => old('items', $lines ?: [['description' => '', 'quantity' => '1', 'unit_price' => '', 'discount' => '0', 'tax_percent' => '0']]),
            'key' => (string) Str::uuid(),
            'shipmentDetails' => old('shipment_details', $document ? json_decode($document->shipment_details ?? 'null', true) : null),
        ]);
    }

    public function customers(Request $request)
    {
        $access = app(SalesAccess::class);
        abort_unless($access->canView($request->user(), 'invoices') || $access->canView($request->user(), 'quotations'), 403);
        $data = $request->validate(['q' => 'required|string|min:2|max:100', 'branch_id' => 'required|integer']);
        if ((int) $request->user()->role !== 1) abort_unless(app(BranchAccessService::class)->branchIdsFor($request->user())->contains((int) $data['branch_id']), 403);
        $query = $access->customers($request->user(), (int) $data['branch_id']);
        $words = preg_split('/\s+/u', trim($data['q']), -1, PREG_SPLIT_NO_EMPTY);
        foreach ($words as $word) {
            $term = '%' . addcslashes(mb_strtolower($word), '\\%_') . '%';
            $query->where(function ($query) use ($term) {
                $query->whereRaw('LOWER(name) LIKE ?', [$term])->orWhereRaw('LOWER(email) LIKE ?', [$term]);
            });
        }
        return response()->json($query->orderBy('name')->limit(20)->get(['id', 'name', 'email']));
    }

    public function shipments(Request $request)
    {
        $access = app(SalesAccess::class);
        abort_unless($access->canView($request->user(), 'invoices') || $access->canView($request->user(), 'quotations'), 403);
        $data = $request->validate(['q' => 'required|string|min:2|max:100', 'branch_id' => 'required|integer']);
        if ((int) $request->user()->role !== 1) abort_unless(app(BranchAccessService::class)->branchIdsFor($request->user())->contains((int) $data['branch_id']), 403);
        $permissions = app(\Modules\Cargo\Services\ShipmentOperationAccessService::class);
        $rows = \Modules\Cargo\Entities\Shipment::where('branch_id', $data['branch_id'])
            ->where('code', 'like', addcslashes(trim($data['q']), '\\%_') . '%')->orderBy('code')->limit(20)->get()
            ->filter(fn ($shipment) => $permissions->canView($request->user(), $shipment))
            ->map(fn ($shipment) => ['code' => $shipment->code, 'url' => route('shipments.show', $shipment->id)])->values();
        return response()->json($rows);
    }

    public function createCustomer(Request $request)
    {
        $access = app(SalesAccess::class);
        $user = $request->user();
        abort_unless((int) $user->role === 1 || $user->can('create-customers'), 403);
        $data = $request->validate([
            'type' => 'required|in:invoices,quotations', 'branch_id' => 'required|integer',
            'first_name' => 'required|string|min:2|max:80', 'last_name' => 'required|string|min:2|max:80',
            'email' => 'required|email|max:191', 'phone' => 'required|string|max:40',
            'phone_2' => 'nullable|string|max:40',
        ]);
        abort_unless($access->canAct($user, 'create', $data['type']) || $access->canAct($user, 'edit', $data['type']), 403);
        $branch = Branch::where('is_archived', 0)->findOrFail($data['branch_id']);
        if ((int) $user->role !== 1) abort_unless(app(BranchAccessService::class)->branchIdsFor($user)->contains($branch->id), 403);
        $numbers = array_unique(array_filter(array_map(fn ($field) => \App\Services\ConsignmentCustomerMatcher::phone($data[$field] ?? ''), ['phone', 'phone_2'])));
        sort($numbers);
        $locks = [];
        try {
            foreach ($numbers as $number) {
                $lock = \Illuminate\Support\Facades\Cache::lock('portal-register-phone:' . hash('sha256', $number), 300);
                abort_unless($lock->get(), 409, 'This contact is being saved. Please try again shortly.');
                $locks[] = $lock;
            }
            $emailLock = \Illuminate\Support\Facades\Cache::lock('sales-customer-email:' . hash('sha256', strtolower(trim($data['email']))), 300);
            abort_unless($emailLock->get(), 409, 'This contact is being saved. Please try again shortly.');
            $locks[] = $emailLock;
            $client = DB::transaction(function () use ($data, $branch, $user) {
                $client = app(\App\Services\ImportCustomerSelection::class)->createForBranch($data + ['contact_name' => ''], $branch->id);
                \App\Models\AuditLog::create(['user_id' => $user->id, 'event' => 'sales_customer_created',
                    'auditable_type' => Client::class, 'auditable_id' => $client->id,
                    'description' => 'Customer created from a sales document.',
                    'new_values' => ['customer_id' => $client->id, 'branch_id' => $branch->id]]);
                return $client;
            });
            return response()->json($client->only(['id', 'name', 'email', 'branch_id']), 201);
        } finally {
            foreach ($locks as $lock) $lock->release();
        }
    }

    public function store(Request $request, string $type, ?int $id = null)
    {
        $this->authorizeAction($request, $type, $id ? 'edit' : 'create');
        if ($id) $this->document($request, $type, $id);
        $data = $request->validate([
            'request_key' => 'required|uuid', 'revision' => 'nullable|integer|min:1',
            'client_id' => 'required|integer', 'branch_id' => 'required|integer',
            'issue_date' => 'required|date_format:Y-m-d', 'due_date' => 'required|date_format:Y-m-d|after_or_equal:issue_date',
            'terms' => 'nullable|string|max:5000', 'items' => 'required|array|min:1|max:100',
            'items.*.description' => 'required|string|max:500', 'items.*.quantity' => 'required|string|max:20',
            'items.*.unit_price' => 'required|string|max:20', 'items.*.discount' => 'required|string|max:20',
            'items.*.tax_percent' => 'required|string|max:20',
        ]);
        $branch = Branch::findOrFail($data['branch_id']);
        if ((int) $request->user()->role !== 1) abort_unless(app(BranchAccessService::class)->branchIdsFor($request->user())->contains($branch->id), 403);
        $client = app(SalesAccess::class)->customers($request->user(), $branch->id)->findOrFail($data['client_id']);
        $data['currency'] = app(BranchAccessService::class)->currencyFor(null, $branch, true);
        $data['shipment_details'] = \Modules\Billing\Services\ShipmentDetails::validated($request->all());
        $data['customer_snapshot'] = ['name' => $client->name, 'email' => $client->email];
        $data['issuer_snapshot'] = ['name' => 'New World Cargo', 'branch' => $branch->name];
        try {
            if ($type === 'quotations') {
                $id = app(QuotationLedger::class)->save($data, $request->user()->id, $id, (int) ($data['revision'] ?? 1));
            } elseif ($id) {
                app(InvoiceLedger::class)->updateDraft($id, $data, $request->user()->id, (int) ($data['revision'] ?? 1));
            } else {
                $id = app(InvoiceLedger::class)->createDraft($data, $request->user()->id);
            }
        } catch (DomainException $error) {
            throw ValidationException::withMessages(['document' => $error->getMessage()]);
        }
        return redirect()->route('billing.documents.show', compact('type', 'id'))->with('success', 'Draft saved.');
    }

    public function show(Request $request, string $type, int $id)
    {
        $document = $this->document($request, $type, $id);
        $items = $this->items($type, $document);
        $payments = $type === 'invoices' ? DB::table('billing_payments')->where('invoice_id', $id)->orderBy('id')->get() : collect();
        $events = DB::table($type === 'invoices' ? 'billing_events' : 'billing_quotation_events')
            ->where($type === 'invoices' ? 'invoice_id' : 'quotation_id', $id)->orderByDesc('id')->paginate(20);
        $deliveries = DB::table('billing_deliveries')->where('type', $type)->where('document_id', $id)->orderByDesc('id')->limit(50)->get();
        return view('billing::show', compact('type', 'document', 'items', 'payments', 'events', 'deliveries'));
    }

    public function print(Request $request, string $type, int $id)
    {
        $document = $this->document($request, $type, $id);
        return response()->view('billing::print', ['type' => $type, 'document' => $document, 'items' => $this->items($type, $document)])
            ->header('Cache-Control', 'private, no-store');
    }

    public function download(Request $request, string $type, int $id)
    {
        $document = $this->document($request, $type, $id);
        $bytes = app(\Modules\Billing\Services\DocumentPdf::class)->get($type, $id);
        return response($bytes)->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'attachment; filename="' . ($document->number ?: 'Draft-' . $id) . '.pdf"')
            ->header('Cache-Control', 'private, no-store');
    }

    public function receipt(Request $request, int $id, int $payment)
    {
        $document = $this->document($request, 'invoices', $id);
        $receipt = DB::table('billing_payments')->where('invoice_id', $id)->where('id', $payment)->first();
        abort_unless($receipt, 404);
        $bytes = app(\Modules\Billing\Services\DocumentPdf::class)->receipt($document, $receipt);
        return response($bytes)->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'attachment; filename="' . $receipt->receipt_number . '.pdf"')->header('Cache-Control', 'private, no-store');
    }

    public function duplicate(Request $request, string $type, int $id)
    {
        $this->authorizeAction($request, $type, 'create');
        $document = $this->document($request, $type, $id);
        $data = $request->validate(['request_key' => 'required|uuid']);
        $data += [
            'client_id' => $document->client_id, 'branch_id' => $document->branch_id, 'currency' => $document->currency,
            'customer_snapshot' => json_decode($document->customer_snapshot, true), 'issuer_snapshot' => json_decode($document->issuer_snapshot, true),
            'shipment_details' => json_decode($document->shipment_details ?? 'null', true),
            'issue_date' => now()->toDateString(), 'due_date' => now()->addDays(14)->toDateString(), 'terms' => $document->terms,
            'items' => array_map(fn ($item) => [
                'description' => $item->description, 'quantity' => number_format($item->quantity_hundredths / 100, 2, '.', ''),
                'unit_price' => number_format($item->unit_price_minor / 100, 2, '.', ''),
                'discount' => number_format($item->discount_minor / 100, 2, '.', ''), 'tax_percent' => number_format($item->tax_basis_points / 100, 2, '.', ''),
            ], $this->items($type, $document)),
        ];
        try {
            $newId = $type === 'invoices' ? app(InvoiceLedger::class)->createDraft($data, $request->user()->id) : app(QuotationLedger::class)->save($data, $request->user()->id);
        } catch (DomainException $error) { throw ValidationException::withMessages(['document' => $error->getMessage()]); }
        return redirect()->route('billing.documents.edit', ['type' => $type, 'id' => $newId])->with('success', 'Copied to a new draft.');
    }

    public function delivery(Request $request, string $type, int $id)
    {
        $input = $request->validate(['request_key' => 'required|uuid', 'purpose' => 'required|in:document,reminder', 'send_at' => 'nullable|date_format:Y-m-d\TH:i']);
        $this->authorizeAction($request, $type, $input['purpose'] === 'reminder' ? 'schedule-reminder' : 'send');
        $document = $this->document($request, $type, $id);
        if (!$document->number || $document->status === 'void') throw ValidationException::withMessages(['document' => 'Only issued documents can be sent.']);
        if ($input['purpose'] === 'reminder' && ($type !== 'invoices' || $document->paid_minor >= $document->total_minor)) throw ValidationException::withMessages(['document' => 'Only unpaid invoices can have payment reminders.']);
        if (!\App\Models\MessagingSetting::current()->allows('email', 'customer_notifications')) throw ValidationException::withMessages(['document' => 'Customer emails are disabled in messaging settings.']);
        $recipient = json_decode($document->customer_snapshot, true)['email'] ?? '';
        if (!filter_var($recipient, FILTER_VALIDATE_EMAIL)) throw ValidationException::withMessages(['document' => 'This document does not have a valid customer email address.']);
        $at = $input['purpose'] === 'reminder' && !empty($input['send_at']) ? \Carbon\Carbon::createFromFormat('Y-m-d\TH:i', $input['send_at'], 'Africa/Lusaka')->timezone(config('app.timezone')) : now();
        if ($input['purpose'] === 'reminder' && (empty($input['send_at']) || $at->isPast() || $at->gt(now()->addYear()))) throw ValidationException::withMessages(['document' => 'Choose a future reminder date within the next year.']);
        DB::transaction(function () use ($request, $type, $id, $input, $recipient, $at) {
            $existing = DB::table('billing_deliveries')->where('request_key', $input['request_key'])->first();
            if ($existing) {
                abort_unless($existing->type === $type && (int) $existing->document_id === $id && $existing->purpose === $input['purpose'], 409);
                return;
            }
            $deliveryId = DB::table('billing_deliveries')->insertGetId([
                'request_key' => $input['request_key'], 'type' => $type, 'document_id' => $id,
                'actor_id' => $request->user()->id, 'recipient' => $recipient, 'purpose' => $input['purpose'],
                'send_at' => $at, 'status' => 'pending', 'created_at' => now(), 'updated_at' => now(),
            ]);
            \Modules\Billing\Jobs\SendSalesDocument::dispatch($deliveryId)->onConnection('messaging')->onQueue('notifications')->delay($at)->afterCommit();
        });
        return back()->with('success', $input['purpose'] === 'reminder' ? 'Reminder scheduled.' : 'Email queued.');
    }

    public function cancelDelivery(Request $request, string $type, int $id, int $delivery)
    {
        $this->authorizeAction($request, $type, 'schedule-reminder');
        $this->document($request, $type, $id);
        DB::table('billing_deliveries')->where('id', $delivery)->where('type', $type)->where('document_id', $id)
            ->where('purpose', 'reminder')->where('status', 'pending')->update(['status' => 'cancelled', 'updated_at' => now()]);
        return back()->with('success', 'Any pending reminder has been cancelled.');
    }

    public function action(Request $request, string $type, int $id, string $action)
    {
        $permissions = ['issue' => 'issue', 'payment' => 'record-payment', 'void' => 'void', 'accept' => 'record-decision', 'decline' => 'record-decision', 'convert' => 'convert'];
        abort_unless(isset($permissions[$action]), 404);
        $this->authorizeAction($request, $type, $permissions[$action]);
        $document = $this->document($request, $type, $id);
        if ($action === 'convert') $this->authorizeAction($request, 'invoices', 'create');
        $data = $request->validate([
            'note' => 'nullable|string|max:1000', 'amount' => 'nullable|string|max:20',
            'method' => 'nullable|in:cash,bank_transfer', 'reference' => 'nullable|string|max:120',
            'paid_on' => 'nullable|date_format:Y-m-d', 'request_key' => 'nullable|uuid',
        ]);
        try {
            if ($type === 'quotations') {
                $invoiceId = app(QuotationLedger::class)->transition($id, $action, $request->user()->id, $data['note'] ?? '');
                if ($invoiceId) return redirect()->route('billing.documents.show', ['type' => 'invoices', 'id' => $invoiceId]);
            } elseif ($action === 'issue') {
                app(InvoiceLedger::class)->issue($id, $request->user()->id);
            } elseif ($action === 'payment') {
                $data['currency'] = $document->currency;
                app(InvoiceLedger::class)->recordPayment($id, $data, $request->user()->id);
            } elseif ($action === 'void') {
                app(InvoiceLedger::class)->void($id, $request->user()->id, $data['note'] ?? '');
            } else abort(404);
            if ($action === 'issue') app(\Modules\Billing\Services\DocumentPdf::class)->get($type, $id);
        } catch (DomainException $error) {
            throw ValidationException::withMessages(['document' => $error->getMessage()]);
        }
        return redirect()->route('billing.documents.show', compact('type', 'id'))->with('success', 'Saved.');
    }

    private function authorizeAction(Request $request, string $type, string $action): void
    {
        abort_unless(app(SalesAccess::class)->canAct($request->user(), $action, $type), 403);
    }

    private function document(Request $request, string $type, int $id): object
    {
        $access = app(SalesAccess::class);
        abort_unless($access->canView($request->user(), $type), 403);
        $document = $access->scope(DB::table('billing_' . $type), $request->user(), $type)->where('id', $id)->first();
        abort_unless($document, 404);
        return $document;
    }

    private function items(string $type, object $document): array
    {
        return $type === 'invoices' ? DB::table('billing_invoice_items')->where('invoice_id', $document->id)->orderBy('position')->get()->all()
            : json_decode($document->items, false, 512, JSON_THROW_ON_ERROR);
    }
}
