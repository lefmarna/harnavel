export type User = {
  id: number
  name: string
  email: string
  avatar?: string
  email_verified_at: string | null
  two_factor_enabled?: boolean
  created_at: string
  updated_at: string
  [key: string]: unknown
}

export type Auth = {
  user: User | null
  hasPassword: boolean
}

export type SeoData = {
  title?: string
  description?: string
}

export type SharedData = {
  name: string
  auth: Auth
  sidebarOpen: boolean
  appDisplayName: string
  appDescription: string
  seo?: SeoData
  [key: string]: unknown
}

export type TwoFactorSetupData = {
  svg: string
  url: string
}

export type TwoFactorSecretKey = {
  secretKey: string
}
