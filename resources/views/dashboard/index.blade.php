@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
<div class="card">
    <div class="card-header">
        <h2>Dashboard</h2>
    </div>
    <div style="font-size: 24px; font-weight: 700; margin-bottom: 2rem;">
        Welcome to Sales Order Accounting, {{ Auth::user()->name }}!
    </div>

    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 20px;">
        @if(in_array(Auth::user()->role, ['sales', 'csr', 'ppic']))
        <a href="{{ route('quotations.index') }}" style="background: white; border-radius: 12px; padding: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); text-align: center; border: 1px solid #E2E8F0; text-decoration: none; color: inherit; display: block;">
            <div style="font-size: 40px; margin-bottom: 15px;">📄</div>
            <div style="font-size: 18px; font-weight: 700; margin-bottom: 5px;">Quotations</div>
            <div style="font-size: 14px; color: #64748B;">{{ $quotationCount ?? 0 }} Total</div>
        </a>

        <a href="{{ route('sales_orders.index') }}" style="background: white; border-radius: 12px; padding: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); text-align: center; border: 1px solid #E2E8F0; text-decoration: none; color: inherit; display: block;">
            <div style="font-size: 40px; margin-bottom: 15px;">📦</div>
            <div style="font-size: 18px; font-weight: 700; margin-bottom: 5px;">Sales Orders</div>
            <div style="font-size: 14px; color: #64748B;">{{ $soCount ?? 0 }} Total</div>
        </a>
        @endif

        @if(in_array(Auth::user()->role, ['sales', 'csr', 'ppic', 'security']))
        <a href="{{ route('delivery_orders.index') }}" style="background: white; border-radius: 12px; padding: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); text-align: center; border: 1px solid #E2E8F0; text-decoration: none; color: inherit; display: block;">
            <div style="font-size: 40px; margin-bottom: 15px;">🚚</div>
            <div style="font-size: 18px; font-weight: 700; margin-bottom: 5px;">Delivery Orders</div>
            <div style="font-size: 14px; color: #64748B;">{{ $doCount ?? 0 }} Total</div>
        </a>
        @endif

        @if(in_array(Auth::user()->role, ['sales', 'csr', 'delivery']))
        <a href="{{ route('deliveries.index') }}" style="background: white; border-radius: 12px; padding: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); text-align: center; border: 1px solid #E2E8F0; text-decoration: none; color: inherit; display: block;">
            <div style="font-size: 40px; margin-bottom: 15px;">📍</div>
            <div style="font-size: 18px; font-weight: 700; margin-bottom: 5px;">Deliveries Tracking</div>
            <div style="font-size: 14px; color: #64748B;">{{ $deliveryCount ?? 0 }} Total</div>
        </a>
        @endif
    </div>
</div>
@endsection
