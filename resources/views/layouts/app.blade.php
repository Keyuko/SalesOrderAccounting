<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Sales Order Accounting - PT Dunia Kimia Jaya')</title>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;700&display=swap" rel="stylesheet">
    
    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
    
    <!-- FullCalendar CSS (for Delivery) -->
    <script src='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.9/index.global.min.js'></script>

    <style>
        :root {
            --navy-dark: #0f2942;
            --sky-blue: #0ea5e9;
            --bg: #F1F5F9;
            --surface: #FFFFFF;
            --border: #E2E8F0;
            --text: #1A1C1E;
            --text-secondary: #64748B;
        }
        body { font-family: 'DM Sans', sans-serif; background: var(--bg); color: var(--text); margin: 0; padding: 0; display: flex; flex-direction: column; min-height: 100vh;}
        
        /* Navbar */
        .header { background: var(--navy-dark); color: white; padding: 1rem 2rem; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); }
        .header-title { display: flex; align-items: center; gap: 15px; }
        .header-title h3 { margin: 0; font-size: 1.25rem; }
        
        .header-nav { display: flex; gap: 20px; align-items: center;}
        .header-nav a { color: #CBD5E1; text-decoration: none; font-weight: 500; transition: color 0.2s;}
        .header-nav a:hover, .header-nav a.active { color: white; }

        .header-user { display: flex; align-items: center; gap: 20px; border-left: 1px solid rgba(255,255,255,0.2); padding-left: 20px;}
        .btn-logout { background: #EF4444; color: white; border: none; padding: 6px 12px; border-radius: 6px; cursor: pointer; font-weight: 600; transition: background 0.2s; }
        .btn-logout:hover { background: #DC2626; }
        
        /* Main Layout */
        .container { flex: 1; max-width: 1200px; margin: 2rem auto; width: 100%; padding: 0 2rem; }
        
        /* Card */
        .card { background: var(--surface); border-radius: 12px; padding: 24px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); border: 1px solid var(--border); margin-bottom: 24px; }
        .card-header { margin-bottom: 20px; padding-bottom: 15px; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center;}
        .card-header h2 { margin: 0; font-size: 1.25rem; color: var(--navy-dark); }
        
        /* Badge & Buttons */
        .badge { padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: 700; text-transform: uppercase;}
        .badge.pending { background: #FEF3C7; color: #D97706; }
        .badge.approved { background: #D1FAE5; color: #059669; }
        .badge.rejected { background: #FEE2E2; color: #DC2626; }
        .badge.close { background: #E0E7FF; color: #4338CA; }
        
        .btn { padding: 6px 12px; border-radius: 6px; border: none; cursor: pointer; font-family: inherit; font-weight: 500; font-size: 14px;}
        .btn-primary { background: var(--sky-blue); color: white; }
        .btn-danger { background: #EF4444; color: white; }
        .btn-success { background: #10B981; color: white; }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-title">
            <h3>Sales Order Accounting</h3>
        </div>
        
        <div class="header-nav">
            <a href="{{ route('dashboard') }}">Dashboard</a>
            @if(in_array(Auth::user()->role, ['sales', 'csr', 'ppic']))
                <a href="{{ route('quotations.index') }}">Quotations</a>
                <a href="{{ route('sales_orders.index') }}">Sales Orders (SO)</a>
            @endif
            @if(in_array(Auth::user()->role, ['sales', 'csr', 'ppic', 'security']))
                <a href="{{ route('delivery_orders.index') }}">Delivery Orders (DO)</a>
            @endif
            @if(in_array(Auth::user()->role, ['sales', 'csr', 'delivery']))
                <a href="{{ route('deliveries.index') }}">Delivery Tracker</a>
            @endif
        </div>

        <div class="header-user">
            <div>
                <strong>{{ Auth::user()->name }}</strong><br>
                <small style="text-transform: uppercase;">Role: {{ Auth::user()->role }}</small>
            </div>
            <form action="{{ route('logout') }}" method="POST">
                @csrf
                <button class="btn-logout">Logout</button>
            </form>
        </div>
    </div>

    <div class="container">
        @yield('content')
    </div>

    <!-- jQuery & DataTables JS -->
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    
    <script>
        $(document).ready(function() {
            $('.datatable').DataTable();
        });
    </script>
    @yield('scripts')
</body>
</html>
