export function hexToRgba(hex: string, alpha: number): string {
  const match = hex.replace('#', '')
  const expanded =
    match.length === 3
      ? match
          .split('')
          .map((c) => c + c)
          .join('')
      : match
  if (!/^[0-9a-fA-F]{6}$/.test(expanded)) return 'rgba(128, 128, 128, 0.12)'
  const num = Number.parseInt(expanded, 16)
  const r = (num >> 16) & 255
  const g = (num >> 8) & 255
  const b = num & 255
  return `rgba(${r}, ${g}, ${b}, ${alpha})`
}