import { spawnSync } from "node:child_process";
import { createHash, randomBytes } from "node:crypto";
import fs from "node:fs";
import net from "node:net";
import path from "node:path";
import { setTimeout as sleep } from "node:timers/promises";

import { envPort, readLocalEnv } from "./local-env";

type Allocation = { path: string; slot: number; project: string; database: string };
type Registry = { basePort: number; infra: string; allocations: Allocation[] };

const root = process.cwd();
const envFile = path.join(root, ".env");
const hash = (value: string): string =>
  createHash("sha256").update(value).digest("hex").slice(0, 12);

function command(
  args: string[],
  options: { cwd?: string; input?: string; env?: NodeJS.ProcessEnv } = {},
): string {
  const [program, ...rest] = args;
  if (!program) throw new Error("実行コマンドがありません。");
  const result = spawnSync(program, rest, {
    cwd: options.cwd ?? root,
    env: options.env ?? process.env,
    input: options.input,
    encoding: "utf8",
    stdio: options.input === undefined ? ["inherit", "pipe", "pipe"] : ["pipe", "pipe", "pipe"],
  });
  if (result.status !== 0) {
    throw new Error(
      `${program} ${rest.slice(0, 3).join(" ")} が失敗しました。\n${result.stderr || result.error?.message || result.stdout}`,
    );
  }
  return result.stdout.trim();
}

const commonDir = fs.realpathSync(
  command(["git", "rev-parse", "--path-format=absolute", "--git-common-dir"]),
);
const primary = path.dirname(commonDir);
const registryFile = path.join(commonDir, "dev-worktrees.json");
// アプリ共通の値。主 checkout が未セットアップでも .env.example から決まる。
const primaryValue = (key: string): string =>
  readLocalEnv(primary)[key] ?? readLocalEnv(primary, ".env.example")[key] ?? "";

const appBasePort = (): number =>
  envPort({ DEV_PORT_BASE: primaryValue("DEV_PORT_BASE") }, "DEV_PORT_BASE");

// 共有状態（ポート枠・共有サービス・DB アカウント）の更新だけを排他にする。
// アプリのポート範囲の +9 を待ち受けて錠にする。異常終了しても OS が解放するため回収が要らない。
async function locked<T>(action: () => Promise<T>): Promise<T> {
  const port = appBasePort() + 9;
  const deadline = Date.now() + 600_000;
  let server: net.Server;
  for (;;) {
    // 排他には待ち受けだけを使う。接続が残ると close() が終わらないため即座に切る。
    server = net.createServer((socket) => socket.destroy());
    const error = await new Promise<NodeJS.ErrnoException | undefined>((resolve) => {
      server.once("error", resolve);
      server.listen(port, "127.0.0.1", () => resolve(undefined));
    });
    if (!error) break;
    if (error.code !== "EADDRINUSE") throw error;
    if (Date.now() > deadline) throw new Error("別の Worktree の初期化・終了処理が実行中です。");
    await sleep(250);
  }
  try {
    return await action();
  } finally {
    await new Promise<void>((resolve) => server.close(() => resolve()));
  }
}

function privatePaths(): void {
  for (const item of [
    ".env",
    "node_modules",
    "vendor",
    "storage",
    "bootstrap/cache",
    "public/build",
  ]) {
    const target = path.join(root, item);
    if (fs.existsSync(target) && fs.lstatSync(target).isSymbolicLink()) {
      throw new Error(
        `${item} は Worktree 固有の実ファイル・ディレクトリにしてください。Orca の Shared Paths から外してください。`,
      );
    }
  }
}

const readRegistry = (): Registry | undefined =>
  fs.existsSync(registryFile)
    ? (JSON.parse(fs.readFileSync(registryFile, "utf8")) as Registry)
    : undefined;

function saveRegistry(registry: Registry): void {
  const temporary = `${registryFile}.${process.pid}`;
  fs.writeFileSync(temporary, JSON.stringify(registry, null, 2), { mode: 0o600 });
  fs.renameSync(temporary, registryFile);
}

