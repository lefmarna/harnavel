import assert from "node:assert/strict";
import { describe, it } from "node:test";

import {
  accessLogHost,
  attributeLogLines,
  capOutput,
  errorLogSite,
  parseRuns,
  orderByLastUpdate,
  scanIsConclusive,
  rsyncOutcome,
  DEFAULT_LINES,
  deriveEnvironments,
  formatCliOutput,
  extractLogPayload,
  fitWithinBytes,
  tailWithinBytes,
  MAX_LINES,
  MAX_OUTPUT_BYTES,
  parseArgs,
  requireKeyword,
  requireKnownFlags,
  requireLines,
  resolveEnvironmentName,
  RuntimeOpsError,
} from "./runtime-ops";

const HTACCESS = `<IfModule mod_rewrite.c>
  RewriteEngine On
  RewriteCond %{HTTP_HOST} !^(?:(?:www|static|dev)\\.)?example\\.com$ [NC]
  RewriteRule ^ - [F]
  RewriteCond %{HTTP_HOST} ^www\\.example\\.com$ [NC]
  RewriteRule ^ https://example.com%{REQUEST_URI} [R=301,L]
</IfModule>`;

const HTACCESS_DEVELOP = `<IfModule mod_rewrite.c>
  RewriteEngine On
  RewriteCond %{HTTP_HOST} !^dev\\.example\\.com$ [NC]
  RewriteRule ^ - [F]
</IfModule>

AuthUserFile "/home/u/example.com/htpasswd/dev.example.com/.htpasswd"
AuthName "Member Site"
AuthType BASIC
require valid-user`;

const ENVIRONMENTS = deriveEnvironments(HTACCESS, HTACCESS_DEVELOP);

describe("環境の導出", () => {
  it("本番のホストと共有ログドメインを拒否条件から読む", () => {
    assert.equal(ENVIRONMENTS.production?.host, "example.com");
    assert.equal(ENVIRONMENTS.production?.xserverLogDomain, "example.com");
    assert.equal(ENVIRONMENTS.production?.branch, "main");
  });

  it("developのホストを読み、ログは親ドメイン側に寄せる", () => {
    assert.equal(ENVIRONMENTS.develop?.host, "dev.example.com");
    assert.equal(ENVIRONMENTS.develop?.xserverLogDomain, "example.com");
    assert.equal(ENVIRONMENTS.develop?.branch, "develop");
  });

  it("Basic認証の有無を require valid-user から判定する", () => {
    assert.equal(ENVIRONMENTS.develop?.basicAuth, true);
    assert.equal(ENVIRONMENTS.production?.basicAuth, false);
  });

  it("developが別ドメインならログを親へ寄せない", () => {
    const separate = deriveEnvironments(
      HTACCESS,
      "RewriteCond %{HTTP_HOST} !^staging\\.other\\.com$ [NC]",
    );

    assert.equal(separate.develop?.xserverLogDomain, "staging.other.com");
  });

  it(".htaccess.develop が無ければ本番だけを返す", () => {
    assert.deepEqual(Object.keys(deriveEnvironments(HTACCESS, undefined)), ["production"]);
  });

  it("ホスト条件を読めなければ推測せず失敗する", () => {
    assert.throws(() => deriveEnvironments("RewriteEngine On", undefined), RuntimeOpsError);
    assert.throws(() => deriveEnvironments(HTACCESS, "RewriteEngine On"), RuntimeOpsError);
    assert.throws(
      () => deriveEnvironments("RewriteCond %{HTTP_HOST} !^not a host$", undefined),
      RuntimeOpsError,
    );
  });
});

