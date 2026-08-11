import { createInertiaApp } from "@inertiajs/react";
import { StrictMode } from "react";
import type { ReactNode } from "react";
import type { Root } from "react-dom/client";
import { createRoot } from "react-dom/client";

import { GoogleAnalyticsTracker } from "./components/functional";
import SeoHead from "./components/seo-head";

import "../css/app.css";
import { initializeTheme } from "./hooks/use-appearance";
import { resolveAppName } from "./lib/app-name";

const configuredAppName: unknown = import.meta.env.VITE_APP_NAME;
const appName = resolveAppName(configuredAppName);

type ReactRootElement = HTMLElement & {
  reactRoot?: Root;
};

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
  setup: ({ el, App, props }) => {
    const rootElement = el as ReactRootElement;
    const root = rootElement.reactRoot ?? createRoot(rootElement);

    rootElement.reactRoot = root;

    root.render(
      <StrictMode>
        <App {...props} />
      </StrictMode>,
    );
  },
  strictMode: true,
  progress: {
    color: "#4B5563",
  },
});

// This will set light / dark mode on load...
initializeTheme();
