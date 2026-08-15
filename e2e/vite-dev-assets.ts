export type ViteDevAssetsProbe = {
  readHotFile: () => string | null;
  requestStatus: (url: string) => Promise<number | null>;
};

// Playwright の reuseExistingServer は URL の応答だけで再利用を決めるが、Laravel が dev server を
// 使うかどうかは public/hot の有無で決まる。両者がずれると E2E は最後のビルド結果を検証し続ける。
export async function assertViteDevAssets(probe: ViteDevAssetsProbe): Promise<void> {
  const hotUrl = probe.readHotFile();

  if (hotUrl === null) {
    throw new Error(
      "public/hot がないため、Laravel は public/build のビルド済みアセットを配信します。" +
        "E2E は現在のソースではなく最後にビルドした時点のコードを検証するため中止しました。" +
        " `pnpm dev` を再起動してください（vite の二重起動はポート衝突で終了する側が" +
        " public/hot を削除するため、先に起動済みのプロセスも止めてください）。",
    );
  }

  if ((await probe.requestStatus(`${hotUrl}/@vite/client`)) !== 200) {
    throw new Error(
      `public/hot が指す ${hotUrl} の Vite dev server へ接続できません。` +
        "アセットの取得がすべて失敗するため中止しました。" +
        " `pnpm dev` を再起動してください。",
    );
  }
}
