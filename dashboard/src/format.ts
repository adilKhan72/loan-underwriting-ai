const usd = new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' })

export function money(cents: number | null | undefined): string {
  return cents == null ? '—' : usd.format(cents / 100)
}

export function dateTime(iso: string | null | undefined): string {
  return iso ? new Date(iso).toLocaleString('en-US', { dateStyle: 'medium', timeStyle: 'short' }) : '—'
}

export function label(value: string): string {
  return value.replace(/_/g, ' ')
}