describe("対象環境の解決", () => {
  it("登録済みブランチを対応する環境へ解決する", () => {
    assert.equal(resolveEnvironmentName({ branch: "develop" }), "develop");
    assert.equal(resolveEnvironmentName({ branch: "main" }), "production");
  });

  it("未登録ブランチからは環境を推測せず失敗する", () => {
    for (const branch of [
      undefined,
      "",
      "feature/anything",
      "release/2026-08",
      "mainline",
      "Main",
    ]) {
      assert.throws(() => resolveEnvironmentName({ branch }), RuntimeOpsError);
    }
  });

  it("未登録ブランチでproductionへ解決されることはない", () => {
    for (const branch of [
      undefined,
      "",
      "feature/anything",
      "release/2026-08",
      "mainline",
      "Main",
    ]) {
      let resolved: string | undefined;

      try {
        resolved = resolveEnvironmentName({ branch });
      } catch {
        resolved = undefined;
      }

      assert.notEqual(resolved, "production");
    }
  });

  it("別ブランチをチェックアウト中でも明示指定は尊重する", () => {
    assert.equal(resolveEnvironmentName({ branch: "develop", env: "production" }), "production");
  });

  it("宣言されていない環境名は拒否する", () => {
    assert.throws(() => resolveEnvironmentName({ env: "staging" }), RuntimeOpsError);
  });
});

describe("取得行数の検証", () => {
  it("既定値は上限より小さい", () => {
    assert.equal(requireLines(undefined), DEFAULT_LINES);
    assert.ok(DEFAULT_LINES <= MAX_LINES);
  });

  it("上限を超える要求は拒否する", () => {
    assert.throws(() => requireLines(String(MAX_LINES + 1)), RuntimeOpsError);
    assert.throws(() => requireLines("100000"), RuntimeOpsError);
  });

  it("正の整数以外は拒否する", () => {
    for (const value of ["0", "-5", "50.5", "abc", "", "1e3", " 10"]) {
      assert.throws(() => requireLines(value), RuntimeOpsError);
    }
  });

  it("境界値は受け付ける", () => {
    assert.equal(requireLines("1"), 1);
    assert.equal(requireLines(String(MAX_LINES)), MAX_LINES);
  });
});

describe("キーワードの検証", () => {
  it("平文はそのまま渡す", () => {
    assert.equal(requireKeyword("500"), "500");
    assert.equal(requireKeyword(undefined), undefined);
  });

  it("空・長すぎ・制御文字は拒否する", () => {
    assert.throws(() => requireKeyword(""), RuntimeOpsError);
    assert.throws(() => requireKeyword("x".repeat(101)), RuntimeOpsError);
    assert.throws(() => requireKeyword("a\nb"), RuntimeOpsError);
  });
});

describe("引数の解析", () => {
  it("長オプションを両方の記法で読む", () => {
    assert.deepEqual(parseArgs(["errors", "--env", "develop", "--lines=10"]), {
      command: "errors",
      flags: { env: "develop", lines: "10" },
    });
  });

  it("位置引数を拒否して暗黙の解釈を防ぐ", () => {
    assert.throws(() => parseArgs(["errors", "develop"]), RuntimeOpsError);
    assert.throws(() => parseArgs([]), RuntimeOpsError);
    assert.throws(() => parseArgs(["--env", "develop"]), RuntimeOpsError);
  });

  it("値の無いオプションを拒否する", () => {
    assert.throws(() => parseArgs(["errors", "--env"]), RuntimeOpsError);
    assert.throws(() => parseArgs(["errors", "--env", "--lines", "10"]), RuntimeOpsError);
  });
});

describe("コマンド別の許可オプション", () => {
  it("そのコマンドが解釈しないオプションを拒否する", () => {
    assert.throws(() => requireKnownFlags({ keyword: "x" }, ["env"]), RuntimeOpsError);
  });

  it("許可された組み合わせは通す", () => {
    assert.doesNotThrow(() => requireKnownFlags({ env: "develop", lines: "10" }, ["env", "lines"]));
  });
});

