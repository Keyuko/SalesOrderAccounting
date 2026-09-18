<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Sales Order Accounting') | PT. Dunia Kimia Jaya</title>
    
    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
    
    <!-- FullCalendar CSS (for Delivery) -->
    <script src='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.9/index.global.min.js'></script>

    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap');
        *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
        html{-webkit-font-smoothing:antialiased;font-size:13px}
        body{font-family:'Inter',system-ui,sans-serif;background:#f3f4f6;color:#111827;min-height:100vh;display:flex}
 
        /* SIDEBAR */
        .sidebar{width:200px;min-height:100vh;background:#111827;display:flex;flex-direction:column;padding:20px 0;flex-shrink:0;position:fixed;top:0;left:0;bottom:0;z-index:100}
        .sidebar-brand{padding:0 16px 20px;display:flex;align-items:center;gap:9px;border-bottom:1px solid rgba(255,255,255,.07)}
        .sidebar-logo{width:32px;height:32px;background:#3b5bdb;border-radius:9px;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:800;color:#fff;flex-shrink:0}
        .sidebar-brand-name{font-size:13px;font-weight:700;color:#f9fafb;line-height:1.2}
        .sidebar-brand-sub{font-size:10px;color:#6b7280;margin-top:1px}
        .sidebar-nav{padding:12px 10px;display:flex;flex-direction:column;gap:2px;flex:1}
        .sidebar-label{font-size:10px;font-weight:600;color:#6b7280;letter-spacing:.08em;text-transform:uppercase;padding:8px 8px 4px;margin-top:6px}
        .sidebar-link{display:flex;align-items:center;gap:9px;padding:8px 10px;border-radius:7px;color:#9ca3af;text-decoration:none;font-size:12.5px;font-weight:500;transition:background .15s,color .15s}
        .sidebar-link svg{flex-shrink:0;opacity:.7}
        .sidebar-link:hover{background:rgba(255,255,255,.07);color:#f9fafb}
        .sidebar-link:hover svg{opacity:1}
        .sidebar-link.active{background:rgba(255,255,255,.1);color:#f9fafb}
        .sidebar-link.active svg{opacity:1}
        .sidebar-footer{padding:10px;border-top:1px solid rgba(255,255,255,.07)}
        .sidebar-logout{display:flex;align-items:center;gap:9px;padding:8px 10px;border-radius:7px;color:#f87171;font-size:12.5px;font-weight:500;background:none;border:none;cursor:pointer;width:100%;text-align:left;transition:background .15s}
        .sidebar-logout:hover{background:rgba(239,68,68,.08)}
 
        /* MAIN */
        .main-wrap{margin-left:200px;flex:1;display:flex;flex-direction:column;min-height:100vh;width:calc(100% - 200px)}
 
        /* TOPBAR */
        .topbar{background:#fff;border-bottom:1px solid #e5e7eb;padding:0 28px;height:54px;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:50}
        .topbar-breadcrumb{display:flex;align-items:center;gap:5px;font-size:12px;color:#6b7280}
        .topbar-breadcrumb a{color:#6b7280;text-decoration:none}
        .topbar-breadcrumb a:hover{color:#111827}
        .topbar-breadcrumb .sep{color:#d1d5db}
        .topbar-breadcrumb .current{color:#111827;font-weight:500}
        .topbar-user{display:flex;align-items:center;gap:9px;font-size:12px}
        .topbar-avatar{width:30px;height:30px;background:#3b5bdb;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:700;color:#fff}
        .topbar-name{font-weight:600;color:#111827;font-size:12.5px}
        .topbar-role{color:#9ca3af;font-size:11px;text-transform:uppercase}
 
        /* PAGE */
        .page-content{padding:24px 28px;flex:1}
        .page-footer{padding:14px 28px;font-size:11.5px;color:#9ca3af;border-top:1px solid #e5e7eb;background:#fff;display:flex;justify-content:space-between}
        .page-footer a{color:#9ca3af;text-decoration:none}
 
        /* ALERTS */
        .alert{padding:11px 14px;border-radius:8px;margin-bottom:16px;font-size:13px;border:1px solid}
        .alert-success{background:#f0fdf4;color:#15803d;border-color:#bbf7d0}
        .alert-danger{background:#fef2f2;color:#b91c1c;border-color:#fecaca}
        .alert ul{margin:4px 0 0 16px}
        
        /* OVERRIDING FOR SALES ORDER PAGES */
        .card { background: #fff; border-radius: 12px; padding: 24px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); border: 1px solid #E2E8F0; margin-bottom: 24px; }
        .card-header { margin-bottom: 20px; padding-bottom: 15px; border-bottom: 1px solid #E2E8F0; display: flex; justify-content: space-between; align-items: center;}
        .card-header h2 { margin: 0; font-size: 1.25rem; color: #111827; }
        .badge { padding: 4px 8px; border-radius: 4px; font-size: 11px; font-weight: 700; text-transform: uppercase;}
        .badge.pending { background: #FEF3C7; color: #D97706; }
        .badge.approved, .badge.done { background: #D1FAE5; color: #059669; }
        .badge.rejected { background: #FEE2E2; color: #DC2626; }
        .badge.close { background: #E0E7FF; color: #4338CA; }
        .btn { padding: 6px 12px; border-radius: 6px; border: none; cursor: pointer; font-family: inherit; font-weight: 500; font-size: 13px;}
        .btn-primary { background: #3b5bdb; color: white; }
        .btn-danger { background: #EF4444; color: white; }
        .btn-success { background: #10B981; color: white; }
        .btn-sm { padding: 4px 8px; font-size: 11px; }
        
        /* DATA TABLES - PURCHASING STYLE */
        table.dataTable { border-collapse: collapse !important; width: 100% !important; font-size: 12.5px; border-bottom: 1px solid #e5e7eb; }
        table.dataTable thead th { background: #f9fafb; padding: 12px 14px; text-align: left; font-size: 10.5px; font-weight: 600; color: #6b7280; text-transform: uppercase; border-bottom: 1px solid #e5e7eb !important; border-top: none; border-left: none; border-right: none; }
        table.dataTable tbody td { padding: 12px 14px; border-bottom: 1px solid #f3f4f6; border-top: none; border-left: none; border-right: none; color: #374151; }
        table.dataTable.no-footer { border-bottom: 1px solid #e5e7eb; }
        .dataTables_wrapper .dataTables_filter { margin-bottom: 15px; }
        .dataTables_wrapper .dataTables_filter input { border: 1px solid #d1d5db; border-radius: 6px; padding: 6px 12px; margin-left: 8px; font-size: 12.5px; outline: none; }
        .dataTables_wrapper .dataTables_length select { border: 1px solid #d1d5db; border-radius: 6px; padding: 6px 12px; outline: none; }
    </style>
</head>
<body>
<aside class="sidebar">
    <div class="sidebar-brand">
        <div class="sidebar-logo">SO</div>
        <div>
            <div class="sidebar-brand-name">PT. Dunia Kimia Jaya</div>
            <div class="sidebar-brand-sub">Sales Order Portal</div>
        </div>
    </div>
    <nav class="sidebar-nav">
        <span class="sidebar-label">Main Menu</span>
        @auth
        <a href="{{ route('dashboard') }}" class="sidebar-link {{ request()->routeIs('dashboard') ? 'active' : '' }}">
            <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/></svg>
            Dashboard
        </a>
        
        @if(in_array(Auth::user()->role, ['sales', 'csr', 'ppic']))
        <a href="{{ route('quotations.index') }}" class="sidebar-link {{ request()->routeIs('quotations.*') ? 'active' : '' }}">
            <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" stroke-linecap="round" stroke-linejoin="round"/></svg>
            Quotations
        </a>
        <a href="{{ route('sales_orders.index') }}" class="sidebar-link {{ request()->routeIs('sales_orders.*') ? 'active' : '' }}">
            <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75" stroke-linecap="round" stroke-linejoin="round"/></svg>
            Sales Orders (SO)
        </a>
        @endif
        
        @if(in_array(Auth::user()->role, ['sales', 'csr', 'ppic', 'security']))
        <a href="{{ route('delivery_orders.index') }}" class="sidebar-link {{ request()->routeIs('delivery_orders.*') ? 'active' : '' }}">
            <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><rect x="4" y="2" width="16" height="20" rx="2" ry="2"/><path d="M9 22v-4h6v4M8 6h.01M16 6h.01M12 6h.01M8 10h.01M16 10h.01M12 10h.01M8 14h.01M16 14h.01M12 14h.01M8 18h.01M16 18h.01M12 18h.01"/></svg>
            Delivery Orders (DO)
        </a>
        @endif
        
        @if(in_array(Auth::user()->role, ['sales', 'csr', 'delivery']))
        <a href="{{ route('deliveries.index') }}" class="sidebar-link {{ request()->routeIs('deliveries.*') ? 'active' : '' }}">
            <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 3" stroke-linecap="round" stroke-linejoin="round"/></svg>
            Delivery Process
        </a>
        @endif
        
        @endauth
    </nav>
    @auth
    <div class="sidebar-footer">
        <form action="{{ route('bigquery.sync') }}" method="post" style="margin-bottom: 5px;">@csrf
            <button type="submit" class="sidebar-logout" style="color: #60A5FA;">
                <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><path d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" stroke-linecap="round" stroke-linejoin="round"/></svg>
                Sync Data BigQuery
            </button>
        </form>
        <form action="{{ route('logout') }}" method="post">@csrf
            <button type="submit" class="sidebar-logout">
                <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4M16 17l5-5-5-5M21 12H9" stroke-linecap="round" stroke-linejoin="round"/></svg>
                Logout
            </button>
        </form>
    </div>
    @endauth
</aside>
 
<div class="main-wrap">
    <header class="topbar">
        <div class="topbar-breadcrumb">
            <a href="{{ route('dashboard') }}">Portal</a>
            <span class="sep">/</span>
            <span class="current">@yield('title', 'Dashboard')</span>
        </div>
        @auth
        <div class="topbar-user">
            <div class="topbar-avatar">{{ strtoupper(substr(Auth::user()->name,0,2)) }}</div>
            <div>
                <div class="topbar-name">{{ Auth::user()->name }}</div>
                <div class="topbar-role">{{ Auth::user()->role }}</div>
            </div>
        </div>
        @endauth
    </header>
 
    <main class="page-content">
        @if(session('success'))
        <div class="alert alert-success">✓ {{ session('success') }}</div>
        @endif
        @if($errors->any())
        <div class="alert alert-danger">
            <strong>Please fix:</strong>
            <ul>@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
        @endif
        @yield('content')
    </main>
 
    <footer class="page-footer">
        <span>© 2026 PT Dunia Kimia Jaya · Sales Order Portal</span>
        <div style="display:flex;gap:12px;"><a href="#">Help</a><a href="#">Privacy</a><a href="#">Contact IT</a></div>
    </footer>
</div>

<!-- jQuery & DataTables JS -->
<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>

<script>
    $(document).ready(function() {
        if($('.datatable').length) {
            $('.datatable').DataTable({
                "pageLength": 10,
                "ordering": true,
                "searching": true,
                "info": true
            });
        }
    });
</script>

@yield('scripts')

</body>
</html>
