<?php

namespace Modules\Cargo\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use Modules\Cargo\Entities\OnlineBookingRequest;
use Modules\Cargo\Services\BranchAccessService;
use Modules\Cargo\Services\OnlineBookingService;

class OnlineBookingController extends Controller
{
    public function index()
    {
        $this->authorizeView();
        breadcrumb([
            ['name' => __('cargo::view.dashboard'), 'path' => fr_route('admin.dashboard')],
            ['name' => 'Online Bookings'],
        ]);

        return view('cargo::adminLte.pages.online-bookings.index', [
            'canManage' => $this->canManage(),
        ]);
    }

    public function feed(Request $request)
    {
        $this->authorizeView();
        $validated = $request->validate([
            'category' => ['nullable', Rule::in(['all', 'local', 'intercity', 'international_air', 'international_sea'])],
            'status' => ['nullable', Rule::in(['all', 'pending', 'accepted', 'rejected'])],
            'search' => ['nullable', 'string', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1', 'max:100000'],
        ]);

        $query = $this->scopedQuery()->with(['client:id,name,email,responsible_mobile', 'branch:id,name', 'shipment:id,code']);
        $category = $validated['category'] ?? 'all';
        if ($category === 'local') $query->where('service', 'local');
        if ($category === 'intercity') $query->where('service', 'intercity');
        if ($category === 'international_air') $query->where('service', 'import')->where('transport_mode', 'air');
        if ($category === 'international_sea') $query->where('service', 'import')->where('transport_mode', 'sea');

        $status = $validated['status'] ?? 'pending';
        if ($status !== 'all') $query->where('status', $status);

        if ($search = trim((string) ($validated['search'] ?? ''))) {
            $query->where(function ($builder) use ($search) {
                $builder->where('reference', 'like', '%' . $search . '%')
                    ->orWhere('recipient_name', 'like', '%' . $search . '%')
                    ->orWhere('recipient_phone', 'like', '%' . $search . '%')
                    ->orWhereHas('client', fn ($client) => $client->where('name', 'like', '%' . $search . '%'));
            });
        }

        $page = (int) ($validated['page'] ?? 1);
        $bookings = $query->orderBy('submitted_at')->orderBy('id')->skip(($page - 1) * 100)->take(101)->get();
        $counts = $this->categoryCounts($status);

        return response()->json([
            'data' => $bookings->take(100)->map(fn ($booking) => $this->serialize($booking))->values(),
            'hasMore' => $bookings->count() > 100,
            'counts' => $counts,
            'refreshedAt' => now()->toIso8601String(),
        ]);
    }

    public function show(OnlineBookingRequest $onlineBooking)
    {
        $this->authorizeBooking($onlineBooking);
        return response()->json(['data' => $this->serialize($onlineBooking->load(['client', 'branch', 'shipment']))]);
    }

    public function update(Request $request, OnlineBookingRequest $onlineBooking)
    {
        $this->authorizeManage($onlineBooking);
        abort_if($onlineBooking->status !== 'pending', 422, 'Only pending booking requests can be edited.');

        $data = $request->validate([
            'service' => ['required', Rule::in(['local', 'intercity', 'import', 'custom'])],
            'transport_mode' => ['nullable', Rule::in(['air', 'sea'])],
            'pickup_address' => ['required', 'string', 'max:1000'],
            'destination_address' => ['required', 'string', 'max:1000'],
            'recipient_name' => ['required', 'string', 'max:255'],
            'recipient_phone' => ['required', 'string', 'max:60'],
            'schedule' => ['nullable', 'string', 'max:255'],
            'instructions' => ['nullable', 'string', 'max:3000'],
        ]);
        if ($data['service'] === 'import' && empty($data['transport_mode'])) {
            throw \Illuminate\Validation\ValidationException::withMessages(['transport_mode' => 'Select air or sea for international bookings.']);
        }
        if ($data['service'] !== 'import') $data['transport_mode'] = null;
        $onlineBooking->update($data);

        return response()->json(['data' => $this->serialize($onlineBooking->fresh(['client', 'branch', 'shipment']))]);
    }

    public function convert(OnlineBookingRequest $onlineBooking, OnlineBookingService $service)
    {
        $this->authorizeManage($onlineBooking);
        try {
            $shipment = $service->convertToShipment($onlineBooking, auth()->user());
        } catch (\DomainException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Booking accepted and shipment created.',
            'shipment' => ['id' => $shipment->id, 'code' => $shipment->code, 'url' => fr_route('shipments.show', $shipment->id)],
        ]);
    }

