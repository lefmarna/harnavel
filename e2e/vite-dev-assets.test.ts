import assert from "node:assert/strict";
import { describe, it } from "node:test";

import { assertViteDevAssets } from "./vite-dev-assets";
import type { ViteDevAssetsProbe } from "./vite-dev-assets";

const HOT_URL = "http://[::1]:5173";

function probe(overrides: Partial<ViteDevAssetsProbe> = {}): ViteDevAssetsProbe {
  return {
    readHotFile: () => HOT_URL,
    requestStatus: () => Promise.resolve(200),
    ...overrides,
  };
}

describe("E2Eが検証するアセットのガード", () => {
  it("dev serverが生きていてhotファイルもあれば通す", async () => {
    await assert.doesNotReject(() => assertViteDevAssets(probe()));
  });

  it("hotファイルが指すdev serverだけを確認する", async () => {
    const requested: string[] = [];

    await assertViteDevAssets(
      probe({
        requestStatus: (url) => {
          requested.push(url);

          return Promise.resolve(200);
        },
      }),
    );

    assert.deepEqual(requested, [`${HOT_URL}/@vite/client`]);
  });

  it("hotファイルがなければ中止する", async () => {
    let requested = false;

    await assert.rejects(
      () =>
        assertViteDevAssets(
          probe({
            readHotFile: () => null,
            requestStatus: () => {
              requested = true;

              return Promise.resolve(200);
            },
          }),
        ),
      /ビルド済みアセット/,
    );
    assert.equal(requested, false);
  });

  it("hotファイルが指すdev serverへ接続できなければ中止する", async () => {
    await assert.rejects(
      () => assertViteDevAssets(probe({ requestStatus: () => Promise.resolve(null) })),
      /接続できません/,
    );
  });

  it("hotファイルが指すdev serverが応答してもエラーなら中止する", async () => {
    await assert.rejects(
      () => assertViteDevAssets(probe({ requestStatus: () => Promise.resolve(404) })),
      /接続できません/,
    );
  });
});
