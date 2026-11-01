@php
    // Rendered in the Buyer's locale (#80): amounts in the shop's currency
    // (EUR), formatted for that language, like the storefront's prices.
    $money = fn ($amount) => \Illuminate\Support\Number::currency($amount ?? 0, in: strtoupper(config('app.currency')), locale: app()->getLocale());
@endphp
<x-mail::message>
# {{ __('mail.order_placed.heading') }}

{{ __('mail.greeting', ['name' => $order->user->name]) }}

{{ __('mail.order_placed.intro') }}

<x-mail::button :url="$url">
{{ __('mail.order_placed.button') }}
</x-mail::button>

## {{ __('mail.order_placed.summary') }}

**{{ __('mail.order_placed.number') }}:** {{ $order->id }}
**{{ __('mail.order_placed.date') }}:** {{ $order->created_at->locale(app()->getLocale())->isoFormat('LLL') }}
**{{ __('mail.order_placed.payment_status') }}:** {{ \App\Enums\OrderPaymentStatusEnum::labels()[$order->payment_status] ?? $order->payment_status }}

<x-mail::table>
| {{ __('mail.order_placed.item') }} | {{ __('mail.order_placed.quantity') }} | {{ __('mail.order_placed.unit_price') }} | {{ __('mail.order_placed.total_price') }} |
| :--- | :---: | :---: | :---: |
@foreach($order->items as $item)
| {{ $item->product->name }}@php $displayAttributes = $item->getAttributeNamesAndOptions(); @endphp @if(!empty($displayAttributes))<br><small>@foreach($displayAttributes as $name => $option){{ $name }}: {{ $option }}{{ !$loop->last ? ', ' : '' }}@endforeach</small>@endif | {{ $item->quantity }} | {{ $money($item->unit_amount?->toFloat()) }} | {{ $money($item->total_amount?->toFloat()) }} |
@endforeach
| | | **{{ __('mail.order_placed.subtotal') }}** | {{ $money(($order->grand_total ?? \App\ValueObjects\Money::zero())->subtract($order->shipping_amount ?? \App\ValueObjects\Money::zero())->toFloat()) }} |
| | | **{{ __('mail.order_placed.shipping') }}** | {{ $money($order->shipping_amount?->toFloat()) }} |
| | | **{{ __('mail.order_placed.total') }}** | **{{ $money($order->grand_total?->toFloat()) }}** |
</x-mail::table>

@if($order->address)
## {{ __('mail.order_placed.shipping_address') }}
{{ $order->address->full_name }}
{{ $order->address->street_address }}
{{ $order->address->city }}, {{ $order->address->postal_code }}
{{ $order->address->country }}
@endif

<x-mail::panel>
{{ __('mail.order_placed.questions') }}
</x-mail::panel>

{{ __('mail.thanks') }}<br>
{{ config('app.name') }}
</x-mail::message>
