@extends('emails.layouts.transactional')

@section('title', 'Paid but not ordered')

@section('content')
    <p class="heading">A customer paid, but no order was created</p>
    <p>
        A {{ $provider === 'bkash' ? 'bKash' : 'Nagad' }} payment of <strong>৳{{ number_format($amount, 2) }}</strong>
        (TrxID <strong>{{ $transactionId }}</strong>) was verified, but
        <strong>{{ $unavailableItem ?? 'an item in the order' }}</strong> became unavailable before the order could be confirmed.
        No order was created and no stock was taken.
    </p>

    <table class="items">
        <tr>
            <th>Item</th>
            <th>Qty</th>
        </tr>
        @foreach ($items as $item)
            <tr>
                <td>{{ $item['name'] }}</td>
                <td>{{ $item['quantity'] }}</td>
            </tr>
        @endforeach
    </table>

    <p>Customer: {{ $customerName ?? 'N/A' }} ({{ $customerPhone ?? 'N/A' }})</p>
    <p>Intended order number: {{ $orderNumber ?? 'N/A' }}{{ $landingPage ? ' (landing page checkout)' : '' }}</p>

    <p>
        Before refunding, check Admin › Received SMS: if the customer later completed this order, the SMS will show as matched to it.
        Otherwise contact the customer to refund or arrange a replacement.
    </p>

    <a href="{{ route('admin.received-sms.index', ['matched' => 'no']) }}" class="btn">Open Received SMS</a>
@endsection
