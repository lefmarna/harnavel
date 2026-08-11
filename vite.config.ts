import inertia from "@inertiajs/vite";
import { wayfinder } from "@laravel/vite-plugin-wayfinder";
import tailwindcss from "@tailwindcss/vite";
import react from "@vitejs/plugin-react";
import laravel from "laravel-vite-plugin";
import { defineConfig, loadEnv } from "vite";

const isGitHubActions = process.env.GITHUB_ACTIONS === "true";

// formVariants controls the --with-form flag for the Vite plugin path.
const wayfinderGenerateCommand = isGitHubActions
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
    },
    plugins: [
      laravel({
        input: ["resources/css/app.css", "resources/js/app.tsx"],
        refresh: true,
      }),
      inertia(),
      react({
        babel: {
          plugins: ["babel-plugin-react-compiler"],
        },
      }),
      tailwindcss(),
      wayfinder({
        formVariants: true,
        command: wayfinderGenerateCommand,
      }),
    ],
  };
});
