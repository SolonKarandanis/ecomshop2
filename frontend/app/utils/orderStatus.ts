import {
  ORDER_CANCELLED,
  ORDER_DELIVERED,
  ORDER_DRAFT,
  ORDER_PAID,
  ORDER_SHIPPED,
  PAYMENT_FAILED,
  PAYMENT_PAID,
  PAYMENT_PENDING,
} from '~/types/order'

type BadgeColor = 'neutral' | 'info' | 'primary' | 'success' | 'warning' | 'error'

const ORDER_STATUSES: Record<string, { label: string, color: BadgeColor }> = {
  [ORDER_DRAFT]: { label: 'Draft', color: 'neutral' },
  [ORDER_PAID]: { label: 'Paid', color: 'info' },
  [ORDER_SHIPPED]: { label: 'Shipped', color: 'primary' },
  [ORDER_DELIVERED]: { label: 'Delivered', color: 'success' },
  [ORDER_CANCELLED]: { label: 'Cancelled', color: 'error' },
}

const PAYMENT_STATUSES: Record<string, { label: string, color: BadgeColor }> = {
  [PAYMENT_PENDING]: { label: 'Pending', color: 'warning' },
  [PAYMENT_PAID]: { label: 'Paid', color: 'success' },
  [PAYMENT_FAILED]: { label: 'Failed', color: 'error' },
}

const PAYMENT_METHODS: Record<string, string> = {
  'payment.method.cod': 'Cash on Delivery',
  'payment.method.stripe': 'Stripe',
  'payment.method.paypal': 'PayPal',
}

export function orderStatusLabel(status: string): string {
  return ORDER_STATUSES[status]?.label ?? status
}

export function orderStatusColor(status: string): BadgeColor {
  return ORDER_STATUSES[status]?.color ?? 'neutral'
}

export function paymentStatusLabel(status: string | null): string {
  return status ? PAYMENT_STATUSES[status]?.label ?? status : '—'
}

export function paymentStatusColor(status: string | null): BadgeColor {
  return (status && PAYMENT_STATUSES[status]?.color) || 'neutral'
}

export function paymentMethodLabel(method: string | null): string {
  return method ? PAYMENT_METHODS[method] ?? method : '—'
}

export type SupplierAction = 'ship' | 'deliver' | 'cancel'

// The status each action moves the Order to (POST /supplier-orders/{order}/{action}).
export const SUPPLIER_ACTION_TARGET: Record<SupplierAction, string> = {
  ship: ORDER_SHIPPED,
  deliver: ORDER_DELIVERED,
  cancel: ORDER_CANCELLED,
}

/** Mirrors OrderService::isLegalSupplierTransition: Paid → Shipped/Cancelled, Shipped → Delivered. */
export function supplierActionsFor(status: string): SupplierAction[] {
  switch (status) {
    case ORDER_PAID: return ['ship', 'cancel']
    case ORDER_SHIPPED: return ['deliver']
    default: return []
  }
}
