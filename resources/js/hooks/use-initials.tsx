export type GetInitialsFn = (fullName: string) => string

/**
 * 氏名からイニシャルを組み立てる純関数。
 *
 * フックと分離しているのは、React をレンダリングせずに node:test で仕様を固定できるようにするため
 * （`resources/js/**\/*.test.{ts,tsx}` / `pnpm test:unit`）。
 */
export function getInitials(fullName: string): string {
  const names = fullName.trim().split(' ')
  const first = names[0]

  if (!first) {
    return ''
  }

  const last = names[names.length - 1]

  if (!last || names.length === 1) {
    return first.charAt(0).toUpperCase()
  }

  return `${first.charAt(0)}${last.charAt(0)}`.toUpperCase()
}

/**
 * モジュールスコープの関数をそのまま返す。参照はレンダリング間で安定しているため
 * useCallback は不要。
 */
export function useInitials(): GetInitialsFn {
  return getInitials
}
