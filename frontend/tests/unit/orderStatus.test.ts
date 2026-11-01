import { afterEach, describe, expect, it } from 'vitest'
import { orderStatusLabel, paymentMethodLabel, paymentStatusLabel } from '~/utils/orderStatus'

afterEach(async () => {
  await useNuxtApp().$i18n.setLocale('en')
})

describe('labels', () => {
  it('names known values and falls back to the raw value', () => {
    expect(orderStatusLabel('order.status.shipped')).toBe('Shipped')
    expect(orderStatusLabel('order.status.unknown')).toBe('order.status.unknown')
    expect(paymentMethodLabel('payment.method.cod')).toBe('Cash on Delivery')
    expect(paymentMethodLabel(null)).toBe('—')
  })

  it('names them in the active language, from the backend values themselves', async () => {
    await useNuxtApp().$i18n.setLocale('el')
    expect(orderStatusLabel('order.status.paid')).toBe('Πληρωμένη')
    expect(paymentStatusLabel('order.payment.status.pending')).toBe('Σε εκκρεμότητα')
    expect(paymentMethodLabel('payment.method.cod')).toBe('Αντικαταβολή')

    await useNuxtApp().$i18n.setLocale('tr')
    expect(orderStatusLabel('order.status.shipped')).toBe('Kargoya verildi')
    expect(orderStatusLabel('order.status.unknown')).toBe('order.status.unknown')
  })
})
