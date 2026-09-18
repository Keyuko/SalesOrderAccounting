@extends('layouts.app')

@section('title', 'Delivery Process')

@section('content')
<div class="card">
    <div class="card-header" style="display:flex; justify-content:space-between; align-items:center;">
        <h2>Delivery Process</h2>
        <div>
            <a href="?view=calendar" class="btn {{ $viewType == 'calendar' ? 'btn-primary' : 'btn-outline' }}" style="{{ $viewType != 'calendar' ? 'background:#E2E8F0; color:#111827;' : '' }}">Calendar View</a>
            <a href="?view=table" class="btn {{ $viewType == 'table' ? 'btn-primary' : 'btn-outline' }}" style="{{ $viewType != 'table' ? 'background:#E2E8F0; color:#111827;' : '' }}">List View</a>
        </div>
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

    @if($viewType == 'table')
        <table class="datatable display" style="width:100%">
            <thead>
                <tr>
                    <th>DO Number</th>
                    <th>Delivery Date</th>
                    <th>Location</th>
                    <th>Driver Name</th>
                    <th>Plat Kendaraan</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @foreach($deliveries as $del)
                <tr>
                    <td>{{ $del->deliveryOrder->do_number ?? '-' }}</td>
                    <td>{{ $del->deliveryOrder->delivery_date ?? '-' }}</td>
                    <td>{{ $del->deliveryOrder->location ?? '-' }}</td>
                    <td>{{ $del->driver_name ?? '-' }}</td>
                    <td>{{ $del->plat_kendaraan ?? '-' }}</td>
                    <td>
                        <span class="badge {{ $del->status == 'pending' ? 'pending' : ($del->status == 'close' ? 'done' : 'rejected') }}">{{ ucfirst($del->status) }}</span>
                    </td>
                    <td>
                        <button class="btn btn-sm btn-primary" onclick="openEventModalFromTable({{ json_encode($del) }}, {{ json_encode($del->deliveryOrder) }})">Manage</button>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    @else
        <div id='calendar'></div>
    @endif
</div>

<!-- Event Detail Modal -->
<div id="eventModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:1000; align-items:center; justify-content:center;">
    <div style="background:white; padding:24px; border-radius:12px; width:450px;">
        <h3 id="modalTitle">Delivery Detail</h3>
        
        <div style="margin-bottom: 20px;">
            <p><strong>Location:</strong> <span id="modalLocation"></span></p>
            <p><strong>Driver:</strong> <span id="modalDriver"></span></p>
            <p><strong>Status:</strong> <span id="modalStatus" class="badge"></span></p>
            
            @if(Auth::user()->role == 'delivery')
            <form id="imeiForm" method="POST" style="margin-top: 10px; display:flex; gap:10px; align-items:center;">
                @csrf
                @method('PUT')
                <strong>Plat Kendaraan:</strong> 
                <input type="text" name="plat_kendaraan" id="modalGpsInput" class="form-control" style="width:200px;" placeholder="Cth: B 1234 CD">
                <button type="submit" class="btn btn-primary btn-sm">Save</button>
            </form>
            @else
            <p><strong>Plat Kendaraan:</strong> <span id="modalGps"></span></p>
            @endif
        </div>

        <div id="trackingSection" style="margin-bottom:20px; display:none; background:#F3F4F6; padding:15px; border-radius:8px; text-align:center;">
            <p style="margin-top:0;"><strong>Pelacakan GPS.id</strong></p>
            <button type="button" class="btn btn-success" id="btnTrack" onclick="openLiveTracking()">Buka Peta Live Tracking</button>
            <p id="trackingLoading" style="display:none; color:#6B7280; margin-bottom:0;">Mengambil Link...</p>
        </div>

        @if(Auth::user()->role == 'delivery')
        <div id="actionButtons" style="display:flex; justify-content:flex-end; gap:10px;">
            <form id="closeForm" method="POST" style="margin:0;">
                @csrf
                <button type="submit" class="btn btn-success">Mark Close</button>
            </form>
            <form id="cancelForm" method="POST" style="margin:0;">
                @csrf
                <button type="submit" class="btn btn-danger">Mark Canceled</button>
            </form>
            <button type="button" class="btn" style="background:#E2E8F0;" onclick="closeEventModal()">Close</button>
        </div>
        @else
        <div style="display:flex; justify-content:flex-end; gap:10px;">
            <button type="button" class="btn" style="background:#E2E8F0;" onclick="closeEventModal()">Close</button>
        </div>
        @endif
    </div>
</div>
@endsection

