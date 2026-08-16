import assert from "node:assert/strict";
import fs from "node:fs";
import net from "node:net";
import os from "node:os";
import path from "node:path";
import { after, describe, it } from "node:test";

import { assertViteDevAssets, createViteDevAssetsProbe } from "./vite-dev-assets";
import type { ViteDevAssetsProbe } from "./vite-dev-assets";

const HOT_URL = "http://[::1]:5173";

function probe(overrides: Partial<ViteDevAssetsProbe> = {}): ViteDevAssetsProbe {
  return {
    readHotFile: () => HOT_URL,
    request: () => Promise.resolve({ status: 200 }),
    ...overrides,
  };
}

const temporaryRoots: string[] = [];

function temporaryRoot(): string {
  const root = fs.mkdtempSync(path.join(os.tmpdir(), "vite-dev-assets-"));

  temporaryRoots.push(root);
  fs.mkdirSync(path.join(root, "public"));

  return root;
}

after(() => {
  for (const root of temporaryRoots) {
    fs.rmSync(root, { recursive: true, force: true });
  }
});

describe("E2Eが検証するアセットのガード", () => {
  it("dev serverが生きていてhotファイルもあれば通す", async () => {
    await assert.doesNotReject(() => assertViteDevAssets(probe()));
  });

  it("hotファイルが指すdev serverだけを確認する", async () => {
    const requested: string[] = [];

    await assertViteDevAssets(
      probe({
        request: (url) => {
          requested.push(url);

          return Promise.resolve({ status: 200 });
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
            request: () => {
              requested = true;

              return Promise.resolve({ status: 200 });
            },
          }),
        ),
      /ビルド済みアセット/,
    );
    assert.equal(requested, false);
  });

  it("hotファイルが空なら未起動として中止する", async () => {
    let requested = false;

    await assert.rejects(
      () =>
        assertViteDevAssets(
          probe({
            readHotFile: () => "",
            request: () => {
              requested = true;

              return Promise.resolve({ status: 200 });
            },
          }),
        ),
      /ビルド済みアセット/,
    );
    assert.equal(requested, false);
  });

  it("hotファイルが指すdev serverへ接続できなければ原因を添えて中止する", async () => {
    await assert.rejects(
      () =>
        assertViteDevAssets(probe({ request: () => Promise.resolve({ error: "ECONNREFUSED" }) })),
      /接続できません[\s\S]*ECONNREFUSED/,
    );
  });

  it("hotファイルが指すdev serverが応答してもエラーならステータスを添えて中止する", async () => {
    await assert.rejects(
      () => assertViteDevAssets(probe({ request: () => Promise.resolve({ status: 404 }) })),
      /404/,
    );
  });

  it("接続できない場合と応答がエラーの場合を別の文言で伝える", async () => {
    const message = async (response: Awaited<ReturnType<ViteDevAssetsProbe["request"]>>) => {
      try {
        await assertViteDevAssets(probe({ request: () => Promise.resolve(response) }));
      } catch (error) {
        return String(error);
      }

      return "";
    };

    assert.notEqual(await message({ error: "ECONNREFUSED" }), await message({ status: 404 }));
  });
});

describe("Vite開発アセットの実プローブ", () => {
  it("hotファイルをカレントディレクトリではなくリポジトリルート基準で読む", () => {
    const root = temporaryRoot();

    fs.writeFileSync(path.join(root, "public/hot"), `${HOT_URL}\n`);

    const originalCwd = process.cwd();

    process.chdir(os.tmpdir());

    try {
      assert.equal(createViteDevAssetsProbe(root).readHotFile(), HOT_URL);
    } finally {
      process.chdir(originalCwd);
    }
  });

  it("hotファイルがなければnullを返す", () => {
    assert.equal(createViteDevAssetsProbe(temporaryRoot()).readHotFile(), null);
  });

  // vite の二重起動時は存在確認と読み取りの間に public/hot が消えるため、読み取り失敗は未起動として扱う。
  it("存在するhotファイルを読めなくてもnullを返す", () => {
    const root = temporaryRoot();

    fs.mkdirSync(path.join(root, "public/hot"));

    assert.equal(createViteDevAssetsProbe(root).readHotFile(), null);
  });

  // Node の fetch は接続失敗を一律 "fetch failed" にし、ECONNREFUSED などは cause 側に入れる。
  it("接続を拒否されたら原因まで含めて返す", async () => {
    const server = net.createServer();

    await new Promise<void>((resolve) => server.listen(0, "127.0.0.1", resolve));

    const { port } = server.address() as net.AddressInfo;

    await new Promise<void>((resolve) => server.close(() => resolve()));

    const response = await createViteDevAssetsProbe(temporaryRoot()).request(
      `http://127.0.0.1:${port}/@vite/client`,
    );

    assert.ok(
      "error" in response && response.error.includes("ECONNREFUSED"),
      `ECONNREFUSED を期待したが ${JSON.stringify(response)} だった`,
    );
  });

  it("応答しないdev serverを待ち続けずエラーとして返す", async (t) => {
    const sockets: net.Socket[] = [];
    const server = net.createServer((socket) => sockets.push(socket));

    await new Promise<void>((resolve) => server.listen(0, "127.0.0.1", resolve));
    t.after(() => {
      for (const socket of sockets) {
        socket.destroy();
      }

      server.close();
    });

    const { port } = server.address() as net.AddressInfo;
    const response = await createViteDevAssetsProbe(temporaryRoot(), 100).request(
      `http://127.0.0.1:${port}/@vite/client`,
    );

    assert.ok("error" in response, `error を期待したが ${JSON.stringify(response)} だった`);
    // タイムアウトは cause を持たないため、原因の連結が "undefined" を混ぜてはいけない。
    assert.doesNotMatch(response.error, /undefined/);
  });
});
