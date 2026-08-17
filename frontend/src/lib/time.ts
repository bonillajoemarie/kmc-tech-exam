const rtf = new Intl.RelativeTimeFormat('en', { numeric: 'auto' })

const units: Array<[Intl.RelativeTimeFormatUnit, number]> = [
  ['year', 31536000],
  ['month', 2592000],
  ['week', 604800],
  ['day', 86400],
  ['hour', 3600],
  ['minute', 60],
  ['second', 1],
]

const dateFormatter = new Intl.DateTimeFormat('en-US', {
  month: 'short',
  day: 'numeric',
  year: 'numeric',
  timeZone: 'UTC',
})

export function formatRelativeTime(iso: string, now: number = Date.now()): string {
  const timestamp = new Date(iso).getTime()
  if (Number.isNaN(timestamp)) return ''

  const diffSeconds = Math.round((timestamp - now) / 1000)

  if (Math.abs(diffSeconds) < 30 * 86400) {
    for (const [unit, seconds] of units) {
      if (Math.abs(diffSeconds) >= seconds) {
        return rtf.format(Math.round(diffSeconds / seconds), unit)
      }
    }
  }

  return dateFormatter.format(new Date(timestamp))
}
