@extends('emails.layouts.transactional')

@section('title', 'Low Stock Alert')

@section('content')
    <p class="heading">Low stock alert</p>
    <p><strong>{{ $productName }}</strong>{{ $sku ? " (SKU: {$sku})" : '' }} has dropped to <strong>{{ $currentStock }}</strong> unit(s) left, at or below its low-stock threshold of {{ $threshold }}.</p>

    <a href="{{ route('admin.products.index') }}" class="btn">Manage products</a>
@endsection
