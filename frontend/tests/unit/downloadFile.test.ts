import { describe, expect, it } from 'vitest'
import { blobErrorMessage } from '~/utils/downloadFile'

// A Blob from another JavaScript realm: it behaves like a Blob but isn't an
// instance of this realm's global `Blob`, as happens with ofetch under happy-dom in CI.
function foreignBlob(body: string): unknown {
  return { size: body.length, type: 'application/json', text: async () => body }
}

describe('blobErrorMessage', () => {
  it("reads Laravel's message from a Blob error body, even one from another realm", async () => {
    const body = foreignBlob(JSON.stringify({ message: 'You cannot export more than 10,000 orders at once.' }))

    expect(body instanceof Blob).toBe(false)
    expect(await blobErrorMessage({ data: body }, 'fallback')).toBe('You cannot export more than 10,000 orders at once.')
  })

  it('reads the message from a same-realm Blob', async () => {
    const body = new Blob([JSON.stringify({ message: 'Nope' })], { type: 'application/json' })

    expect(await blobErrorMessage({ data: body }, 'fallback')).toBe('Nope')
  })

  it('falls back when the Blob body is not JSON or has no message', async () => {
    expect(await blobErrorMessage({ data: foreignBlob('<html>') }, 'fallback')).toBe('fallback')
    expect(await blobErrorMessage({ data: foreignBlob('{}') }, 'fallback')).toBe('fallback')
  })
})