describe("出力量の上限", () => {
  it("新しい行だけを残し、切り詰めたことを明示する", () => {
    const capped = capOutput(
      Array.from({ length: 500 }, (_, index) => `line ${index}`).join("\n"),
      10,
    );

    assert.match(capped, /kept the last 10 of 500 lines/);
    assert.match(capped, /line 499$/);
    assert.doesNotMatch(capped, /line 100\b/);
  });

  it("一行が巨大でもバイト数で頭打ちにする", () => {
    const capped = capOutput("x".repeat(MAX_OUTPUT_BYTES * 2), MAX_LINES);

    assert.match(capped, /kept the last \d+ bytes/);
    assert.ok(Buffer.byteLength(capped, "utf8") <= MAX_OUTPUT_BYTES + 200);
  });

  it("短い出力はそのまま返す", () => {
    assert.equal(capOutput("one\ntwo", 10), "one\ntwo");
  });
});

describe("CLI出力の整形", () => {
  it("スキーマを仮定せずJSONを整形する", () => {
    assert.equal(
      formatCliOutput('{"anything":[1,2]}', 50),
      '{\n  "anything": [\n    1,\n    2\n  ]\n}',
    );
  });

  it("JSONでない出力は本文のまま渡す", () => {
    assert.equal(formatCliOutput("plain text", 50), "plain text");
  });

  it("空の出力はその旨を返す", () => {
    assert.equal(formatCliOutput("   ", 50), "[no output]");
  });

  it("巨大な応答は切り詰める", () => {
    assert.match(formatCliOutput("y".repeat(MAX_OUTPUT_BYTES * 2), MAX_LINES), /truncated/);
  });
});

const APACHE = "[Thu Aug 13 12:00:00 2026] [core:error] [pid 1:tid 2] [client 1.2.3.4:1]";

