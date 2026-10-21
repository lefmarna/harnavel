import { useCallback } from "react";

export type CleanupFn = () => void;

export function useMobileNavigation(): CleanupFn {
  return useCallback(() => {
    // 開いたメニューから遷移すると、Radix が body に付けた pointer-events: none が残る。
    document.body.style.removeProperty("pointer-events");
  }, []);
}
