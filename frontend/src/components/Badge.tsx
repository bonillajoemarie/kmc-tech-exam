import { hexToRgba } from '../lib/color'

export function Badge({ name, color }: { name: string; color: string }) {
  return (
    <span
      className="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium whitespace-nowrap"
      style={{ color, backgroundColor: hexToRgba(color, 0.12) }}
    >
      {name}
    </span>
  )
}