describe("ログ行の帰属", () => {
  it("アクセスログは先頭のホスト欄だけで帰属を決める", () => {
    assert.equal(accessLogHost("www.example.com 203.0.113.1 - - GET / 200"), "www.example.com");
    assert.equal(accessLogHost("- 203.0.113.1"), undefined);
  });

  it("URLやRefererにdevが出るだけの本番行を落とさない", () => {
    const log = [
      'www.example.com 203.0.113.1 - - "GET /" "https://dev.example.com/x" "UA"',
      "dev.example.com 203.0.113.2 - - GET / 200",
    ].join("\n");
    const { kept, other } = attributeLogLines(log, "access", "example.com", "example.com");

    assert.equal(kept.length, 1);
    assert.equal(other, 1);
    assert.match(kept[0]!, /^www\.example\.com/);
  });

  it("Refererにdevがあるだけでdevelopへ誤帰属しない", () => {
    const log = 'www.example.com 203.0.113.1 - - "GET /" "https://dev.example.com/x"';
    const { kept, other } = attributeLogLines(log, "access", "dev.example.com", "example.com");

    assert.deepEqual(kept, []);
    assert.equal(other, 1);
  });

  it("エラーログの配備パスから該当サイトを読む", () => {
    const apex = "example.com";

    assert.equal(
      errorLogSite(
        `${APACHE} AH01630: client denied by server configuration: /home/u/example.com/public_html/.env`,
        apex,
      ),
      "example.com",
    );
    assert.equal(
      errorLogSite(
        `${APACHE} AH01630: client denied by server configuration: /home/u/example.com/dev.example.com/public/.env`,
        apex,
      ),
      "dev.example.com",
    );
    assert.equal(
      errorLogSite(
        `${APACHE} AH01630: client denied by server configuration: /home/u/example.com/static.example.com/a.css`,
        apex,
      ),
      "static.example.com",
    );
  });

  it("public_html でも有効なサブドメインでもない兄弟ディレクトリを本番にしない", () => {
    const apex = "example.com";

    assert.equal(
      errorLogSite(
        `${APACHE} AH01630: client denied by server configuration: /home/u/example.com/cache/x`,
        apex,
      ),
      undefined,
    );
    assert.equal(
      errorLogSite(
        `${APACHE} AH01630: client denied by server configuration: /home/u/example.com/htpasswd/dev.example.com/.htpasswd`,
        apex,
      ),
      undefined,
    );
  });

  it("URLパスや参照は配備パスと見なさない", () => {
    const apex = "example.com";

    assert.equal(errorLogSite("GET /example.com/public_html/ HTTP/1.1", apex), undefined);
    assert.equal(errorLogSite("referer https://dev.example.com/x", apex), undefined);
    assert.equal(errorLogSite("[mpm_prefork:notice] caught SIGTERM", apex), undefined);
  });

  it("Apacheのメッセージ形式でない行は帰属させない", () => {
    const apex = "example.com";

    for (const line of [
      "referer: /home/u/example.com/public_html/.env",
      "request: /home/u/example.com/dev.example.com/public/.env",
      "GET /home/u/example.com/dev.example.com/public/.env HTTP/1.1",
      "referer: https://attacker.test/home/u/example.com/public_html/.env",
    ]) {
      assert.equal(errorLogSite(line, apex), undefined, line);
    }
  });

  it("PHPメッセージは実際のスクリプト位置を採る", () => {
    const apex = "example.com";
    const prefix = "[Thu] [proxy_fcgi:error] [client 1.2.3.4] ";

    assert.equal(
      errorLogSite(
        `${prefix}PHP Warning: invalid value /home/u/example.com/dev.example.com/x ` +
          "in /home/u/example.com/public_html/index.php",
        apex,
      ),
      "example.com",
    );
    assert.equal(
      errorLogSite(
        `${prefix}PHP Fatal error: boom in /home/u/example.com/dev.example.com/public/index.php ` +
          "on line 3",
        apex,
      ),
      "dev.example.com",
    );
  });

  it("1行が複数サイトを指す場合はどちらにも帰属させない", () => {
    assert.equal(
      errorLogSite(
        `${APACHE} AH01276: Cannot serve directory /home/u/example.com/dev.example.com/a: ` +
          "File does not exist: /home/u/example.com/public_html/b",
        "example.com",
      ),
      undefined,
    );
  });

  it("PHPが複数のパスを含む場合も先頭を鵜呑みにしない", () => {
    assert.equal(
      errorLogSite(
        "[Thu] [proxy_fcgi:error] [client 1.2.3.4] PHP Warning: invalid text " +
          "in /home/u/example.com/dev.example.com/fake.php " +
          "in /home/u/example.com/public_html/index.php on line 12",
        "example.com",
      ),
      undefined,
    );
  });

  it("サーバーが生成すると分かっている形式以外からはパスを読まない", () => {
    for (const message of [
      "client sent referer: /home/u/example.com/dev.example.com/x",
      "unknown directive at /home/u/example.com/public_html/x",
      "some header: /home/u/example.com/public_html/x",
    ]) {
      assert.equal(errorLogSite(`${APACHE} ${message}`, "example.com"), undefined, message);
    }
  });

  it("Apacheが末尾へ付ける referer は配備パスとして読まない", () => {
    const apex = "example.com";
    const prefix = "[Thu] [core:error] [client 1.2.3.4] ";

    assert.equal(
      errorLogSite(`${prefix}denied, referer: /home/u/example.com/dev.example.com/y`, apex),
      undefined,
    );
    assert.equal(
      errorLogSite(
        `${prefix}File does not exist: /home/u/example.com/public_html/x, ` +
          "referer: /home/u/example.com/dev.example.com/y",
        apex,
      ),
      "example.com",
    );
  });

  it("エラーログは本番へ他サブドメインや兄弟ディレクトリを混ぜない", () => {
    const log = [
      `${APACHE} AH01630: client denied by server configuration: /home/u/example.com/public_html/.env`,
      `${APACHE} AH01630: client denied by server configuration: /home/u/example.com/static.example.com/a.css`,
      `${APACHE} AH01630: client denied by server configuration: /home/u/example.com/dev.example.com/public/.env`,
    ].join("\n");
    const { kept, other } = attributeLogLines(log, "error", "example.com", "example.com");

    assert.equal(kept.length, 1);
    assert.equal(other, 2);
    assert.match(kept[0]!, /public_html/);
  });

  it("エラーログのdevelopは配備パスで正方向に判定する", () => {
    const log = [
      `${APACHE} AH01630: client denied by server configuration: /home/u/example.com/dev.example.com/public/.env`,
      `${APACHE} AH01630: client denied by server configuration: /home/u/example.com/public_html/.env`,
      `${APACHE} AH00037: Symbolic link not allowed or link target not accessible: /var/www/x`,
    ].join("\n");
    const { kept, other, unattributable } = attributeLogLines(
      log,
      "error",
      "dev.example.com",
      "example.com",
    );

    assert.equal(kept.length, 1);
    assert.equal(other, 1);
    assert.equal(unattributable, 1);
  });

  it("サイトを名乗らない行は帰属させず別に数える", () => {
    const { kept, unattributable } = attributeLogLines(
      "[mpm_prefork:notice] caught SIGTERM",
      "error",
      "example.com",
      "example.com",
    );

    assert.deepEqual(kept, []);
    assert.equal(unattributable, 1);
  });

  it("空のログは1行として数えない", () => {
    assert.deepEqual(attributeLogLines("", "error", "example.com", "example.com"), {
      kept: [],
      other: 0,
      unattributable: 0,
    });
  });
});

