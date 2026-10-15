// Mirrors the Laravel API resources (api/app/Http/Resources). Money is integer cents everywhere.

export type ApplicationStatus = 'draft' | 'submitted' | 'processing' | 'decided'
export type DocumentType = 'paystub' | 'bank_statement'
export type DocumentStatus = 'uploaded' | 'processing' | 'extracted' | 'needs_review' | 'failed'
export type RuleOutcome = 'pass' | 'refer' | 'fail' | 'skip'
export type DecisionOutcome = 'approve' | 'refer' | 'decline'
export type ExplanationStatus = 'pending' | 'ready' | 'unavailable'

export interface Applicant {
  id: string
  first_name: string
  last_name: string
  email: string
  phone: string | null
  ssn_masked: string
}

export interface Extraction {
  status: 'ok' | 'needs_review'
  data: Record<string, unknown> | null
  errors: string[]
  redactions: Record<string, number>
  provider: string | null
  attempts: number
  created_at: string
}

export interface LoanDocument {
  id: string
  type: DocumentType
  status: DocumentStatus
  original_name: string
  mime_type: string
  size_bytes: number
  sha256: string
  uploaded_at: string
  extraction?: Extraction | null
}

export interface RuleResult {
  code: string
  outcome: RuleOutcome
  message: string
  values: Record<string, unknown>
}

export interface Decision {
  id: string
  outcome: DecisionOutcome
  policy_version: string
  rule_results: RuleResult[]
  explanation: {
    status: ExplanationStatus
    underwriter_summary: string | null
    applicant_message: string | null
    principal_reasons: string[]
    provider: string | null
  }
  decided_at: string
}

export interface LoanApplication {
  id: string
  status: ApplicationStatus
  amount_requested_cents: number
  term_months: number
  purpose: string | null
  stated_monthly_income_cents: number
  applicant?: Applicant
  documents?: LoanDocument[]
  decision?: Decision | null
  submitted_at: string | null
  created_at: string
}

export interface AuditEvent {
  id: string
  position: number
  event: string
  actor: { type: 'user' | 'system'; id: string | null }
  data: Record<string, unknown>
  hash: string
  prev_hash: string | null
  occurred_at: string
}

export interface NewApplication {
  applicant: {
    first_name: string
    last_name: string
    email: string
    phone?: string
    date_of_birth: string
    ssn: string
  }
  amount_requested_cents: number
  term_months: number
  purpose?: string
  stated_monthly_income_cents: number
}
