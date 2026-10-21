import { describe, expect, it } from 'vitest'
import { orderStatusLabel, paymentMethodLabel, supplierActionsFor } from '~/utils/orderStatus'

describe('supplierActionsFor', () => {
  it('offers Ship and Cancel on a Paid Order, Deliver on a Shipped one, and nothing otherwise', () => {
    expect(supplierActionsFor('order.status.paid')).toEqual(['ship', 'cancel'])
    expect(supplierActionsFor('order.status.shipped')).toEqual(['deliver'])
    for (const status of ['order.status.draft', 'order.status.delivered', 'order.status.cancelled']) {
      expect(supplierActionsFor(status)).toEqual([])
    }
  })
})

describe('labels', () => {
  it('names known values and falls back to the raw value', () => {
    expect(orderStatusLabel('order.status.shipped')).toBe('Shipped')
    expect(orderStatusLabel('order.status.unknown')).toBe('order.status.unknown')
    expect(paymentMethodLabel('payment.method.cod')).toBe('Cash on Delivery')
    expect(paymentMethodLabel(null)).toBe('—')
  })
})
