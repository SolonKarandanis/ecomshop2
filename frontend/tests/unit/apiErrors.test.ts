import { describe, expect, it } from 'vitest'
import { FetchError } from 'ofetch'
import { apiErrorMessage, apiStatus, apiValidationErrors } from '~/utils/apiErrors'

function fetchError(statusCode: number, data: unknown): FetchError {
  const error = new FetchError('Request failed')
  error.statusCode = statusCode
  error.data = data
  return error
}

describe('apiValidationErrors', () => {
  it('flattens a Laravel 422 body to the first message per field', () => {
    const error = fetchError(422, {
      message: 'The email field is required. (and 1 more error)',
      errors: { email: ['The email field is required.', 'ignored'], password: ['Too short.'] },
    })

    expect(apiValidationErrors(error)).toEqual([
      { name: 'email', message: 'The email field is required.' },
      { name: 'password', message: 'Too short.' },
    ])
  })

  it('returns nothing for non-422 errors or non-fetch errors', () => {
    expect(apiValidationErrors(fetchError(500, { errors: { email: ['x'] } }))).toEqual([])
    expect(apiValidationErrors(new Error('boom'))).toEqual([])
  })
})

describe('apiErrorMessage / apiStatus', () => {
  it('uses the API message when there is one, else the fallback', () => {
    expect(apiErrorMessage(fetchError(422, { message: 'Invalid token.' }))).toBe('Invalid token.')
    expect(apiErrorMessage(new Error('boom'), 'Fallback')).toBe('Fallback')
  })

  it('reads the status of fetch errors only', () => {
    expect(apiStatus(fetchError(401, null))).toBe(401)
    expect(apiStatus(new Error('boom'))).toBeUndefined()
  })
})
