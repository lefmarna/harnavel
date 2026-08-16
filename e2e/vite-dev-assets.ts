import fs from "node:fs";
import path from "node:path";

export type ViteDevServerResponse = { status: number } | { error: string };

export type ViteDevAssetsProbe = {
  readHotFile: () => string | null;
  request: (url: string) => Promise<ViteDevServerResponse>;
};

const DEFAULT_TIMEOUT_MS = 5_000;

// Node の fetch は接続失敗を一律 "fetch failed" にし、ECONNREFUSED などの実際の原因は cause に入れる。
function describeError(error: unknown): string {
  const cause = error instanceof Error ? error.cause : undefined;

  if (cause === undefined) {
    return String(error);
  }

  return `${String(error)}: ${cause instanceof Error ? cause.message : JSON.stringify(cause)}`;
}

export function createViteDevAssetsProbe(
  repoRoot: string,
  timeoutMs: number = DEFAULT_TIMEOUT_MS,
): ViteDevAssetsProbe {
  const hotFile = path.resolve(repoRoot, "public/hot");

  return {
    // vite の二重起動では存在確認と読み取りの間に public/hot が消えるため、読み取り失敗も未起動として扱う。
    readHotFile: () => {
      try {
        return fs.readFileSync(hotFile, "utf8").trim();
      } catch {
        return null;
      }
    },
    // 接続を受けたまま応答しない dev server で待ち続けないよう、必ず期限を切る。
    request: async (url) => {
      try {
        const response = await fetch(url, { signal: AbortSignal.timeout(timeoutMs) });

        await response.body?.cancel();

        return { status: response.status };
      } catch (error) {
        return { error: describeError(error) };
      }
    },
  };
}

// Playwright の reuseExistingServer は URL の応答だけで再利用を決めるが、Laravel が dev server を
// 使うかどうかは public/hot の有無で決まる。両者がずれると E2E は最後のビルド結果を検証し続ける。
export async function assertViteDevAssets(probe: ViteDevAssetsProbe): Promise<void> {
  const hotUrl = probe.readHotFile();

  if (!hotUrl) {
    throw new Error(
      "public/hot がないため、Laravel は dev server ではなく public/build のビルド済みアセットを参照します。" +
        "E2E が現在のソースを検証できない（未ビルドなら manifest が見つからず全ページがエラーになる）" +
        "ため中止しました。" +
        " `pnpm dev` を再起動してください（vite の二重起動はポート衝突で終了する側が" +
        " public/hot を削除するため、先に起動済みのプロセスも止めてください）。",
    );
  }

  const response = await probe.request(`${hotUrl}/@vite/client`);

  if ("error" in response) {
    throw new Error(
      `public/hot が指す ${hotUrl} の Vite dev server へ接続できません。` +
        "アセットの取得がすべて失敗するため中止しました。" +
        " `pnpm dev` を再起動してください。" +
        `\n${response.error}`,
    );
  }

  if (response.status !== 200) {
    throw new Error(
      `public/hot が指す ${hotUrl} の Vite dev server が /@vite/client に ${response.status} を返しました。` +
        "アセットの取得が失敗するため中止しました。" +
        " `pnpm dev` を再起動してください。",
    );
  }
}
