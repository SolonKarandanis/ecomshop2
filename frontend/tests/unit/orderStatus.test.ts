import { describe, expect, it } from 'vitest'
import { orderStatusLabel, paymentMethodLabel } from '~/utils/orderStatus'

describe('labels', () => {
  it('names known values and falls back to the raw value', () => {
    expect(orderStatusLabel('order.status.shipped')).toBe('Shipped')
    expect(orderStatusLabel('order.status.unknown')).toBe('order.status.unknown')
    expect(paymentMethodLabel('payment.method.cod')).toBe('Cash on Delivery')
    expect(paymentMethodLabel(null)).toBe('—')
  })
})
