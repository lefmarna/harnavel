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

export const AdCode = ({
  slot,
  style = { display: "block" },
  format,
  responsive,
  layoutKey,
}: AdCodeProps) => {
  const { tracking } = usePage<SharedData>().props;
  const canRender =
    !import.meta.env.DEV && tracking.adsEnabled && Boolean(tracking.adsensePublisherId);

  useLayoutEffect(() => {
    if (!canRender) {
      return;
    }

    try {
      (window.adsbygoogle = window.adsbygoogle || []).push({});
    } catch (e) {
      console.error(e);
    }
  }, [canRender]);

  if (!canRender) {
    return null;
  }

  return (
    <ins
      className="adsbygoogle"
      style={style}
      data-ad-client={tracking.adsensePublisherId ?? undefined}
      data-ad-slot={slot}
      data-ad-format={format}
      data-ad-layout-key={layoutKey}
      data-adtest={tracking.adsTestMode ? "on" : "off"}
      data-full-width-responsive={responsive}
    />
  );
};
