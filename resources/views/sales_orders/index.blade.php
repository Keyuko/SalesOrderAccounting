@extends('layouts.app')

@section('title', 'Sales Orders')

@section('content')
<div class="card">
    <div class="card-header">
        <h2>List Sales Order</h2>
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
                <th>SO Number</th>
                <th>Delivery Date</th>
                <th>Location</th>
                <th>Notes</th>
                <th>Status (PPIC)</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            @foreach($salesOrders as $so)
            <tr>
                <td>{{ $so->so_number }}</td>
                <td>{{ $so->delivery_date }}</td>
                <td>{{ $so->location }}</td>
                <td>{{ $so->notes ?? '-' }}</td>
                <td>
                    <span class="badge {{ $so->ppic_status }}">{{ ucfirst($so->ppic_status) }}</span>
                </td>
                <td>
                    @if(in_array(Auth::user()->role, ['sales', 'csr']))
                        <button class="btn btn-primary" onclick="openEditModal({{ json_encode($so) }})">Edit</button>
                    @endif
                    
                    @if(Auth::user()->role == 'ppic' && $so->ppic_status == 'pending')
                        <form action="{{ route('sales_orders.approve', $so->id) }}" method="POST" style="display:inline;">
                            @csrf
                            <button class="btn btn-success" type="submit">Approve</button>
                        </form>
                        <button class="btn btn-danger" onclick="openRejectModal({{ $so->id }})">Reject</button>
                    @endif
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>

<!-- Edit Modal (Sales/CSR) -->
<div id="editModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:1000; align-items:center; justify-content:center;">
    <div style="background:white; padding:24px; border-radius:12px; width:500px;">
        <h3>Edit Sales Order</h3>
        <p style="font-size:12px; color:var(--text-secondary);">Editing will reset PPIC approval status to pending.</p>
        <form id="editForm" method="POST">
            @csrf
            @method('PUT')
            <div style="margin-bottom: 15px;">
                <label style="display:block; margin-bottom:5px;">Delivery Date</label>
                <input type="date" name="delivery_date" id="edit_delivery_date" required style="width:100%; padding:8px; border-radius:6px; border:1px solid #E2E8F0;">
            </div>
            <div style="margin-bottom: 15px;">
                <label style="display:block; margin-bottom:5px;">Location</label>
                <input type="text" name="location" id="edit_location" required style="width:100%; padding:8px; border-radius:6px; border:1px solid #E2E8F0;">
            </div>
            <div style="margin-bottom: 15px;">
                <label style="display:block; margin-bottom:5px;">Notes</label>
                <textarea name="notes" id="edit_notes" style="width:100%; padding:8px; border-radius:6px; border:1px solid #E2E8F0;"></textarea>
            </div>
            <div style="display:flex; justify-content:flex-end; gap:10px;">
                <button type="button" class="btn" style="background:#E2E8F0;" onclick="closeEditModal()">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<!-- Reject Modal (PPIC) -->
<div id="rejectModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:1000; align-items:center; justify-content:center;">
    <div style="background:white; padding:24px; border-radius:12px; width:400px;">
        <h3>Reject Sales Order</h3>
        <form id="rejectForm" method="POST">
            @csrf
            <div style="margin-bottom: 15px;">
                <label style="display:block; margin-bottom:5px;">Reason / Notes for Sales</label>
                <textarea name="notes" required style="width:100%; padding:8px; border-radius:6px; border:1px solid #E2E8F0;"></textarea>
            </div>
            <div style="display:flex; justify-content:flex-end; gap:10px;">
                <button type="button" class="btn" style="background:#E2E8F0;" onclick="closeRejectModal()">Cancel</button>
                <button type="submit" class="btn btn-danger">Confirm Reject</button>
            </div>
        </form>
    </div>
</div>
@endsection

@section('scripts')
<script>
    function openEditModal(so) {
        document.getElementById('editModal').style.display = 'flex';
        document.getElementById('editForm').action = '/sales_orders/' + so.id;
        document.getElementById('edit_delivery_date').value = so.delivery_date;
        document.getElementById('edit_location').value = so.location;
        document.getElementById('edit_notes').value = so.notes;
    }
    function closeEditModal() {
        document.getElementById('editModal').style.display = 'none';
    }

    function openRejectModal(id) {
        document.getElementById('rejectModal').style.display = 'flex';
        document.getElementById('rejectForm').action = '/sales_orders/' + id + '/reject';
    }
    function closeRejectModal() {
        document.getElementById('rejectModal').style.display = 'none';
    }
</script>
@endsection
