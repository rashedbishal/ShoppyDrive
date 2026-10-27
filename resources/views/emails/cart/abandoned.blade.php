@extends('emails.layouts.transactional')

@section('title', 'You left something in your cart')

@section('content')
    <p class="heading">You left something behind!</p>
    <p>Hi {{ $incompleteOrder->name ?: 'there' }}, you had these items waiting for you at checkout:</p>

    <table class="items">
        <tr>
            <th>Item</th>
            <th>Qty</th>
            <th class="num">Price</th>
        </tr>
        @foreach ($incompleteOrder->items as $item)
            <tr>
                <td>{{ $item['product_name'] }}</td>
                <td>{{ $item['quantity'] }}</td>
                <td class="num">৳{{ number_format($item['subtotal'], 2) }}</td>
            </tr>
        @endforeach
    </table>

    <a href="{{ route('checkout.index') }}" class="btn">Complete your order</a>
@endsection