    public function reject(Request $request, OnlineBookingRequest $onlineBooking)
    {
        $this->authorizeManage($onlineBooking);
        abort_if($onlineBooking->status !== 'pending', 422, 'Only pending booking requests can be rejected.');
        $data = $request->validate(['reason' => ['required', 'string', 'max:1000']]);
        $payload = $onlineBooking->payload;
        $payload['review'] = ['rejectionReason' => $data['reason']];
        $onlineBooking->update([
            'status' => 'rejected',
            'payload' => $payload,
            'processed_by' => auth()->id(),
            'processed_at' => now(),
        ]);

        return response()->json(['message' => 'Booking rejected and retained in booking history.']);
    }

    private function scopedQuery()
    {
        $user = auth()->user();
        $branchAccess = app(BranchAccessService::class);
        return OnlineBookingRequest::query()
            ->when(!$branchAccess->isTopAdmin($user), fn ($query) => $query->whereIn('branch_id', $branchAccess->branchIdsFor($user)));
    }

    private function categoryCounts(string $status): array
    {
        $base = $this->scopedQuery()->when($status !== 'all', fn ($query) => $query->where('status', $status));
        return [
            'all' => (clone $base)->count(),
            'local' => (clone $base)->where('service', 'local')->count(),
            'intercity' => (clone $base)->where('service', 'intercity')->count(),
            'international_air' => (clone $base)->where('service', 'import')->where('transport_mode', 'air')->count(),
            'international_sea' => (clone $base)->where('service', 'import')->where('transport_mode', 'sea')->count(),
        ];
    }

    private function serialize(OnlineBookingRequest $booking): array
    {
        $serviceLabels = ['local' => 'Local Delivery', 'intercity' => 'City to City', 'import' => 'International', 'custom' => 'Custom Request'];
        return [
            'id' => $booking->id,
            'reference' => $booking->reference,
            'service' => $booking->service,
            'serviceLabel' => $serviceLabels[$booking->service] ?? 'Custom Request',
            'transportMode' => $booking->transport_mode,
            'status' => $booking->status,
            'customer' => $booking->client?->name,
            'customerEmail' => $booking->client?->email,
            'customerPhone' => $booking->client?->responsible_mobile,
            'branch' => $booking->branch?->name,
            'pickupAddress' => $booking->pickup_address,
            'destinationAddress' => $booking->destination_address,
            'recipientName' => $booking->recipient_name,
            'recipientPhone' => $booking->recipient_phone,
            'schedule' => $booking->schedule,
            'instructions' => $booking->instructions,
            'weight' => (float) $booking->total_weight,
            'quotedAmount' => (float) $booking->quoted_amount,
            'currency' => $booking->currency,
            'submittedAt' => optional($booking->submitted_at)->toIso8601String(),
            'submittedLabel' => optional($booking->submitted_at)->format('d M Y, H:i'),
            'shipment' => $booking->shipment ? ['id' => $booking->shipment->id, 'code' => $booking->shipment->code] : null,
            'canManage' => $this->canManage() && $booking->status === 'pending',
        ];
    }

    private function authorizeView(): void
    {
        $user = auth()->user();
        abort_unless($user && ((int) $user->role === User::ADMIN || $user->can('view-online-bookings')), 403);
    }

    private function authorizeBooking(OnlineBookingRequest $booking): void
    {
        $this->authorizeView();
        $branchAccess = app(BranchAccessService::class);
        abort_unless($branchAccess->isTopAdmin(auth()->user()) || $branchAccess->canAccessBranch(auth()->user(), $booking->branch_id), 403);
    }

    private function authorizeManage(OnlineBookingRequest $booking): void
    {
        $this->authorizeBooking($booking);
        abort_unless($this->canManage(), 403);
    }

    private function canManage(): bool
    {
        $user = auth()->user();
        return $user && ((int) $user->role === User::ADMIN || $user->can('manage-online-bookings'));
    }
}
