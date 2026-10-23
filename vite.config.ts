import inertia from "@inertiajs/vite";
import { wayfinder } from "@laravel/vite-plugin-wayfinder";
import babel from "@rolldown/plugin-babel";
import tailwindcss from "@tailwindcss/vite";
import react, { reactCompilerPreset } from "@vitejs/plugin-react";
import laravel from "laravel-vite-plugin";
import { defineConfig, lazyPlugins, loadEnv } from "vite-plus";

// CI は Docker を使わずホストの PHP で動く。
const isCI = !!process.env.CI;

const wayfinderGenerateCommand = isCI
  ? "php artisan wayfinder:generate"
  : "docker compose exec -T app php artisan wayfinder:generate";

const parsePort = (value: string | undefined, fallback: number): number => {
  const port = Number.parseInt(value ?? "", 10);

  return Number.isNaN(port) ? fallback : port;
};

export default defineConfig(({ mode }) => {
  const env = loadEnv(mode, process.cwd(), "");
  const vitePort = parsePort(env.VITE_PORT, 5173);

  return {
    server: {
      port: vitePort,
      strictPort: true,
      // E2E のレポート出力で開いているページが再読み込みされないようにする。
      watch: {
        ignored: ["**/playwright-report/**"],
      },
    },
    // ページの依存も起動時に取り込み、初回アクセス時の全体リロードを防ぐ。
    optimizeDeps: {
      entries: ["resources/js/app.tsx", "resources/js/pages/**/*.tsx"],
    },
    plugins: lazyPlugins(() => [
      laravel({
        input: ["resources/css/app.css", "resources/js/app.tsx"],
        refresh: true,
      }),
      // 既定では app.tsx を SSR エントリとみなして dev サーバーが読み込もうとする。
      inertia({ ssr: false }),
      react(),
      babel({
        presets: [reactCompilerPreset()],
      }),
      tailwindcss(),
      wayfinder({
        formVariants: true,
        command: wayfinderGenerateCommand,
      }),
    ]),
    lint: {
      categories: {
        correctness: "error",
      },
      plugins: ["import", "typescript", "unicorn", "oxc", "react"],
      options: {
        reportUnusedDisableDirectives: "error",
      },
      rules: {
        "eslint/no-fallthrough": "error",
        "import/default": "off",
        "import/namespace": "off",
        "react/rules-of-hooks": "error",
        // コンポーネントの関数呼び出しを検出するルールは correctness に含まれないため、個別に有効化する。
        "react/capitalized-calls": "warn",
        "typescript/ban-ts-comment": "error",
        "typescript/no-empty-object-type": "error",
        "typescript/no-explicit-any": "error",
        "typescript/no-floating-promises": [
          "error",
          {
            allowForKnownSafeCalls: [
              {
                from: "package",
                name: ["describe", "it", "test"],
                package: "node:test",
              },
            ],
          },
        ],
        "typescript/no-misused-promises": [
          "error",
          {
            checksVoidReturn: {
              attributes: false,
            },
          },
        ],
        "typescript/no-unsafe-assignment": "error",
        "typescript/no-unsafe-argument": "error",
        "typescript/no-unsafe-call": "error",
        "typescript/no-unsafe-function-type": "error",
        "typescript/no-unsafe-member-access": "error",
        "typescript/no-unsafe-return": "error",
        "typescript/switch-exhaustiveness-check": "error",
        "typescript/no-useless-default-assignment": "off",
      },
      overrides: [
        {
          files: ["resources/js/**/*.{cjs,cts,js,jsx,mjs,mts,ts,tsx}"],
          env: {
            browser: true,
          },
          rules: {
            "import/no-nodejs-modules": "error",
          },
        },
        {
          files: ["resources/js/**/*.test.{cts,mts,ts,tsx}"],
          env: {
            node: true,
          },
          rules: {
            "import/no-nodejs-modules": "off",
          },
        },
        {
          files: [
            "scripts/**/*.{cjs,cts,js,mjs,mts,ts,tsx}",
            "e2e/**/*.{cjs,cts,js,jsx,mjs,mts,ts,tsx}",
            "*.config.{cjs,cts,js,mjs,mts,ts}",
          ],
          env: {
            node: true,
          },
        },
        {
          files: ["**/*.{js,mjs,cjs}"],
          rules: {
            "typescript/no-unsafe-assignment": "off",
            "typescript/no-unsafe-argument": "off",
            "typescript/no-unsafe-call": "off",
            "typescript/no-unsafe-member-access": "off",
            "typescript/no-unsafe-return": "off",
          },
        },
      ],
    },
    fmt: {
      sortImports: true,
      sortTailwindcss: {
        stylesheet: "resources/css/app.css",
        functions: ["clsx", "cn", "cva"],
      },
      ignorePatterns: [
        "/boost.json",
        // Laravel Boost と cc-sdd が全体を再生成する。
        ".agents/skills/**",
        ".claude/skills/**",
        // エージェント向け文書では、表の桁揃え用の空白を省く。
        ".kiro/**/*.md",
        "docs/generated/**/*.md",
        "storage/inertia-devtools/",
      ],
      overrides: [
        {
          // コード片が別の意味の JavaScript として整形されることを防ぐ。
          files: ["*.md"],
          options: {
            embeddedLanguageFormatting: "off",
          },
        },
      ],
    },
  };
});
