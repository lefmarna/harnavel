import { LayoutGrid } from "lucide-react";

import { dashboard, home } from "@/routes";
import type { NavItem } from "@/types";

const logoHref = home();

const mainNavItems: NavItem[] = [
  {
    title: "ダッシュボード",
    href: dashboard(),
    icon: LayoutGrid,
  },
];

const secondaryNavItems: NavItem[] = [];

// 派生アプリが差し替える。
export function useNavigation() {
  return { logoHref, mainNavItems, secondaryNavItems };
}