describe("ログ応答の取り出し", () => {
  it("log文字列を切り詰めずそのまま返す", () => {
    const log = Array.from({ length: 300 }, (_, index) => `line ${index}`).join("\n");
    const extracted = extractLogPayload(JSON.stringify({ domain: "example.com", log }));

    assert.equal(extracted.split("\n").length, 300);
    assert.doesNotMatch(extracted, /truncated/);
  });

  it("巨大なログでも帰属判定前に行を落とさない", () => {
    // 前半に対象環境、後半に別環境を置く。バイト上限を先に適用すると前半が消える
    const lines = [
      ...Array.from(
        { length: 100 },
        (_, i) => `www.example.com - - "GET /p${i} ${"x".repeat(950)}"`,
      ),
      ...Array.from(
        { length: 100 },
        (_, i) => `dev.example.com - - "GET /d${i} ${"x".repeat(950)}"`,
      ),
    ];
    const payload = extractLogPayload(
      JSON.stringify({ domain: "example.com", log: lines.join("\n") }),
    );
    const production = attributeLogLines(payload, "access", "example.com", "example.com");
    const develop = attributeLogLines(payload, "access", "dev.example.com", "example.com");

    assert.ok(Buffer.byteLength(payload, "utf8") > MAX_OUTPUT_BYTES);
    assert.equal(production.kept.length, 100);
    assert.equal(develop.kept.length, 100);
    // 切り詰め通知や不完全な行が帰属集計へ混ざらない
    assert.equal(production.unattributable, 0);
    assert.equal(develop.unattributable, 0);
  });

  it("空ログは空文字のまま返し、表示文字列を混ぜない", () => {
    assert.equal(extractLogPayload(JSON.stringify({ domain: "example.com", log: "" })), "");
  });

  it("log項目を持たない応答はJSONとして扱う", () => {
    assert.match(extractLogPayload(JSON.stringify({ crons: [] })), /"crons"/);
  });

  it("JSONでない応答も落とさず返す", () => {
    assert.equal(extractLogPayload("エラー [VALIDATION_ERROR]"), "エラー [VALIDATION_ERROR]");
  });
});

describe("環境名の検証", () => {
  it("継承プロパティを環境として受け付けない", () => {
    for (const name of ["constructor", "__proto__", "toString", "valueOf", "hasOwnProperty"]) {
      assert.throws(() => resolveEnvironmentName({ env: name }), RuntimeOpsError);
    }
  });
});

