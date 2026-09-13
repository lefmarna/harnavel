export type User = {
  id: number;
  name: string;
  email: string;
  avatar: string | null;
  email_verified_at: string | null;
  created_at: string;
  updated_at: string;
};

export type Passkey = {
  id: number;
  name: string;
  authenticator: string | null;
  created_at_diff: string;
  last_used_at_diff: string | null;
};

export type Auth = {
  user: User | null;
  hasPassword: boolean;
};

export type Tracking = {
  trackingEnabled: boolean;
  adsEnabled: boolean;
  adsensePublisherId: string | null;
  adsTestMode: boolean;
};

export type SeoData = {
  title?: string;
  description?: string;
  robots?: string;
  googlebot?: string;
  image?: string;
  imageAlt?: string;
  url?: string;
  type?: "website" | "article";
};

export type SharedData = {
  name: string;
  url: string;
  currentUrl: string;
  appDisplayName: string;
  appDescription: string;
  defaultRobots?: string | null;
  seo: SeoData;
  auth: Auth;
  tracking: Tracking;
  sidebarOpen: boolean;
  flash: {
    status: string | null;
    error: string | null;
  };
  [key: string]: unknown;
};
