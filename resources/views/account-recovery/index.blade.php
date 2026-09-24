@extends('cargo::adminLte.layouts.master')
@section('pageTitle', 'Customer Account Recovery')
@section('content')
<div class="container-fluid py-4">
    <h1 class="h3">Customer account recovery</h1>
    <p>Email confirmation verifies the contact email only. Confirm shipment ownership independently before changing any account. Saving a review does not merge accounts or grant access.</p>
    @if(session('success'))<div class="alert alert-success" role="status">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>@endif
    <div class="table-responsive"><table class="table table-bordered">
        <thead><tr><th>Request</th><th>Customer details</th><th>Review</th></tr></thead>
        <tbody>@forelse($requests as $item)
            <tr><td>{{ $item->created_at }}<br><span class="text-break">{{ $item->reference }}</span><br>{{ ucfirst($item->status) }}</td>
            <td><strong>{{ $item->details['name'] }}</strong><br>{{ $item->details['email'] }} (email confirmed)<br>{{ $item->details['phone'] }} (ownership unverified)<br>Shipment: {{ $item->details['shipmentReference'] ?? 'Not supplied' }}<p class="text-break">{{ $item->details['detail'] }}</p></td>
            <td><form method="POST" action="{{ route('account-recovery.update', $item->reference) }}">@csrf
                <label for="status-{{ $item->id }}">Status</label><select id="status-{{ $item->id }}" name="status" class="form-control mb-2">@foreach(['pending','contacted','resolved','rejected'] as $status)<option value="{{ $status }}" @selected($item->status === $status)>{{ ucfirst($status) }}</option>@endforeach</select>
                <label for="note-{{ $item->id }}">Review note</label><textarea id="note-{{ $item->id }}" name="note" class="form-control mb-2" required minlength="5" maxlength="2000">{{ $item->review_note }}</textarea><button class="btn btn-primary" type="submit">Save review</button>
            </form></td></tr>
        @empty<tr><td colspan="3">No recovery requests.</td></tr>@endforelse</tbody>
    </table></div>{{ $requests->links() }}
</div>
@endsection