describe("稼働中コミットの特定", () => {
  const RUN = {
    attempt: 1,
    conclusion: "success",
    createdAt: "2026-08-05T03:52:38Z",
    databaseId: 30973504152,
    headSha: "67635dc2e78e94b04a2486023da064e874c39e03",
    status: "completed",
    updatedAt: "2026-08-05T04:02:00Z",
    url: "https://github.com/o/r/actions/runs/1",
  };

  it("delivery run から SHA と実行URLを読む", () => {
    const [run] = parseRuns(JSON.stringify([RUN]));

    assert.equal(run?.headSha, RUN.headSha);
    assert.equal(run?.url, RUN.url);
  });

  it("必須項目を欠く応答は run として採用しない", () => {
    assert.deepEqual(parseRuns("[]"), []);
    assert.deepEqual(parseRuns("{}"), []);
    assert.deepEqual(parseRuns(JSON.stringify([{ ...RUN, headSha: 123 }])), []);
    assert.deepEqual(parseRuns(JSON.stringify([{ ...RUN, databaseId: "x" }])), []);
    assert.deepEqual(parseRuns(JSON.stringify([{ ...RUN, updatedAt: 1 }])), []);
  });

  const jobs = (conclusion: string | null) =>
    JSON.stringify({
      jobs: [
        { conclusion: "success", name: "lint", steps: [] },
        {
          conclusion: "failure",
          name: "deploy",
          steps: [
            {
              completedAt: "2026-08-05T04:04:53Z",
              conclusion,
              name: "Deploy to XServer via Rsync",
            },
          ],
        },
      ],
    });

  it("転送完了時刻はステップから取り、run の更新時刻を代用しない", () => {
    assert.equal(rsyncOutcome(jobs("success"), "completed").completedAt, "2026-08-05T04:04:53Z");
    assert.equal(rsyncOutcome(jobs("failure"), "completed").completedAt, undefined);
  });

  it("履歴を読み切ったときだけ走査を確定とみなす", () => {
    const run = (createdAt: string) => ({ ...RUN, createdAt });
    const full = Array.from({ length: 1000 }, () => run("2026-08-12T00:00:00Z"));

    assert.equal(scanIsConclusive([run("2026-08-12T00:00:00Z")]), true);
    assert.equal(scanIsConclusive(full), false);
    // 取得範囲に古い run が含まれていても、上限に達していれば見落とし得る。
    // 再実行は作成から30日以内に可能なので、範囲外の古い run が後から配置していることがある
    assert.equal(scanIsConclusive([...full.slice(1), run("2026-01-01T00:00:00Z")]), false);
  });

  it("rsync が成功していればサーバーは置き換わっている", () => {
    assert.equal(rsyncOutcome(jobs("success"), "completed").outcome, "done");
  });

  it("rsync 失敗・実行中・キャンセルは部分転送の可能性があるため不明とする", () => {
    assert.equal(rsyncOutcome(jobs("failure"), "completed").outcome, "unknown");
    assert.equal(rsyncOutcome(jobs(null), "in_progress").outcome, "unknown");
    // 実行中の転送もキャンセルされうるため skipped と同義ではない
    assert.equal(rsyncOutcome(jobs("cancelled"), "completed").outcome, "unknown");
  });

  it("先行ステップ失敗による skipped だけをサーバー無傷と断定する", () => {
    assert.equal(rsyncOutcome(jobs("skipped"), "completed").outcome, "untouched");
  });

  it("deploy ジョブの skipped が未転送の証拠になる", () => {
    const skipped = JSON.stringify({
      jobs: [
        { conclusion: "failure", name: "lint", steps: [] },
        { conclusion: "skipped", name: "deploy", steps: [] },
      ],
    });

    assert.equal(rsyncOutcome(skipped, "completed").outcome, "untouched");
  });

  it("deploy ジョブが見つからなければ不明とする", () => {
    const lintOnly = JSON.stringify({ jobs: [{ conclusion: "failure", name: "lint", steps: [] }] });

    assert.equal(rsyncOutcome(lintOnly, "completed").outcome, "unknown");
    assert.equal(rsyncOutcome(lintOnly, "in_progress").outcome, "unknown");
    assert.equal(rsyncOutcome(JSON.stringify({}), "completed").outcome, "unknown");
  });

  it("対象ステップが無い場合、deploy 成功時のみ転送済みとみなす", () => {
    const withoutStep = (conclusion: string) =>
      JSON.stringify({ jobs: [{ conclusion, name: "deploy", steps: [{ name: "Other" }] }] });

    assert.equal(rsyncOutcome(withoutStep("success"), "completed").outcome, "done");
    assert.equal(rsyncOutcome(withoutStep("failure"), "completed").outcome, "unknown");
  });

  it("作成順ではなく最終更新順に並べる", () => {
    const run = (databaseId: number, createdAt: string, updatedAt: string) => ({
      ...RUN,
      createdAt,
      databaseId,
      updatedAt,
    });
    // 古い run を後から再実行すると、新しく作られた run より後にサーバーを更新する
    const ordered = orderByLastUpdate([
      run(2, "2026-08-02T00:00:00Z", "2026-08-02T00:10:00Z"),
      run(1, "2026-08-01T00:00:00Z", "2026-08-03T00:00:00Z"),
    ]);

    assert.deepEqual(
      ordered.map((entry) => entry.databaseId),
      [1, 2],
    );
  });
});

