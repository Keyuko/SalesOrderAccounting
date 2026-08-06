@extends('layouts.app')

@section('title', 'Deliveries Tracking')

@section('content')
<div class="card">
    <div class="card-header">
        <h2>Deliveries Tracking Calendar</h2>
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

    <div id='calendar'></div>
</div>

<!-- Event Detail Modal -->
<div id="eventModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:1000; align-items:center; justify-content:center;">
    <div style="background:white; padding:24px; border-radius:12px; width:450px;">
        <h3 id="modalTitle">Delivery Detail</h3>
        
        <div style="margin-bottom: 20px;">
            <p><strong>Location:</strong> <span id="modalLocation"></span></p>
            <p><strong>Driver:</strong> <span id="modalDriver"></span></p>
            <p><strong>Status:</strong> <span id="modalStatus" class="badge"></span></p>
        </div>

        @if(Auth::user()->role == 'delivery')
        <div id="actionButtons" style="display:flex; justify-content:flex-end; gap:10px;">
            <button type="button" class="btn btn-primary" onclick="openBukuMuat()">Buku Muat (Checklist)</button>
            <form id="closeForm" method="POST" style="margin:0;">
                @csrf
                <button type="submit" class="btn btn-success">Mark Close</button>
            </form>
            <form id="cancelForm" method="POST" style="margin:0;">
                @csrf
                <button type="submit" class="btn btn-danger">Mark Canceled</button>
            </form>
            <button type="button" class="btn" style="background:#E2E8F0;" onclick="closeEventModal()">Close Window</button>
        </div>
        @else
        <div style="display:flex; justify-content:flex-end; gap:10px;">
            <button type="button" class="btn btn-primary" onclick="openBukuMuat()">Lihat Buku Muat (Checklist)</button>
            <button type="button" class="btn" style="background:#E2E8F0;" onclick="closeEventModal()">Close Window</button>
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

    function openBukuMuat() {
        var baseUrl = "{{ env('BUKU_MUAT_URL', 'http://localhost/buku-muat/checklist_kendaraan_out.php') }}";
        
        // Find the event data from fullcalendar's array based on currentSelectedEventId
        var calEvent = @json($events).find(e => e.id == currentSelectedEventId);
        
        if (calEvent) {
            var url = new URL(baseUrl);
            url.searchParams.append('driver', calEvent.extendedProps.driver);
            url.searchParams.append('id', calEvent.id);
            // Any other matching fields here
            
            window.open(url.toString(), '_blank');
        }
    }
</script>
<style>
    .fc-event { cursor: pointer; }
</style>
@endsection
