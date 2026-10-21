import { LayoutGrid } from "lucide-react";

import { dashboard, home } from "@/routes";
import type { NavItem } from "@/types";

// 派生アプリが差し替える。
export const logoHref = home();

export const mainNavItems: NavItem[] = [
  {
    title: "ダッシュボード",
    href: dashboard(),
    icon: LayoutGrid,
  },
];

export const secondaryNavItems: NavItem[] = [];