describe("表示行のバイト制限", () => {
  it("行境界を保ち、末尾の行から収める", () => {
    const lines = ["a".repeat(30), "b".repeat(30), "c".repeat(30)];

    // 2行ぶん（30 + 1 + 30）は入るが3行目は入らない
    assert.deepEqual(fitWithinBytes(lines, 61), [lines[1], lines[2]]);
    assert.deepEqual(fitWithinBytes(lines, 1000), lines);
  });

  it("欠けた行を作らず、入らなければ何も返さない", () => {
    assert.deepEqual(fitWithinBytes(["x".repeat(100)], 50), []);
    assert.deepEqual(fitWithinBytes([], 100), []);
  });

  it("マルチバイト文字を文字数ではなくバイト数で数える", () => {
    // 「あ」はUTF-8で3バイト。10文字=30バイトなので29バイトには入らない
    assert.deepEqual(fitWithinBytes(["あ".repeat(10)], 29), []);
    assert.deepEqual(fitWithinBytes(["あ".repeat(10)], 30), ["あ".repeat(10)]);
  });

  it("報告する件数は切り詰め後の実表示件数と一致する", () => {
    const lines = Array.from({ length: 100 }, (_, index) => `line ${index} ${"x".repeat(950)}`);
    const shown = fitWithinBytes(lines, MAX_OUTPUT_BYTES);

    assert.ok(shown.length < lines.length);
    assert.equal(shown.join("\n").split("\n").length, shown.length);
    assert.ok(Buffer.byteLength(shown.join("\n"), "utf8") <= MAX_OUTPUT_BYTES);
  });
});

describe("巨大な1行の切り詰め", () => {
  it("上限以下ならそのまま返す", () => {
    assert.equal(tailWithinBytes("short", 100), "short");
  });

  it("末尾を残しつつ上限に収める", () => {
    const tail = tailWithinBytes(`${"a".repeat(100)}TAIL`, 10);

    assert.ok(Buffer.byteLength(tail, "utf8") <= 10);
    assert.ok(tail.endsWith("TAIL"));
  });

  it("マルチバイト文字の途中で切らない", () => {
    // 「あ」は3バイト。10バイト境界は文字の途中に落ちる
    const tail = tailWithinBytes("あ".repeat(10), 10);

    assert.ok(Buffer.byteLength(tail, "utf8") <= 10);
    assert.doesNotMatch(tail, /\uFFFD/);
    assert.equal(tail, "あああ");
  });
});
