<x-mail::message>
# Order Confirmation

Hello {{ $order->user->name }},

Thank you for your order! We've received it and are currently processing it. You'll receive another email once your items have shipped.

<x-mail::button :url="$url">
View Order Details
</x-mail::button>

## Order Summary

**Order Number:** {{ $order->id }}
**Order Date:** {{ $order->created_at->format('M d, Y H:i') }}
**Payment Status:** {{ \App\Enums\OrderPaymentStatusEnum::labels()[$order->payment_status] ?? $order->payment_status }}

<x-mail::table>
| Item | Quantity | Unit Price | Total Price |
| :--- | :---: | :---: | :---: |
@foreach($order->items as $item)
| {{ $item->product->name }}@php $displayAttributes = $item->getAttributeNamesAndOptions(); @endphp @if(!empty($displayAttributes))<br><small>@foreach($displayAttributes as $name => $option){{ $name }}: {{ $option }}{{ !$loop->last ? ', ' : '' }}@endforeach</small>@endif | {{ $item->quantity }} | {{ \Illuminate\Support\Number::currency($item->unit_amount?->toFloat() ?? 0) }} | {{ \Illuminate\Support\Number::currency($item->total_amount?->toFloat() ?? 0) }} |
@endforeach
| | | **Subtotal** | {{ \Illuminate\Support\Number::currency(($order->grand_total?->cents() - ($order->shipping_amount?->cents() ?? 0)) / 100) }} |
| | | **Shipping** | {{ \Illuminate\Support\Number::currency($order->shipping_amount?->toFloat() ?? 0) }} |
| | | **Total** | **{{ \Illuminate\Support\Number::currency($order->grand_total?->toFloat() ?? 0) }}** |
</x-mail::table>

@if($order->address)
## Shipping Address
{{ $order->address->full_name }}
{{ $order->address->street_address }}
{{ $order->address->city }}, {{ $order->address->postal_code }}
{{ $order->address->country }}
@endif

<x-mail::panel>
If you have any questions, please reply to this email or contact our support team.
</x-mail::panel>

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
