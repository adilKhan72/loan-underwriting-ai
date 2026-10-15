import type { AuditEvent, DocumentType, LoanApplication, LoanDocument, NewApplication, ApplicationStatus } from './types'

export class ApiError extends Error {
  readonly status: number
  /** Laravel validation errors: field → messages. */
  readonly fieldErrors: Record<string, string[]>

  constructor(status: number, message: string, fieldErrors: Record<string, string[]> = {}) {
    super(message)
    this.status = status
    this.fieldErrors = fieldErrors
  }
}

type Fetch = typeof fetch

export class ApiClient {
  private readonly baseUrl: string
  private readonly token: string
  private readonly fetchImpl: Fetch

  constructor(baseUrl: string, token: string, fetchImpl: Fetch = (...args) => fetch(...args)) {
    this.baseUrl = baseUrl
    this.token = token
    this.fetchImpl = fetchImpl
  }

  private async request<T>(path: string, init: RequestInit = {}): Promise<T> {
    const response = await this.fetchImpl(`${this.baseUrl}/api${path}`, {
      ...init,
      headers: { Accept: 'application/json', Authorization: `Bearer ${this.token}`, ...init.headers },
    })

    const body = response.status === 204 ? null : await response.json().catch(() => null)

    if (!response.ok) {
      throw new ApiError(response.status, body?.message ?? `Request failed (${response.status})`, body?.errors ?? {})
    }
    return body as T
  }

  private json<T>(path: string, method: string, payload: unknown): Promise<T> {
    return this.request<T>(path, { method, headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload) })
  }

  async listApplications(status?: ApplicationStatus): Promise<LoanApplication[]> {
    const query = status ? `?status=${encodeURIComponent(status)}` : ''
    return (await this.request<{ data: LoanApplication[] }>(`/applications${query}`)).data
  }

  async getApplication(id: string): Promise<LoanApplication> {
    return (await this.request<{ data: LoanApplication }>(`/applications/${id}`)).data
  }

  async createApplication(payload: NewApplication): Promise<LoanApplication> {
    return (await this.json<{ data: LoanApplication }>('/applications', 'POST', payload)).data
  }

  async uploadDocument(applicationId: string, type: DocumentType, file: File): Promise<LoanDocument> {
    const form = new FormData()
    form.append('type', type)
    form.append('file', file)
    // No Content-Type header: the browser sets the multipart boundary.
    return (await this.request<{ data: LoanDocument }>(`/applications/${applicationId}/documents`, { method: 'POST', body: form })).data
  }

  async submit(applicationId: string): Promise<LoanApplication> {
    return (await this.json<{ data: LoanApplication }>(`/applications/${applicationId}/submit`, 'POST', {})).data
  }

  async getAudit(applicationId: string): Promise<{ events: AuditEvent[]; chainIntact: boolean }> {
    const body = await this.request<{ data: AuditEvent[]; chain_intact: boolean }>(`/applications/${applicationId}/audit`)
    return { events: body.data, chainIntact: body.chain_intact }
  }
}
