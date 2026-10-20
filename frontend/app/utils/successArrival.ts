import type { LocationQuery } from 'vue-router'

/**
 * How the Buyer reached /success (ADR-0004): back from Stripe with the
 * Checkout Session id, or straight from a Cash on Delivery checkout with the
 * Order id. Anything else has no Order to show.
 */
export type SuccessArrival
  = | { kind: 'stripe', sessionId: string }
    | { kind: 'cod', orderId: number }
    | { kind: 'none' }

function single(value: LocationQuery[string]): string | null {
  const first = Array.isArray(value) ? value[0] : value
  return first || null
}

export function successArrival(query: LocationQuery): SuccessArrival {
  const sessionId = single(query.session_id)
  if (sessionId) {
    return { kind: 'stripe', sessionId }
  }

  const orderId = orderIdFromQuery(query)
  return orderId === null ? { kind: 'none' } : { kind: 'cod', orderId }
}

/** A positive integer `order_id` from the query, or null. */
export function orderIdFromQuery(query: LocationQuery): number | null {
  const raw = single(query.order_id)
  return raw && /^[1-9]\d*$/.test(raw) ? Number(raw) : null
}
