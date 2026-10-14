import { describe, expect, it } from 'vitest'
import { DEFAULT_FILTERS, filtersFromQuery, queryFromFilters } from '~/utils/catalogueQuery'

describe('filtersFromQuery', () => {
  it('reads every filter from the URL', () => {
    expect(filtersFromQuery({ 'q': ' lamp ', 'sort': 'price_asc', 'categories[]': ['3', '7'], 'page': '2' }))
      .toEqual({ q: 'lamp', sort: 'price_asc', categories: [3, 7], page: 2 })
  })

  it('accepts a single category as a plain string', () => {
    expect(filtersFromQuery({ 'categories[]': '4' }).categories).toEqual([4])
  })

  it('falls back to defaults for values the API would reject', () => {
    expect(filtersFromQuery({ 'sort': 'bogus', 'page': '-1', 'categories[]': ['x', '0', '5'] }))
      .toEqual({ ...DEFAULT_FILTERS, categories: [5] })
  })
})

describe('queryFromFilters', () => {
  it('writes arrays with the [] suffix Laravel needs', () => {
    expect(queryFromFilters({ ...DEFAULT_FILTERS, categories: [1, 2] }))
      .toEqual({ 'categories[]': ['1', '2'] })
  })

  it('omits defaults to keep URLs clean', () => {
    expect(queryFromFilters(DEFAULT_FILTERS)).toEqual({})
  })

  it('round-trips through the URL', () => {
    const filters = { q: 'desk', sort: 'price_desc' as const, categories: [9], page: 3 }
    expect(filtersFromQuery(queryFromFilters(filters))).toEqual(filters)
  })
})
