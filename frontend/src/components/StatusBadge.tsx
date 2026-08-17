import type { Status } from '../types'
import { Badge } from './Badge'

export function StatusBadge({ status }: { status: Status }) {
  return <Badge name={status.name} color={status.color} />
}

export function PriorityBadge({ name, color }: { name: string; color: string }) {
  return <Badge name={name} color={color} />
}