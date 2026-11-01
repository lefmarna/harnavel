import fs from "node:fs";
import path from "node:path";

const DEFAULT_TIMEOUT_MS = 5_000;

function describeError(error: unknown): string {
  const cause = error instanceof Error ? error.cause : undefined;

  if (cause === undefined) {
    return String(error);
  }

  return `${String(error)}: ${cause instanceof Error ? cause.message : JSON.stringify(cause)}`;
}

// Playwright の reuseExistingServer は URL の応答だけで再利用を決めるが、Laravel が dev server を使うかは
// public/hot で決まる。ずれると E2E は最後のビルド結果を検証し続ける。
export async function assertViteDevAssets(
  repoRoot: string,
  timeoutMs: number = DEFAULT_TIMEOUT_MS,
): Promise<void> {
  const hotFile = path.resolve(repoRoot, "public/hot");
  let hotUrl: string | null = null;

  try {
    hotUrl = fs.readFileSync(hotFile, "utf8").trim();
  } catch {
    hotUrl = null;
  }

  if (!hotUrl) {
    throw new Error(
      "public/hot がないため、Laravel は dev server ではなく public/build のビルド済みアセットを参照します。" +
        "E2E が現在のソースを検証できない（未ビルドなら manifest が見つからず全ページがエラーになる）" +
        "ため中止しました。" +
        " `pnpm dev` を再起動してください（vite の二重起動はポート衝突で終了する側が" +
        " public/hot を削除するため、先に起動済みのプロセスも止めてください）。",
    );
  }

  let status: number;

  try {
    const response = await fetch(`${hotUrl}/@vite/client`, {
      signal: AbortSignal.timeout(timeoutMs),
    });
    await response.body?.cancel();
    status = response.status;
  } catch (error) {
    throw new Error(
      `public/hot が指す ${hotUrl} の Vite dev server へ接続できません。` +
        "アセットの取得がすべて失敗するため中止しました。" +
        " `pnpm dev` を再起動してください。" +
        `\n${describeError(error)}`,
      { cause: error },
    );
  }

  if (status !== 200) {
    throw new Error(
      `public/hot が指す ${hotUrl} の Vite dev server が /@vite/client に ${status} を返しました。` +
        "アセットの取得が失敗するため中止しました。" +
        " `pnpm dev` を再起動してください。",
    );
  }
}
