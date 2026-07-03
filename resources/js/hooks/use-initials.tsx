import { useCallback } from 'react'

export type GetInitialsFn = (fullName: string) => string

export function useInitials(): GetInitialsFn {
  return useCallback((fullName: string): string => {
    const names = fullName.trim().split(' ')
    const first = names[0]

    if (!first) {
      return ''
    }

    const last = names[names.length - 1]

    if (!last || names.length === 1) {
      return first.charAt(0).toUpperCase()
    }

    const firstInitial = first.charAt(0)
    const lastInitial = last.charAt(0)

    return `${firstInitial}${lastInitial}`.toUpperCase()
  }, [])
}
