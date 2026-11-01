import { describe, expect, it } from 'vitest'
import { redirectTarget } from '~/utils/redirectTarget'

describe('redirectTarget', () => {
  it('keeps in-app paths, including their query', () => {
    expect(redirectTarget('/orders/12?tab=items')).toBe('/orders/12?tab=items')
    expect(redirectTarget(['/profile', '/other'])).toBe('/profile')
  })

  it.each([undefined, null, '', 'https://evil.example', '//evil.example', '/\\evil.example'])(
    'falls back to / for %s',
    (value) => {
      expect(redirectTarget(value)).toBe('/')
    },
  )

  it("falls back to the given home page, such as the current language's", () => {
    expect(redirectTarget(undefined, '/el')).toBe('/el')
    expect(redirectTarget('/el/orders', '/el')).toBe('/el/orders')
  })
})
