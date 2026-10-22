import { describe, expect, it } from 'vitest'
import { slugify } from '~/utils/slugify'

describe('slugify', () => {
  it('lower-cases and joins words with single dashes', () => {
    expect(slugify('Aurora  Laptop 14')).toBe('aurora-laptop-14')
  })

  it('drops accents and punctuation, and trims dashes at the ends', () => {
    expect(slugify(' Café — "Crème" Brûlée! ')).toBe('cafe-creme-brulee')
  })

  it('spells out @ like Laravel\'s Str::slug', () => {
    expect(slugify('me@home')).toBe('me-at-home')
  })

  it('returns an empty string when nothing is left', () => {
    expect(slugify('!!!')).toBe('')
  })
})