@section('scripts')
<script>
    var currentSelectedEventId = null;
    var currentSelectedChecklist = null;

    document.addEventListener('DOMContentLoaded', function() {
        var calendarEl = document.getElementById('calendar');
        var calendar = new FullCalendar.Calendar(calendarEl, {
            initialView: 'dayGridMonth',
            events: @json($events),
            eventClick: function(info) {
                currentSelectedEventId = info.event.id;
                currentSelectedChecklist = info.event.extendedProps.checklist;

                // Populate Modal
                document.getElementById('modalTitle').innerText = info.event.title;
                document.getElementById('modalLocation').innerText = info.event.extendedProps.location;
                document.getElementById('modalDriver').innerText = info.event.extendedProps.driver;
                
                var statusBadge = document.getElementById('modalStatus');
                statusBadge.innerText = info.event.extendedProps.status.toUpperCase();
                statusBadge.className = 'badge ' + info.event.extendedProps.status;

                // Setup Plat Kendaraan
                var imei = info.event.extendedProps.plat_kendaraan;
                @if(Auth::user()->role == 'delivery')
                    document.getElementById('modalGpsInput').value = imei || '';
                    document.getElementById('imeiForm').action = '/deliveries/' + info.event.id + '/imei';
                @else
                    document.getElementById('modalGps').innerText = imei || '-';
                @endif

                // Setup Tracking Section
                if (imei) {
                    document.getElementById('trackingSection').style.display = 'block';
                } else {
                    document.getElementById('trackingSection').style.display = 'none';
                }

                // Setup Action URLs
                @if(Auth::user()->role == 'delivery')
                if(info.event.extendedProps.status === 'pending') {
                    document.getElementById('closeForm').style.display = 'block';
                    document.getElementById('cancelForm').style.display = 'block';
                    document.getElementById('closeForm').action = '/deliveries/' + info.event.id + '/close';
                    document.getElementById('cancelForm').action = '/deliveries/' + info.event.id + '/cancel';
                } else {
                    document.getElementById('closeForm').style.display = 'none';
                    document.getElementById('cancelForm').style.display = 'none';
                }
                @endif

                document.getElementById('eventModal').style.display = 'flex';
            }
        });
        calendar.render();
    });

    function closeEventModal() {
        document.getElementById('eventModal').style.display = 'none';
    }

    function openLiveTracking() {
        var btn = document.getElementById('btnTrack');
        var loading = document.getElementById('trackingLoading');
        
        btn.style.display = 'none';
        loading.style.display = 'block';

        fetch('/deliveries/' + currentSelectedEventId + '/tracking')
            .then(res => res.json())
            .then(data => {
                btn.style.display = 'inline-block';
                loading.style.display = 'none';
                
                if (data.link) {
                    window.open(data.link, '_blank');
                } else if (data.error) {
                    alert('Error: ' + data.error);
                }
            })
            .catch(err => {
                btn.style.display = 'inline-block';
                loading.style.display = 'none';
                alert('Failed to connect to server.');
            });
    }
</script>
<script>
    function openEventModalFromTable(del, doObj) {
        currentSelectedEventId = del.id;
        
        document.getElementById('modalTitle').innerText = 'DO: ' + (doObj ? doObj.do_number : 'Unknown') + ' - ' + del.status.toUpperCase();
        document.getElementById('modalLocation').innerText = doObj ? doObj.location : '-';
        document.getElementById('modalDriver').innerText = del.driver_name || '-';
        
        var statusBadge = document.getElementById('modalStatus');
        statusBadge.innerText = del.status.toUpperCase();
        var badgeClass = del.status == 'pending' ? 'pending' : (del.status == 'close' ? 'done' : 'rejected');
        statusBadge.className = 'badge ' + badgeClass;

        var imei = del.plat_kendaraan;
        @if(Auth::user()->role == 'delivery')
            document.getElementById('modalGpsInput').value = imei || '';
            document.getElementById('imeiForm').action = '/deliveries/' + del.id + '/imei';
        @else
            document.getElementById('modalGps').innerText = imei || '-';
        @endif

        if (imei) {
            document.getElementById('trackingSection').style.display = 'block';
        } else {
            document.getElementById('trackingSection').style.display = 'none';
        }

        @if(Auth::user()->role == 'delivery')
        if(del.status === 'pending') {
            document.getElementById('closeForm').style.display = 'block';
            document.getElementById('cancelForm').style.display = 'block';
            document.getElementById('closeForm').action = '/deliveries/' + del.id + '/close';
            document.getElementById('cancelForm').action = '/deliveries/' + del.id + '/cancel';
        } else {
            document.getElementById('closeForm').style.display = 'none';
            document.getElementById('cancelForm').style.display = 'none';
        }
        @endif

        document.getElementById('eventModal').style.display = 'flex';
    }
</script>
<style>
    .fc-event { cursor: pointer; }
</style>
@endsection
