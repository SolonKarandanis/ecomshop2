/** Saves a Blob the API returned as a file, without navigating away. */
export function saveBlob(blob: Blob, filename: string): void {
  const url = URL.createObjectURL(blob)
  const link = document.createElement('a')
  link.href = url
  link.download = filename
  document.body.appendChild(link)
  link.click()
  link.remove()
  URL.revokeObjectURL(url)
}

/**
 * Whether a value is a Blob, by shape rather than `instanceof`. The Blob ofetch builds
 * can come from a different JavaScript realm than the page's global `Blob` (Node's
 * own Blob under happy-dom in CI), and then `instanceof Blob` is false for a real Blob.
 */
function isBlob(value: unknown): value is Blob {
  return typeof value === 'object'
    && value !== null
    && typeof (value as Blob).text === 'function'
    && typeof (value as Blob).size === 'number'
}

/**
 * The message from a failed `responseType: 'blob'` request. ofetch parses the error body
 * with the request's responseType, so Laravel's JSON error arrives as a Blob too.
 */
export async function blobErrorMessage(error: unknown, fallback: string): Promise<string> {
  const data = (error as { data?: unknown } | null)?.data

  if (isBlob(data)) {
    try {
      const body = JSON.parse(await data.text()) as { message?: string }
      return body.message || fallback
    }
    catch {
      return fallback
    }
  }

  return apiErrorMessage(error, fallback)
}
