import { execFileSync } from "node:child_process";

/**
 * 実行中の E2E コンテナ名を探す。
 *
 * 名前は playwright-e2e-<PORT>-<PID>。Playwright はワーカーでも config を読み直すため
 * 環境変数の PID 部分は当てにならないが、PORT は NGINX_PORT + 1000 で一致する。接頭辞だけで
 * 絞ると同じテンプレート由来の別リポジトリのコンテナまで拾うので、PORT まで含めて照合する。
 */
const resolveContainer = (): string => {
  const port = process.env.PLAYWRIGHT_E2E_CONTAINER?.split("-")[2];

  if (!port) {
    throw new Error("Playwright E2E container name is missing or invalid.");
  }

  const names = execFileSync(
    "docker",
    ["ps", "--filter", `name=^playwright-e2e-${port}-`, "--format", "{{.Names}}"],
    { encoding: "utf8", stdio: "pipe" },
  )
    .split("\n")
    .map((name) => name.trim())
    .filter(Boolean);

  const [container] = names;

  if (names.length !== 1 || !container) {
    throw new Error(`Expected exactly one E2E container, found ${names.length}.`);
  }

  return container;
};

/** E2E アプリが動く場所で artisan を実行する。ローカルは使い捨てコンテナ、CI はホスト。 */
const runArtisan = (args: string[]): string => {
  if (process.env.CI) {
    return execFileSync("php", ["artisan", ...args], { encoding: "utf8", stdio: "pipe" });
  }

  return execFileSync("docker", ["exec", "-i", resolveContainer(), "php", "artisan", ...args], {
    encoding: "utf8",
    stdio: "pipe",
  });
};

/**
 * ログイン済みセッションからパスワード確認の記録だけを消し、期限切れを再現する。
 * ログイン自体は維持されるため、確認が必要な操作だけが再確認を求められる。
 *
 * payload の形式は config/session.php の serialization に従う。取り出せなければ
 * 黙ってセッションを壊さず落とす。
 */
export const expirePasswordConfirmation = (): void => {
  runArtisan([
    "tinker",
    "--execute",
    "foreach (DB::table('sessions')->whereNotNull('user_id')->get() as $row) {" +
      " $data = json_decode(base64_decode($row->payload), true);" +
      " if (! is_array($data)) { throw new RuntimeException('Unexpected session payload format.'); }" +
      " \\Illuminate\\Support\\Arr::forget($data, 'auth.password_confirmed_at');" +
      " DB::table('sessions')->where('id', $row->id)" +
      "->update(['payload' => base64_encode(json_encode($data))]); }",
  ]);
};
