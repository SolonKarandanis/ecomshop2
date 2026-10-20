// Mirrors the backend's order responses.

// App\Data\LabelsData, as returned by GET /api/payment-methods.
export interface PaymentMethodOption {
  id: number
  key: string
  label: string
}

export interface CheckoutPayload {
  firstName: string
  lastName: string
  phone: string
  address: string
  city: string
  country: string
  zipCode: string
  paymentMethod: string
}

// POST /api/checkout: Stripe's hosted payment page, or /success?order_id=… for Cash on Delivery.
export interface CheckoutResponse {
  redirect_url: string
}

// The backend's OrderStatusEnum / OrderPaymentStatusEnum values.
export const PAYMENT_PAID = 'order.payment.status.paid'
export const PAYMENT_PENDING = 'order.payment.status.pending'
export const PAYMENT_FAILED = 'order.payment.status.failed'

// GET /api/success and GET /api/cancel (StripeRedirectController).
export interface OrderPaymentResult {
  order_id: number
  order_status: string
  payment_status: string | null
}

export interface StripeCancelResult extends OrderPaymentResult {
  // The open Stripe session to pay the same Order again, or null once it has
  // expired or the Order is no longer pending.
  retry_url: string | null
}

// App\Data\OrderData, the fields these pages use (GET /api/orders/{order}, wrapped in `data`).
export interface Order {
  id: number
  order_status: string
  payment_status: string | null
  grand_total: number | null
  created_at: string | null
}