const envKey = (line: string): string | undefined => /^([A-Z_][A-Z0-9_]*)=/.exec(line)?.[1];

function writeEnv(values: Record<string, string>): void {
  const example = fs.readFileSync(path.join(root, ".env.example"), "utf8").split("\n");
  const lines = fs.readFileSync(envFile, "utf8").split("\n");
  const exampleIndex = (key: string): number => {
    const index = example.findIndex((line) => envKey(line) === key);
    if (index === -1) throw new Error(`${key} を .env.example に追加してください。`);
    return index;
  };
  const entries = Object.entries(values).sort(([a], [b]) => exampleIndex(a) - exampleIndex(b));
  for (const [key, value] of entries) {
    // $ と \ は二重引用符内で展開されるため書かない。空白を含むパスだけ二重引用符で囲む。
    if (/[$"\\\r\n]/.test(value)) throw new Error(`${key} に書き込めない文字が含まれています。`);
    const line = /^[A-Za-z0-9_.:/-]*$/.test(value) ? `${key}=${value}` : `${key}="${value}"`;
    const current = lines.findIndex((item) => envKey(item) === key);
    if (current !== -1) {
      lines[current] = line;
      continue;
    }
    // .env.example と比較しやすいよう、同じ位置に直前のコメントごと追加する。
    const at = exampleIndex(key);
    let start = at;
    while (start > 0 && example[start - 1]?.startsWith("#")) start--;
    // 既存の直前行（キーまたはコメント）の後ろに、まだないコメントとキーを入れる。
    let anchor = -1;
    let from = start;
    for (let index = at - 1; index >= 0 && anchor === -1; index--) {
      const previous = example[index] ?? "";
      const previousKey = envKey(previous);
      anchor = previousKey
        ? lines.findIndex((item) => envKey(item) === previousKey)
        : previous.startsWith("#")
          ? lines.indexOf(previous)
          : -1;
      if (anchor !== -1) from = Math.max(start, index + 1);
    }
    lines.splice(anchor + 1, 0, ...example.slice(from, at), line);
  }
  const content = lines.join("\n");
  // Linux の bind mount ではホスト所有のまま見えるため、PHP-FPM の www-data が読める権限にする。
  fs.writeFileSync(envFile, content, { mode: 0o644 });
  fs.chmodSync(envFile, 0o644);
}

function validName(value: string): string {
  if (!/^[a-z][a-z0-9_]{0,63}$/.test(value))
    throw new Error(`ローカル DB 名 ${value} は英小文字・数字・_ の64文字以内にしてください。`);
  return value;
}

async function available(port: number): Promise<void> {
  await new Promise<void>((resolve, reject) => {
    const server = net.createServer();
    server.once("error", () =>
      reject(
        new Error(`ポート ${port} が使用中です。主 Worktree の DEV_PORT_BASE を変更してください。`),
      ),
    );
    server.listen(port, "127.0.0.1", () => server.close(() => resolve()));
  });
}

// DB_URL は個別の接続設定より優先されるため、残っていると他の Worktree の DB に接続する。
function assertNoDatabaseUrl(env: Record<string, string>): void {
  if (env.DB_URL)
    throw new Error(".env の DB_URL を空にしてください。Worktree ごとの DB 設定より優先されます。");
}

async function initialize(): Promise<Record<string, string>> {
  privatePaths();
  if (!fs.existsSync(envFile)) {
    // API キー等はコピーし、ポート・接続先は下で Worktree 固有に書き換える。
    const source =
      root !== primary && fs.existsSync(path.join(primary, ".env"))
        ? path.join(primary, ".env")
        : path.join(root, ".env.example");
    fs.copyFileSync(source, envFile);
  }
  const env = readLocalEnv(root);
  if (env.APP_ENV !== "local")
    throw new Error("開発環境の操作は APP_ENV=local でのみ実行できます。");
  assertNoDatabaseUrl(env);
  const base = appBasePort();
  if (base + 99 > 65535) throw new Error("DEV_PORT_BASE + 99 が65535を超えます。");
  const infra = `dev-${hash(primary)}-infra`;
  const registry: Registry = readRegistry() ?? { basePort: base, infra, allocations: [] };
  if (registry.basePort !== base || registry.infra !== infra)
    throw new Error(
      "登録済み環境の DEV_PORT_BASE・パスは変更できません。停止・削除後に設定してください。",
    );
  let allocation = registry.allocations.find((item) => item.path === root);
  if (!allocation) {
    const slot =
      root === primary
        ? 0
        : Array.from({ length: 9 }, (_, index) => index + 1).find(
            (index) => !registry.allocations.some((item) => item.slot === index),
          );
    if (slot === undefined)
      throw new Error(
        "追加 Worktree のポート枠が9個すべて使用中です。worktree:remove で未使用環境を削除してください。",
      );
    const database =
      root === primary
        ? validName(env.DB_DATABASE ?? "")
        : validName(primaryValue("DB_DATABASE").slice(0, 35) + `_w${hash(root)}`);
    allocation = { path: root, slot, project: `dev-${hash(root)}`, database };
    const ports = [0, 1, 5].map((offset) => base + slot * 10 + offset);
    // 最初の割り当てでは共有サービスも未起動のため、DEV_PORT_BASE が固定される前に確認する。
    if (!fs.existsSync(registryFile)) ports.push(base + 2, base + 3, base + 4);
    for (const port of ports) await available(port);
    registry.allocations.push(allocation);
    saveRegistry(registry);
  }
  const values = {
    COMPOSE_PROJECT_NAME: allocation.project,
    DEV_PORT_BASE: String(base),
    DEV_INFRA_PROJECT: infra,
    DEV_GIT_COMMON_DIR: commonDir,
    NGINX_PORT: String(base + allocation.slot * 10),
    VITE_PORT: String(base + allocation.slot * 10 + 1),
    MARIADB_PORT: String(base + 2),
    MAILPIT_SMTP_PORT: String(base + 3),
    MAILPIT_UI_PORT: String(base + 4),
    APP_URL: `http://localhost:${base + allocation.slot * 10}`,
    DB_DATABASE: allocation.database,
    DB_USERNAME: `dev_${hash(root)}`,
    // 主 Worktree からコピーしたパスワードは使わず、この Worktree のユーザーの値だけ引き継ぐ。
    DB_PASSWORD:
      env.DB_USERNAME === `dev_${hash(root)}` && env.DB_PASSWORD
        ? env.DB_PASSWORD
        : randomBytes(24).toString("hex"),
  };
  writeEnv(values);
  return readLocalEnv(root);
}

function compose(env: Record<string, string>, infrastructure = false): string[] {
  // 共有環境は主 Worktree の定義で操作し、DB の保存先を主 Worktree の storage に固定する。
  const directory = infrastructure ? primary : root;
  const file = path.join(
    directory,
    infrastructure ? "compose.infrastructure.yaml" : "compose.yaml",
  );
  if (!fs.existsSync(file))
    throw new Error(`${file} がありません。主 Worktree を更新してください。`);
  return [
    "docker",
    "compose",
    "--project-directory",
    directory,
    "--env-file",
    envFile,
    "-p",
    infrastructure ? env.DEV_INFRA_PROJECT || "" : env.COMPOSE_PROJECT_NAME || "",
    "-f",
    file,
  ];
}

function sql(env: Record<string, string>, query: string): string {
  // Worktree のパスワードを含む SQL はコマンド引数・ログに含めない。
  try {
    return command(
      [
        ...compose(env, true),
        "exec",
        "-T",
        "-e",
        "MYSQL_PWD=password",
        "mariadb",
        "mariadb",
        "--user=root",
        "--batch",
        "--skip-column-names",
      ],
      { input: query },
    );
  } catch (error) {
    const message = error instanceof Error ? error.message : String(error);
    throw new Error(env.DB_PASSWORD ? message.replaceAll(env.DB_PASSWORD, "[redacted]") : message);
  }
}

const literal = (value: string): string =>
  `'${value.replaceAll("\\", "\\\\").replaceAll("'", "''")}'`;

// GRANT・LIKE 共通のパターン。末尾の % 以外の _ はエスケープして他 DB に一致させない。
const parallelTestDatabases = (testDatabase: string): string =>
  `${validName(testDatabase)}_test_`.replaceAll("_", "\\_") + "%";

// 開発・PHPUnit・E2E の DB とアカウント。compose.yaml も同じ規則でテスト・E2E の接続先を渡す。
const accounts = (database: string, user: string): [string, string][] => [
  [database, user],
  [`${database}_testing`, `${user}_test`],
  [`${database}_e2e`, `${user}_e2e`],
];

function provision(env: Record<string, string>): void {
  const { DB_DATABASE: base, DB_USERNAME: baseUser, DB_PASSWORD: password } = env;
  if (!base || !baseUser || !password) throw new Error("Worktree の DB 接続設定が未作成です。");
  const queries: string[] = [];
  for (const [database, user] of accounts(base, baseUser)) {
    validName(database);
    // GRANT の DB 名では _ がワイルドカードになるため、必ずエスケープする。
    const grant = database.replaceAll("_", "\\_");
    queries.push(
      `CREATE DATABASE IF NOT EXISTS \`${database}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;`,
      `CREATE USER IF NOT EXISTS ${literal(user)}@'%' IDENTIFIED BY ${literal(password)};`,
      `ALTER USER ${literal(user)}@'%' IDENTIFIED BY ${literal(password)};`,
      `GRANT ALL PRIVILEGES ON \`${grant}\`.* TO ${literal(user)}@'%';`,
    );
  }
  // 並列テストは <テスト DB>_test_<番号> を作るため、この Worktree のワーカー DB だけ許可する。
  queries.push(
    `GRANT ALL PRIVILEGES ON \`${parallelTestDatabases(`${base}_testing`)}\`.* TO ${literal(`${baseUser}_test`)}@'%';`,
  );
  sql(env, queries.join("\n"));
}

const startSharedServices = (env: Record<string, string>): void => {
  command([...compose(env, true), "up", "-d", "--wait", "--wait-timeout", "90"]);
};

async function up(): Promise<void> {
  const env = await locked(initialize);
  // Dockerfile の変更を反映する。時間がかかるため共有状態のロックの外で行う。
  command([...compose(env), "build"]);
  await locked(async () => {
    // ビルド中に worktree:remove されていたら、削除済みの環境を作り直さない。
    const allocation = readRegistry()?.allocations.find((item) => item.path === root);
    if (
      !allocation ||
      allocation.project !== env.COMPOSE_PROJECT_NAME ||
      allocation.database !== env.DB_DATABASE
    ) {
      command([...compose(env), "down", "--rmi", "local"]);
      throw new Error(
        "この Worktree の登録は削除されました。使うときは mise run setup を実行してください。",
      );
    }
    startSharedServices(env);
    command([
      "docker",
      "volume",
      "create",
      "--label",
      `com.docker.compose.project=${env.DEV_INFRA_PROJECT}`,
      `${env.DEV_INFRA_PROJECT}-composer-cache`,
    ]);
    provision(env);
    // 起動済みにしてからロックを外し、infra:stop の実行中判定に含める。
    command([...compose(env), "up", "-d"]);
  });
  console.log(`Web: ${env.APP_URL}`);
}

function configured(): Record<string, string> {
  privatePaths();
  const env = readLocalEnv(root);
  if (
    env.APP_ENV !== "local" ||
    env.COMPOSE_PROJECT_NAME !== `dev-${hash(root)}` ||
    env.DEV_INFRA_PROJECT !== `dev-${hash(primary)}-infra`
  )
    throw new Error("この Worktree の開発環境設定が未作成、または別の Worktree の設定です。");
  assertNoDatabaseUrl(env);
  return env;
}

async function remove(): Promise<void> {
  if (root === primary)
    throw new Error(
      "主 Worktree の DB は自動削除できません。停止には mise run down を使ってください。",
    );
  const allocation = readRegistry()?.allocations.find((item) => item.path === root);
  if (!allocation) {
    console.log("この Worktree に削除する開発環境はありません。");
    return;
  }
  const env = configured();
  if (allocation.database !== env.DB_DATABASE)
    throw new Error("削除する Worktree の DB を特定できません。");
  // infra:stop と排他にし、共有 DB が動いている間にコンテナ停止から登録解除までを終える。
  await locked(async () => {
    startSharedServices(env);
    command([...compose(env), "down", "--rmi", "local"]);
    const queries = sql(
      env,
      `SELECT schema_name FROM information_schema.schemata WHERE schema_name LIKE ${literal(parallelTestDatabases(`${allocation.database}_testing`))};`,
    )
      .split("\n")
      .filter(Boolean)
      .map((database) => `DROP DATABASE IF EXISTS \`${validName(database)}\`;`);
    for (const [database, user] of accounts(allocation.database, `dev_${hash(root)}`)) {
      queries.push(
        `DROP DATABASE IF EXISTS \`${validName(database)}\`;`,
        `DROP USER IF EXISTS ${literal(user)}@'%';`,
      );
    }
    sql(env, queries.join("\n"));
    const registry = readRegistry();
    if (!registry) return;
    registry.allocations = registry.allocations.filter((item) => item.path !== root);
    saveRegistry(registry);
  });
  console.log("この Worktree のコンテナ・DB・接続ユーザー・ポート枠を削除しました。");
}

async function main(): Promise<void> {
  const action = process.argv[2] ?? "";
  switch (action) {
    case "env":
      await locked(initialize);
      break;
    case "up":
      await up();
      break;
    case "backend": {
      const env = configured();
      fs.rmSync(path.join(root, "bootstrap/cache/config.php"), { force: true });
      command([
        ...compose(env),
        "exec",
        "-T",
        "app",
        "composer",
        "install",
        "--no-interaction",
        "--prefer-dist",
      ]);
      if (!readLocalEnv(root).APP_KEY)
        command([...compose(env), "exec", "-T", "app", "php", "artisan", "key:generate"]);
      break;
    }
    case "initialize-app": {
      const env = configured();
      // migrate・seed が途中で失敗しても、次回 setup で初期シードを再試行できるよう記録する。
      const seedPending = path.join(root, "storage/framework/.dev-seed-pending");
      if (
        sql(
          env,
          `SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = ${literal(env.DB_DATABASE || "")};`,
        ) === "0"
      )
        fs.writeFileSync(seedPending, "");
      command([...compose(env), "exec", "-T", "app", "php", "artisan", "migrate"]);
      if (fs.existsSync(seedPending)) {
        command([...compose(env), "exec", "-T", "app", "php", "artisan", "db:seed"]);
        fs.rmSync(seedPending);
      }
      break;
    }
    case "remove":
      await remove();
      break;
    case "info": {
      const env = configured();
      console.log(
        `Web: ${env.APP_URL}\nVite: http://localhost:${env.VITE_PORT}（pnpm dev 実行時）\nE2E: http://localhost:${envPort(env, "NGINX_PORT") + 5}（Playwright 実行時）\nMailpit: http://localhost:${env.MAILPIT_UI_PORT}\nDB: ${env.DB_DATABASE}\nCompose: ${env.COMPOSE_PROJECT_NAME}\n共有環境: ${env.DEV_INFRA_PROJECT}`,
      );
      break;
    }
    case "infra-stop": {
      const env = configured();
      await locked(async () => {
        const running = command([
          "docker",
          "ps",
          "--filter",
          `network=${env.DEV_INFRA_PROJECT}-backend`,
          "--format",
          '{{.Label "com.docker.compose.project"}}',
        ])
          .split("\n")
          .filter((item) => item && item !== env.DEV_INFRA_PROJECT);
        if (running.length)
          throw new Error(
            "実行中の Worktree があります。先に各 Worktree で mise run down を実行してください。",
          );
        command([...compose(env, true), "stop"]);
      });
      break;
    }
    default:
      throw new Error("env / up / remove / info / infra-stop を指定してください。");
  }
}

main().catch((error: unknown) => {
  console.error(error instanceof Error ? error.message : String(error));
  process.exitCode = 1;
});
