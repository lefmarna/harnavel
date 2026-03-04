import type { SharedData } from '@/types/auth'

declare module '@inertiajs/core' {
  export interface InertiaConfig {
    sharedPageProps: SharedData
  }
}
