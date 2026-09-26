import fs from "node:fs";
import path from "node:path";
import { parseEnv } from "node:util";

export const readLocalEnv = (root: string, name = ".env"): Record<string, string> => {
  const file = path.join(root, name);
  const values = fs.existsSync(file) ? parseEnv(fs.readFileSync(file, "utf8")) : {};

  const expand = (value: string, seen: Set<string>): string =>
    value.replace(/\$\{([A-Z_][A-Z_0-9]*)\}/g, (_, key: string) => {
      if (seen.has(key)) {
        throw new Error(`.env の ${key} が循環参照しています。`);
      }

      return expand(values[key] ?? "", new Set([...seen, key]));
    });

  return Object.fromEntries(
    Object.entries(values).map(([key, value]) => [key, expand(value ?? "", new Set([key]))]),
  );
};

export const envPort = (
  values: Record<string, string>,
  name: string,
  fallback?: number,
): number => {
  const value = process.env[name] ?? values[name];
  const port = value === undefined ? fallback : Number(value);

  if (port === undefined || !Number.isInteger(port) || port < 1 || port > 65535) {
    throw new Error(`${name} に 1〜65535 のポート番号を設定してください。`);
  }

  return port;
};
