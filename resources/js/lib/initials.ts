/**
 * 氏名からイニシャルを組み立てる純関数。
 *
 * React に依存しないため、レンダリングせずに node:test で仕様を固定できる
 * （`resources/js/**\/*.test.{ts,tsx}` / `pnpm test:unit`）。
 */
export function getInitials(fullName: string): string {
  const [first, ...rest] = fullName.trim().split(/\s+/)

  if (!first) {
    return ''
  }

  const last = rest[rest.length - 1] ?? ''

  return `${first.charAt(0)}${last.charAt(0)}`.toUpperCase()
}
