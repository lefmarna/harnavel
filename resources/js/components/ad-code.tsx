import { usePage } from "@inertiajs/react";
import { useLayoutEffect } from "react";
import type { CSSProperties } from "react";

import type { SharedData } from "@/types";

interface AdCodeProps {
  slot: string;
  style?: CSSProperties;
  format?: string;
  responsive?: string;
  layoutKey?: string;
}

declare global {
  interface Window {
    adsbygoogle: unknown[];
  }
}

export const AdCode = (props: AdCodeProps) => {
  const { tracking } = usePage<SharedData>().props;
  const {
    slot,
    style = { display: "block" },
    format = undefined,
    responsive = undefined,
    layoutKey = undefined,
  } = props;

  useLayoutEffect(() => {
    if (import.meta.env.DEV) {
      return;
    }

    if (typeof window === "undefined") {
      return;
    }

    if (!tracking.adsEnabled || !tracking.adsensePublisherId) {
      return;
    }

    try {
      (window.adsbygoogle = window.adsbygoogle || []).push({});
    } catch (e) {
      console.error(e);
    }
  }, [tracking.adsEnabled, tracking.adsensePublisherId]);

  if (import.meta.env.DEV) {
    return null;
  }

  if (!tracking.adsEnabled || !tracking.adsensePublisherId) {
    return null;
  }

  const adTestMode = tracking.adsTestMode ? "on" : "off";

  return (
    <ins
      className="adsbygoogle"
      style={style}
      data-ad-client={tracking.adsensePublisherId}
      data-ad-slot={slot}
      data-ad-format={format}
      data-ad-layout-key={layoutKey}
      data-adtest={adTestMode}
      data-full-width-responsive={responsive}
    />
  );
};
