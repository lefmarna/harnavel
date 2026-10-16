import { useCallback } from "react";

export type CleanupFn = () => void;

export function useMobileNavigation(): CleanupFn {
  return useCallback(() => {
    // Radix のメニューが閉じる前に遷移すると、body に付けた pointer-events: none が残る。
    document.body.style.removeProperty("pointer-events");
  }, []);
}
