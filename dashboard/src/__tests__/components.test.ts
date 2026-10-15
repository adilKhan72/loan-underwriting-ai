import { mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'
import type { Decision, RuleResult } from '../api/types'
import AuditTimeline from '../components/AuditTimeline.vue'
import DecisionPanel from '../components/DecisionPanel.vue'
import RuleResultsTable from '../components/RuleResultsTable.vue'
import { money } from '../format'

const rules: RuleResult[] = [
  { code: 'PAY_DATE_RECENT', outcome: 'pass', message: 'ok', values: {} },
  { code: 'OVERDRAFT_HISTORY', outcome: 'skip', message: 'no statement', values: {} },
  { code: 'MIN_VERIFIED_INCOME', outcome: 'fail', message: 'too low', values: {} },
  { code: 'PAYMENT_TO_INCOME', outcome: 'refer', message: 'high', values: {} },
]

function decision(status: Decision['explanation']['status']): Decision {
  return {
    id: 'd1', outcome: 'decline', policy_version: 'v1', rule_results: rules, decided_at: '2026-10-15T10:00:00Z',
    explanation: {
      status,
      underwriter_summary: status === 'ready' ? 'Declined under MIN_VERIFIED_INCOME.' : null,
      applicant_message: status === 'ready' ? 'We cannot approve your loan.' : null,
      principal_reasons: status === 'ready' ? ['Income too low.'] : [],
      provider: 'fixture',
    },
  }
}

describe('RuleResultsTable', () => {
  it('lists failing and referring rules first and counts outcomes', () => {
    const wrapper = mount(RuleResultsTable, { props: { results: rules } })

    const codes = wrapper.findAll('tbody tr').map((row) => row.find('code').text())
    expect(codes).toEqual(['MIN_VERIFIED_INCOME', 'PAYMENT_TO_INCOME', 'PAY_DATE_RECENT', 'OVERDRAFT_HISTORY'])
    expect(wrapper.find('[data-test=counts]').text()).toBe('1 fail · 1 refer · 1 pass · 1 skip')
  })
})

describe('DecisionPanel', () => {
  it('shows both explanations and the principal reasons when ready', () => {
    const wrapper = mount(DecisionPanel, { props: { decision: decision('ready') } })

    expect(wrapper.find('[data-test=explanation]').text()).toContain('We cannot approve your loan.')
    expect(wrapper.text()).toContain('Income too low.')
  })

  it('still shows the decision and rules while the explanation is pending or unavailable', () => {
    expect(mount(DecisionPanel, { props: { decision: decision('pending') } }).find('[data-test=explanation-pending]').exists()).toBe(true)

    const unavailable = mount(DecisionPanel, { props: { decision: decision('unavailable') } })
    expect(unavailable.find('[data-test=explanation-unavailable]').exists()).toBe(true)
    expect(unavailable.findAll('tbody tr')).toHaveLength(4)
  })
})

describe('AuditTimeline', () => {
  it('warns loudly when the hash chain is broken', () => {
    const wrapper = mount(AuditTimeline, { props: { events: [], chainIntact: false } })

    expect(wrapper.find('[data-test=chain]').text()).toContain('tampered')
  })
})

describe('money', () => {
  it('formats integer cents as dollars', () => {
    expect(money(150_070)).toBe('$1,500.70')
    expect(money(null)).toBe('—')
  })
})
