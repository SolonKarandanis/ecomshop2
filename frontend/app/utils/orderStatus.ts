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
import type { SupplierAction } from '~/types/order'

type BadgeColor = 'neutral' | 'info' | 'primary' | 'success' | 'warning' | 'error'

// Labels come from the message files, keyed by the backend's own values
// (order.status.paid, payment.method.cod), so only the colours live here.
const ORDER_STATUS_COLORS: Record<string, BadgeColor> = {
  [ORDER_DRAFT]: 'neutral',
  [ORDER_PAID]: 'info',
  [ORDER_SHIPPED]: 'primary',
  [ORDER_DELIVERED]: 'success',
  [ORDER_CANCELLED]: 'error',
}

const PAYMENT_STATUS_COLORS: Record<string, BadgeColor> = {
  [PAYMENT_PENDING]: 'warning',
  [PAYMENT_PAID]: 'success',
  [PAYMENT_FAILED]: 'error',
}

// A value the frontend has no message for yet is shown as it came.
export function orderStatusLabel(status: string): string {
  return translateOr(status, status)
}

export function orderStatusColor(status: string): BadgeColor {
  return ORDER_STATUS_COLORS[status] ?? 'neutral'
}

export function paymentStatusLabel(status: string | null): string {
  return status ? translateOr(status, status) : '—'
}

export function paymentStatusColor(status: string | null): BadgeColor {
  return (status && PAYMENT_STATUS_COLORS[status]) || 'neutral'
}

export function paymentMethodLabel(method: string | null): string {
  return method ? translateOr(method, method) : '—'
}

export type { SupplierAction } from '~/types/order'

// The status each action moves the Order to (POST /supplier-orders/{order}/{action}).
export const SUPPLIER_ACTION_TARGET: Record<SupplierAction, string> = {
  ship: ORDER_SHIPPED,
  deliver: ORDER_DELIVERED,
  cancel: ORDER_CANCELLED,
}
