import type { SharedData } from "@/types/auth";

declare module "@inertiajs/core" {
  export interface InertiaConfig {
    sharedPageProps: SharedData;
  }
}

declare module "react" {
  // style 属性で CSS カスタムプロパティを渡せるようにする。
  interface CSSProperties {
    [customProperty: `--${string}`]: string | number | undefined;
  }
}
