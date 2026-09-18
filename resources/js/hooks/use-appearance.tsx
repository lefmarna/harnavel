import { useCallback, useMemo, useSyncExternalStore } from "react";

export type ResolvedAppearance = "light" | "dark";
export type Appearance = ResolvedAppearance | "system";

export type UseAppearanceReturn = {
  readonly appearance: Appearance;
  readonly resolvedAppearance: ResolvedAppearance;
  readonly updateAppearance: (mode: Appearance) => void;
};

const STORAGE_KEY = "appearance";
const listeners = new Set<() => void>();
let currentAppearance: Appearance = "system";

const isAppearance = (value: string | null): value is Appearance =>
  value === "light" || value === "dark" || value === "system";

const darkScheme = (): MediaQueryList => window.matchMedia("(prefers-color-scheme: dark)");

// 初期 HTML の dark クラスはサーバー側がこの Cookie から決める（HandleAppearance）。
const setCookie = (name: string, value: string, days = 365): void => {
  const maxAge = days * 24 * 60 * 60;
  document.cookie = `${name}=${value};path=/;max-age=${maxAge};SameSite=Lax`;
};

const getStoredAppearance = (): Appearance => {
  const stored = localStorage.getItem(STORAGE_KEY);

  return isAppearance(stored) ? stored : "system";
};

const isDarkMode = (appearance: Appearance): boolean => {
  return appearance === "dark" || (appearance === "system" && darkScheme().matches);
};

const applyTheme = (appearance: Appearance): void => {
  const isDark = isDarkMode(appearance);

  document.documentElement.classList.toggle("dark", isDark);
  document.documentElement.style.colorScheme = isDark ? "dark" : "light";
};

const subscribe = (callback: () => void) => {
  listeners.add(callback);

  return () => listeners.delete(callback);
};

const notify = (): void => listeners.forEach((listener) => listener());

const handleSystemThemeChange = (): void => applyTheme(currentAppearance);

export function initializeTheme(): void {
  if (!localStorage.getItem(STORAGE_KEY)) {
    localStorage.setItem(STORAGE_KEY, "system");
    setCookie(STORAGE_KEY, "system");
  }

  currentAppearance = getStoredAppearance();
  applyTheme(currentAppearance);

  darkScheme().addEventListener("change", handleSystemThemeChange);
}

export function useAppearance(): UseAppearanceReturn {
  const appearance: Appearance = useSyncExternalStore(subscribe, () => currentAppearance);

  const resolvedAppearance: ResolvedAppearance = useMemo(
    () => (isDarkMode(appearance) ? "dark" : "light"),
    [appearance],
  );

  const updateAppearance = useCallback((mode: Appearance): void => {
    currentAppearance = mode;

    localStorage.setItem(STORAGE_KEY, mode);
    setCookie(STORAGE_KEY, mode);

    applyTheme(mode);
    notify();
  }, []);

  return { appearance, resolvedAppearance, updateAppearance } as const;
}
