import { describe, expect, it } from 'vitest'
import { orderIdFromQuery, successArrival } from '~/utils/successArrival'

describe('successArrival', () => {
  it('recognises a return from Stripe by its session id', () => {
    expect(successArrival({ session_id: 'cs_test_123' })).toEqual({ kind: 'stripe', sessionId: 'cs_test_123' })
  })

  it('prefers the session id if both are present', () => {
    expect(successArrival({ session_id: 'cs_test_123', order_id: '7' })).toEqual({ kind: 'stripe', sessionId: 'cs_test_123' })
  })

  it('recognises a Cash on Delivery checkout by its Order id', () => {
    expect(successArrival({ order_id: '42' })).toEqual({ kind: 'cod', orderId: 42 })
  })

  it('has nothing to show without either', () => {
    expect(successArrival({})).toEqual({ kind: 'none' })
    expect(successArrival({ session_id: '' })).toEqual({ kind: 'none' })
  })
})

describe('orderIdFromQuery', () => {
  it('takes a positive integer, or the first of repeated values', () => {
    expect(orderIdFromQuery({ order_id: '5' })).toBe(5)
    expect(orderIdFromQuery({ order_id: ['8', '9'] })).toBe(8)
  })

  it('rejects anything else', () => {
    for (const value of ['0', '-1', '1.5', 'abc', '12abc', '']) {
      expect(orderIdFromQuery({ order_id: value })).toBeNull()
    }
    expect(orderIdFromQuery({})).toBeNull()
  })
})
