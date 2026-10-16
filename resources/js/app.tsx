import { createInertiaApp } from "@inertiajs/react";
import type { ReactNode } from "react";

import { GoogleAnalyticsTracker } from "./components/functional";
import SeoHead from "./components/seo-head";

import "../css/app.css";
import { initializeTheme } from "./hooks/use-appearance";
import { resolveAppName } from "./lib/app-name";

const configuredAppName: unknown = import.meta.env.VITE_APP_NAME;
const appName = resolveAppName(configuredAppName);

function SeoWrapper({ children }: { children: ReactNode }) {
  return (
    <>
      <SeoHead />
      <GoogleAnalyticsTracker />
      {children}
    </>
  );
}

void createInertiaApp({
  title: (title) => (title ? `${title} - ${appName}` : appName),
  layout: () => SeoWrapper,
  strictMode: true,
  progress: {
    color: "#4B5563",
  },
});

initializeTheme();
