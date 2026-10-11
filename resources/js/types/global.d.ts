import type { SharedData } from "@/types/auth";

declare module "@inertiajs/core" {
  export interface InertiaConfig {
    sharedPageProps: SharedData;
  }
}

declare module "react" {
  interface CSSProperties {
    [customProperty: `--${string}`]: string | number | undefined;
  }
}
