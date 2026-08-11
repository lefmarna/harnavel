import { usePage } from "@inertiajs/react";
import { useEffect, useRef } from "react";

import type { SharedData } from "@/types";

declare global {
  interface Window {
    gtag?: (...args: unknown[]) => void;
  }
}

// page_view はアプリ側で手動管理する。
// GA4 管理画面の Enhanced Measurement「ブラウザ履歴イベントに基づくページの変更」は
// 必ず OFF にすること（有効のままだと遷移ごとに二重計測になる）。
export function GoogleAnalyticsTracker() {
  const page = usePage<SharedData>();
  const { tracking } = page.props;
  const lastTrackedPathRef = useRef<string | null>(null);

  useEffect(() => {
    if (!tracking.trackingEnabled || typeof window.gtag !== "function") {
      return;
    }

    const url = new URL(page.url, window.location.origin);
    const path = `${url.pathname}${url.search}`;

    if (lastTrackedPathRef.current === path) {
      return;
    }

    lastTrackedPathRef.current = path;

    window.gtag("event", "page_view", {
      page_title: document.title,
      page_location: url.toString(),
      page_path: path,
    });
  }, [page.url, tracking.trackingEnabled]);

  return null;
}
