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
  // Also add the shipping address to the Buyer's address book.
  saveAddress: boolean
}

// POST /api/checkout: Stripe's hosted payment page, or /success?order_id=… for Cash on Delivery.
export interface CheckoutResponse {
  redirect_url: string
}

// The backend's OrderStatusEnum / OrderPaymentStatusEnum values.
export const ORDER_DRAFT = 'order.status.draft'
export const ORDER_PAID = 'order.status.paid'
export const ORDER_SHIPPED = 'order.status.shipped'
export const ORDER_DELIVERED = 'order.status.delivered'
export const ORDER_CANCELLED = 'order.status.cancelled'
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

// App\Data\AddressData.
export interface OrderAddress {
  first_name: string
  last_name: string
  phone: string
  street_address: string
  city: string
  country: string
  postal_code: string
}

// App\Data\OrderItemData, with the product loaded.
export interface OrderItem {
  id: number
  product_id: number
  product: { id: number, name: string, slug: string } | null
  quantity: number
  unit_amount: number | null
  total_amount: number | null
  // attribute name → chosen option name
  attribute_labels: Record<string, string>
}

// App\Data\OrderStatusHistoryData: one status change, oldest first.
export interface OrderStatusHistoryEntry {
  order_status: string
  payment_status: string | null
  created_at: string | null
}

// GET /api/orders/{order}: OrderData with its relations loaded.
export interface OrderDetail extends Order {
  currency: string | null
  shipping_amount: number | null
  notes: string | null
  payment_method: string | null
  address: OrderAddress | null
  items: OrderItem[]
  status_history: OrderStatusHistoryEntry[]
}
