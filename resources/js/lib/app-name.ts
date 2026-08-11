const DEFAULT_APP_NAME = "Laravel App Template";

export function resolveAppName(configuredAppName: unknown): string {
  return typeof configuredAppName === "string" && configuredAppName.length > 0
    ? configuredAppName
    : DEFAULT_APP_NAME;
}
