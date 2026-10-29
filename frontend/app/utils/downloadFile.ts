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
 * The message from a failed `responseType: 'blob'` request. ofetch parses the error body
 * with the request's responseType, so Laravel's JSON error arrives as a Blob too.
 */
export async function blobErrorMessage(error: unknown, fallback: string): Promise<string> {
  const data = (error as { data?: unknown } | null)?.data

  if (data instanceof Blob) {
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
