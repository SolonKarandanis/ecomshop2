import type { FormError } from '@nuxt/ui'
import type { FetchError } from 'ofetch'

interface LaravelErrorBody {
  message?: string
  errors?: Record<string, string[]>
}

function isFetchError(error: unknown): error is FetchError {
  return error instanceof Error && error.name === 'FetchError'
}

function errorBody(error: unknown): LaravelErrorBody | undefined {
  return isFetchError(error) ? error.data as LaravelErrorBody | undefined : undefined
}

export function apiStatus(error: unknown): number | undefined {
  return isFetchError(error) ? error.statusCode : undefined
}

/** Laravel's 422 `errors` object, flattened to one message per field. */
export function apiValidationErrors(error: unknown): FormError[] {
  if (apiStatus(error) !== 422) {
    return []
  }

  return Object.entries(errorBody(error)?.errors ?? {})
    .filter(([, messages]) => messages.length > 0)
    .map(([name, messages]) => ({ name, message: messages[0]! }))
}

export function apiErrorMessage(error: unknown, fallback = 'Something went wrong. Please try again.'): string {
  return errorBody(error)?.message || fallback
}
