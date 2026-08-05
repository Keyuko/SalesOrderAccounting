@extends('layouts.app')

@section('title', 'Quotations')

@section('content')
<div class="card">
    <div class="card-header">
        <h2>List Quotation (Salesforce Data)</h2>
    </div>
    
    @if(session('success'))
        <div style="background: #D1FAE5; color: #059669; padding: 10px; border-radius: 6px; margin-bottom: 20px;">
            {{ session('success') }}
        </div>
    @endif
    @if(session('error'))
        <div style="background: #FEE2E2; color: #DC2626; padding: 10px; border-radius: 6px; margin-bottom: 20px;">
            {{ session('error') }}
        </div>
    @endif

    <table class="datatable display" style="width:100%">
        <thead>
            <tr>
                <th>No</th>
                <th>Quotation Number</th>
                <th>Customer Name</th>
                <th>Req. Delivery Date</th>
                <th>Status (PPIC)</th>
                <th>Notes</th>
                @if(Auth::user()->role == 'ppic')
                <th>Actions</th>
                @endif
            </tr>
        </thead>
        <tbody>
            @foreach($quotations as $index => $q)
            <tr>
                <td>{{ $index + 1 }}</td>
                <td>{{ $q->quotation_number }}</td>
                <td>{{ $q->customer_name }}</td>
                <td>{{ $q->requested_delivery_date }}</td>
                <td>
                    <span class="badge {{ $q->ppic_status }}">{{ ucfirst($q->ppic_status) }}</span>
                </td>
                <td>{{ $q->ppic_notes ?? '-' }}</td>
                @if(Auth::user()->role == 'ppic')
                <td>
                    @if($q->ppic_status == 'pending')
                    <form action="{{ route('quotations.approve', $q->id) }}" method="POST" style="display:inline;">
                        @csrf
                        <button class="btn btn-success" type="submit">Approve</button>
                    </form>
                    <button class="btn btn-danger" onclick="openRejectModal({{ $q->id }})">Reject</button>
                    @else
                    -
                    @endif
                </td>
                @endif
            </tr>
            @endforeach
        </tbody>
    </table>
</div>

<!-- Reject Modal -->
<div id="rejectModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:1000; align-items:center; justify-content:center;">
    <div style="background:white; padding:24px; border-radius:12px; width:400px;">
        <h3>Reject Quotation</h3>
        <form id="rejectForm" method="POST">
            @csrf
            <div style="margin-bottom: 15px;">
                <label style="display:block; margin-bottom:5px;">Reason / Notes for Sales</label>
                <textarea name="notes" required style="width:100%; padding:8px; border-radius:6px; border:1px solid #E2E8F0;"></textarea>
            </div>
            <div style="display:flex; justify-content:flex-end; gap:10px;">
                <button type="button" class="btn" style="background:#E2E8F0;" onclick="closeModal()">Cancel</button>
                <button type="submit" class="btn btn-danger">Confirm Reject</button>
            </div>
        </form>
    </div>
</div>
@endsection

@section('scripts')
<script>
    function openRejectModal(id) {
        document.getElementById('rejectModal').style.display = 'flex';
        document.getElementById('rejectForm').action = '/quotations/' + id + '/reject';
    }
    function closeModal() {
        document.getElementById('rejectModal').style.display = 'none';
    }
</script>
@endsection
