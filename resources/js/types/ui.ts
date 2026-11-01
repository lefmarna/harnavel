import type { ReactNode } from "react";

import type { BreadcrumbItem } from "./navigation";

export type AppLayoutProps = {
  children: ReactNode;
  breadcrumbs?: BreadcrumbItem[];
};

export type AuthLayoutProps = {
  children?: ReactNode;
  title?: string;
  description?: string;
};
