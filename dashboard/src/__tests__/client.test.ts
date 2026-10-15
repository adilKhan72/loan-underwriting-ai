import { describe, expect, it, vi } from 'vitest'
import { ApiClient, ApiError } from '../api/client'

function fakeFetch(status: number, body: unknown) {
  return vi.fn(async () => new Response(JSON.stringify(body), { status, headers: { 'Content-Type': 'application/json' } }))
}

describe('ApiClient', () => {
  it('sends the bearer token and status filter', async () => {
    const fetch = fakeFetch(200, { data: [] })
    await new ApiClient('http://api', 'tok', fetch).listApplications('decided')

    const [url, init] = fetch.mock.calls[0] as unknown as [string, RequestInit]
    expect(url).toBe('http://api/api/applications?status=decided')
    expect((init.headers as Record<string, string>).Authorization).toBe('Bearer tok')
  })

  it('maps Laravel validation errors onto ApiError', async () => {
    const fetch = fakeFetch(422, { message: 'Invalid', errors: { 'applicant.ssn': ['The SSN must be 9 digits.'] } })

    const error = await new ApiClient('http://api', 'tok', fetch).submit('x').catch((e) => e)

    expect(error).toBeInstanceOf(ApiError)
    expect(error.status).toBe(422)
    expect(error.fieldErrors['applicant.ssn']).toEqual(['The SSN must be 9 digits.'])
  })

  it('uploads documents as multipart without forcing a content type', async () => {
    const fetch = fakeFetch(201, { data: { id: 'd1' } })
    const file = new File(['%PDF'], 'stub.pdf', { type: 'application/pdf' })

    await new ApiClient('http://api', 'tok', fetch).uploadDocument('a1', 'paystub', file)

    const [url, init] = fetch.mock.calls[0] as unknown as [string, RequestInit]
    expect(url).toBe('http://api/api/applications/a1/documents')
    expect(init.body).toBeInstanceOf(FormData)
    expect((init.body as FormData).get('type')).toBe('paystub')
    expect(init.headers).not.toHaveProperty('Content-Type')
  })

  it('returns audit events with the chain status', async () => {
    const fetch = fakeFetch(200, { data: [{ id: 'e1' }], chain_intact: false })

    const audit = await new ApiClient('http://api', 'tok', fetch).getAudit('a1')

    expect(audit.chainIntact).toBe(false)
    expect(audit.events).toHaveLength(1)
  })
})
