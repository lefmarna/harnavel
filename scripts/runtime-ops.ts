import { spawnSync } from "node:child_process";
import path from "node:path";
import { fileURLToPath } from "node:url";

export const DEFAULT_LINES = 50;
export const MAX_LINES = 200;
export const MAX_OUTPUT_BYTES = 64 * 1024;
export const MAX_KEYWORD_LENGTH = 100;

// bootstrap/app.php registers the health route and delivery.yml rejects any other
// health path, so the path is an invariant rather than per-application state.
const HEALTH_PATH = "/up";
const HOSTNAME_PATTERN = /^[a-z0-9]([a-z0-9-]*[a-z0-9])?(\.[a-z0-9]([a-z0-9-]*[a-z0-9])?)+$/i;

export type RuntimeEnvironment = {
  basicAuth: boolean;
  branch: string;
  host: string;
  xserverLogDomain: string;
};

export type RuntimeEnvironments = Record<string, RuntimeEnvironment>;

/**
 * `delivery.yml` deploys `main` and `develop` only, and picks the GitHub Environment from that same
 * pair, so the mapping is an invariant of the template rather than per-application state. Keeping it
 * here means the target branch is known before any file is read.
 */
export const ENVIRONMENT_BRANCHES: Record<string, string> = {
  develop: "develop",
  production: "main",
};

export class RuntimeOpsError extends Error {}

function fail(message: string): never {
  throw new RuntimeOpsError(message);
}

function requireHostname(value: string | undefined, source: string): string {
  const host = value?.replaceAll("\\", "");

  if (host === undefined || !HOSTNAME_PATTERN.test(host)) {
    fail(
      `Could not read the served hostname from ${source}. Every environment is derived from the ` +
        "deployed rewrite rules, so that file must keep its host condition.",
    );
  }

  return host;
}

/**
 * The apex the production rules allow, taken from the deny condition that lists the optional
 * subdomains: `!^(?:(?:www|dev)\.)?example\.com$`. This is both the host production serves and the
 * domain XServer files the whole family's web logs under.
 */
export function deriveProductionHost(htaccess: string): string {
  return requireHostname(
    /HTTP_HOST}\s*!\^(?:\(\?:[^)]*\)\\\.\)\?)?([A-Za-z0-9.\\-]+?)\$/.exec(htaccess)?.[1],
    ".htaccess",
  );
}

/**
 * The single host the develop rules allow: `!^dev\.example\.com$`.
 */
export function deriveDevelopHost(htaccessDevelop: string): string {
  return requireHostname(
    /HTTP_HOST}\s*!\^([A-Za-z0-9.\\-]+?)\$/.exec(htaccessDevelop)?.[1],
    ".htaccess.develop",
  );
}

export function requiresBasicAuth(htaccess: string): boolean {
  return /^\s*require\s+valid-user/im.test(htaccess);
}

/**
 * Environments are derived from the files that actually govern the deployment rather than restated
 * in a manifest, so the mapping cannot drift from the rules the server enforces. `delivery.yml`
 * deploys only `main` and `develop` and selects the GitHub Environment from that same pair, so the
 * branch names are an invariant of the template rather than per-application state.
 */
export function deriveEnvironments(
  htaccess: string,
  htaccessDevelop: string | undefined,
): RuntimeEnvironments {
  const productionHost = deriveProductionHost(htaccess);
  const environments: RuntimeEnvironments = {
    production: {
      basicAuth: requiresBasicAuth(htaccess),
      branch: "main",
      host: productionHost,
      xserverLogDomain: productionHost,
    },
  };

  if (htaccessDevelop !== undefined) {
    const developHost = deriveDevelopHost(htaccessDevelop);

    environments.develop = {
      basicAuth: requiresBasicAuth(htaccessDevelop),
      branch: "develop",
      host: developHost,
      // A subdomain's logs are filed under the apex; an unrelated domain keeps its own.
      xserverLogDomain: developHost.endsWith(`.${productionHost}`) ? productionHost : developHost,
    };
  }

  return environments;
}

/**
 * Resolves the target environment without ever guessing. An explicit name wins; otherwise the
 * branch must match exactly one environment. An unrecognised branch is an error rather than a
 * fallback, so no unregistered branch can ever be treated as production.
 */
export function resolveEnvironmentName(options: {
  branch?: string | undefined;
  env?: string | undefined;
}): string {
  const known = Object.keys(ENVIRONMENT_BRANCHES).sort().join(", ");

  if (options.env !== undefined) {
    // Own properties only: a plain object answers "constructor" and "__proto__" from its prototype,
    // which would pass an undefined check and resolve to an environment that does not exist.
    if (!Object.hasOwn(ENVIRONMENT_BRANCHES, options.env)) {
      fail(`Unknown environment "${options.env}". Available environments: ${known}.`);
    }

    return options.env;
  }

  const branch = options.branch;

  if (branch === undefined || branch === "") {
    fail(`Could not determine the current branch. Pass --env explicitly (one of: ${known}).`);
  }

  const matches = Object.entries(ENVIRONMENT_BRANCHES).filter(([, mapped]) => mapped === branch);
  const [match] = matches;

  if (match === undefined) {
    fail(
      `Branch "${branch}" is not mapped to a deployed environment. ` +
        `Pass --env explicitly (one of: ${known}).`,
    );
  }

  return match[0];
}

export function requireLines(value: string | undefined): number {
  if (value === undefined) {
    return DEFAULT_LINES;
  }

  if (!/^\d+$/.test(value)) {
    fail(`--lines must be a whole number between 1 and ${MAX_LINES}.`);
  }

  const lines = Number(value);

  if (lines < 1 || lines > MAX_LINES) {
    fail(`--lines must be between 1 and ${MAX_LINES} (requested ${lines}).`);
  }

  return lines;
}

export function requireKeyword(value: string | undefined): string | undefined {
  if (value === undefined) {
    return undefined;
  }

  if (value === "" || value.length > MAX_KEYWORD_LENGTH) {
    fail(`--keyword must be between 1 and ${MAX_KEYWORD_LENGTH} characters.`);
  }

  if (!/^\P{Cc}+$/u.test(value)) {
    fail("--keyword must not contain control characters.");
  }

  return value;
}

/**
 * Last line of defence against flooding the agent's context: the remote side is already asked for
 * a bounded number of lines, but a single log line can still be arbitrarily long.
 */
export function capOutput(
  text: string,
  maxLines: number = MAX_LINES,
  maxBytes: number = MAX_OUTPUT_BYTES,
): string {
  const notices: string[] = [];
  const lines = text.replace(/\n$/, "").split("\n");
  let kept = lines;

  if (lines.length > maxLines) {
    kept = lines.slice(-maxLines);
    notices.push(`[truncated: kept the last ${maxLines} of ${lines.length} lines]`);
  }

  let output = kept.join("\n");

  if (Buffer.byteLength(output, "utf8") > maxBytes) {
    output = Buffer.from(output, "utf8").subarray(-maxBytes).toString("utf8");
    notices.push(`[truncated: kept the last ${maxBytes} bytes]`);
  }

  return notices.length > 0 ? `${notices.join(" ")}\n${output}` : output;
}

/**
 * The XServer CLI response schema is not part of its published reference, so no field is assumed.
 * Valid JSON is re-indented for readability and anything else is passed through as text.
 */
export function formatCliOutput(stdout: string, maxLines: number): string {
  const trimmed = stdout.trim();

  if (trimmed === "") {
    return "[no output]";
  }

  try {
    return capOutput(JSON.stringify(JSON.parse(trimmed), null, 2), maxLines);
  } catch {
    return capOutput(trimmed, maxLines);
  }
}

/**
 * `server log access|error` answers with `{ domain, log }`, where `log` is the raw lines in one
 * string. The payload is returned whole and uncapped: capping here would decide which lines exist
 * before anything knows which environment they belong to, and a shared log whose tail happens to
 * be another environment's would answer "no matching lines" while the requested environment's own
 * lines sat just inside the window that was fetched. Size is bounded once, on what is displayed.
 */
export function extractLogPayload(stdout: string): string {
  const trimmed = stdout.trim();
  let decoded: unknown;

  try {
    decoded = JSON.parse(trimmed);
  } catch {
    return trimmed;
  }

  if (typeof decoded === "object" && decoded !== null && "log" in decoded) {
    const { log } = decoded as { log: unknown };

    if (typeof log === "string") {
      return log.trim() === "" ? "" : log;
    }
  }

  return trimmed === "" ? "" : JSON.stringify(decoded, null, 2);
}

export type ParsedArgs = {
  command: string;
  flags: Record<string, string>;
};

export function parseArgs(argv: readonly string[]): ParsedArgs {
  const [command, ...rest] = argv;

  if (command === undefined || command.startsWith("-")) {
    fail("Usage: pnpm runtime-ops <env|status|errors|access|cron> [flags]");
  }

  const flags: Record<string, string> = {};

  for (let index = 0; index < rest.length; index += 1) {
    const token = rest[index] ?? "";

    if (!token.startsWith("--")) {
      fail(`Unexpected argument "${token}". Every option must be passed as --name value.`);
    }

    const separator = token.indexOf("=");

    if (separator !== -1) {
      flags[token.slice(2, separator)] = token.slice(separator + 1);
      continue;
    }

    const value = rest[index + 1];

    if (value === undefined || value.startsWith("--")) {
      fail(`Option "${token}" requires a value.`);
    }

    flags[token.slice(2)] = value;
    index += 1;
  }

  return { command, flags };
}

export function requireKnownFlags(flags: Record<string, string>, allowed: readonly string[]): void {
  const unknown = Object.keys(flags).filter((flag) => !allowed.includes(flag));

  if (unknown.length > 0) {
    fail(
      `Unsupported option(s): ${unknown.map((flag) => `--${flag}`).join(", ")}. ` +
        `This command accepts: ${allowed.map((flag) => `--${flag}`).join(", ") || "no options"}.`,
    );
  }
}

type CommandResult = {
  status: number;
  stderr: string;
  stdout: string;
};

/**
 * The XServer CLI is a host tool, and installing it through mise puts it on a shim path that
 * non-interactive shells do not inherit. Falling back to `mise exec` keeps the wrapper usable from
 * an agent's shell without asking anyone to hand-manage PATH.
 */
let xserverCommand: readonly string[] | undefined;

function xserverArgv(): readonly string[] {
  if (xserverCommand !== undefined) {
    return xserverCommand;
  }

  for (const candidate of [["xserver"], ["mise", "exec", "--", "xserver"]]) {
    const [command = "", ...rest] = candidate;
    const probe = spawnSync(command, [...rest, "--version"], { shell: false });

    if (probe.error === undefined && probe.status === 0) {
      xserverCommand = candidate;

      return candidate;
    }
  }

  fail(
    "The XServer CLI is not available. Install it as a host tool with " +
      "`mise use -g npm:xserver-cli@latest`, then authenticate with `xserver auth login`.",
  );
}

function execute(command: string, args: readonly string[]): CommandResult {
  const result = spawnSync(command, [...args], {
    encoding: "utf8",
    maxBuffer: 8 * 1024 * 1024,
    shell: false,
  });

  if (result.error !== undefined) {
    const reason =
      (result.error as NodeJS.ErrnoException).code === "ENOENT"
        ? `"${command}" is not installed or not on PATH.`
        : result.error.message;

    fail(reason);
  }

  return {
    status: result.status ?? 1,
    stderr: result.stderr ?? "",
    stdout: result.stdout ?? "",
  };
}

function executeOrFail(command: string, args: readonly string[]): string {
  const result = execute(command, args);

  if (result.status !== 0) {
    fail(`\`${command} ${args.join(" ")}\` failed:\n${capOutput(result.stderr || result.stdout)}`);
  }

  return result.stdout;
}

function runXserver(args: readonly string[]): string {
  const [command, ...prefix] = xserverArgv();

  return executeOrFail(command ?? "xserver", [...prefix, ...args]);
}

export type DeliveryRun = {
  attempt: number | undefined;
  conclusion: string | undefined;
  createdAt: string;
  databaseId: number;
  headSha: string;
  status: string | undefined;
  updatedAt: string;
  url: string;
};

// The step that replaces the live directory. `scripts/test-deploy-lifecycle.ts` pins this name, so
// it is a checked invariant of delivery.yml rather than a guess about the workflow.
const RSYNC_STEP = "Deploy to XServer via Rsync";
const RUN_FIELDS = "attempt,conclusion,createdAt,databaseId,headSha,status,updatedAt,url";
// Runs come back newest-created first, while the release that is live is the one updated last, so
// only reading the whole history proves the scan saw it.
const RUN_SCAN_LIMIT = 1000;

export function parseRuns(stdout: string): DeliveryRun[] {
  const parsed: unknown = JSON.parse(stdout);
  const rows: unknown[] = Array.isArray(parsed) ? parsed : [];
  const runs: DeliveryRun[] = [];

  for (const row of rows) {
    const run = row as Record<string, unknown>;

    if (
      typeof run.headSha === "string" &&
      typeof run.createdAt === "string" &&
      typeof run.updatedAt === "string" &&
      typeof run.url === "string" &&
      typeof run.databaseId === "number"
    ) {
      runs.push({
        attempt: typeof run.attempt === "number" ? run.attempt : undefined,
        conclusion: typeof run.conclusion === "string" ? run.conclusion : undefined,
        createdAt: run.createdAt,
        databaseId: run.databaseId,
        headSha: run.headSha,
        status: typeof run.status === "string" ? run.status : undefined,
        updatedAt: run.updatedAt,
        url: run.url,
      });
    }
  }

  return runs;
}

/**
 * Whether a run replaced the files on the server. Deployment is in place: rsync overwrites the live
 * directory before Composer, migrations, `artisan up`, and the health check run, so a failed run can
 * still have left its own `.htaccess` behind. A failed or unfinished transfer is reported as unknown
 * rather than guessed, because rsync may have copied part of the tree.
 */
export type RsyncResult = {
  completedAt: string | undefined;
  outcome: "done" | "unknown" | "untouched";
};

export function rsyncOutcome(jobsJson: string, runStatus: string | undefined): RsyncResult {
  const parsed: unknown = JSON.parse(jobsJson);
  const container = parsed as { jobs?: unknown };
  const jobs: unknown[] = Array.isArray(container.jobs) ? container.jobs : [];
  const deploy = jobs.find((job) => (job as { name?: unknown }).name === "deploy") as
    | { conclusion?: unknown; steps?: unknown }
    | undefined;

  // Absence is never proof. A deploy job that never ran is reported as skipped, which is what says
  // the files were left alone; a missing job or a missing step means the answer is unavailable.
  if (deploy === undefined) {
    return { completedAt: undefined, outcome: "unknown" };
  }

  if (deploy.conclusion === "skipped") {
    return { completedAt: undefined, outcome: "untouched" };
  }

  const steps: unknown[] = Array.isArray(deploy.steps) ? deploy.steps : [];
  const step = steps.find((entry) => (entry as { name?: unknown }).name === RSYNC_STEP) as
    | { completedAt?: unknown; conclusion?: unknown }
    | undefined;

  if (step === undefined) {
    return {
      completedAt: undefined,
      outcome: runStatus === "completed" && deploy.conclusion === "success" ? "done" : "unknown",
    };
  }

  const completedAt = typeof step.completedAt === "string" ? step.completedAt : undefined;

  if (step.conclusion === "success") {
    return { completedAt, outcome: "done" };
  }

  // Only "skipped" proves the step never ran: a step is skipped when an earlier one already failed.
  // A cancellation can interrupt a transfer that is already under way, which is not the same thing.
  return {
    completedAt: undefined,
    outcome: step.conclusion === "skipped" ? "untouched" : "unknown",
  };
}

/**
 * Runs most recently updated first. Creation order is not deployment order: re-running an older run
 * adds an attempt to it rather than creating a new run, so its files can be the ones on the server
 * even though a different run was created later.
 */
export function orderByLastUpdate(runs: readonly DeliveryRun[]): DeliveryRun[] {
  return [...runs].sort((left, right) => Date.parse(right.updatedAt) - Date.parse(left.updatedAt));
}

function listRuns(branch: string): DeliveryRun[] {
  return parseRuns(
    executeOrFail("gh", [
      "run",
      "list",
      "--workflow",
      "delivery.yml",
      "--branch",
      branch,
      "--limit",
      String(RUN_SCAN_LIMIT),
      "--json",
      RUN_FIELDS,
    ]),
  );
}

/**
 * Whether the scan read every run GitHub still holds. Nothing weaker is sound: runs are listed
 * newest-created first, but any run can have been re-run later, so a run beyond the limit can hold
 * the files that are on the server no matter how old its creation date is. An age cut-off would
 * only bound which runs may still be re-run in future, not which past re-run placed what is live.
 *
 * This bounds the listing, not the truth. Completed runs can be deleted, and a deleted run leaves
 * no trace in the listing, so the release it placed cannot be seen at all. Every answer derived
 * from run history is therefore an inference and is reported as one.
 */
export function scanIsConclusive(runs: readonly DeliveryRun[]): boolean {
  return runs.length < RUN_SCAN_LIMIT;
}

/**
 * The run whose files are on the server right now: the most recently updated one that actually
 * completed its transfer. Runs are walked by last update rather than creation, because re-running an
 * older run adds an attempt to it instead of creating a new one, so its files can be the ones on
 * disk even though another run was created later. A run whose transfer may have been interrupted
 * stops the search instead of being skipped over, because the tree it left behind belongs to no
 * single commit.
 */
function liveDeployment(
  branch: string,
  name: string,
): { placedAt: string | undefined; run: DeliveryRun; warning?: string } {
  const scanned = listRuns(branch);

  if (scanned.length === 0) {
    fail(`No delivery run exists for "${branch}", so nothing has been deployed to ${name}.`);
  }

  if (!scanIsConclusive(scanned)) {
    fail(
      `"${branch}" has at least ${RUN_SCAN_LIMIT} delivery runs, more than one listing returns, ` +
        "so the run whose files are on the server cannot be identified with certainty. Recording " +
        "the released commit on the server would answer this directly.",
    );
  }

  const runs = orderByLastUpdate(scanned);
  const [newest] = runs;

  for (const run of runs) {
    const { completedAt, outcome } = rsyncOutcome(
      executeOrFail("gh", ["run", "view", String(run.databaseId), "--json", "jobs"]),
      run.status,
    );

    if (outcome === "unknown") {
      fail(
        `Delivery run ${run.databaseId} for "${branch}" neither completed its transfer nor ` +
          `clearly left the server untouched, so which commit is live cannot be determined: ` +
          run.url,
      );
    }

    if (outcome === "done") {
      if (run.conclusion === "success" && run === newest) {
        return { placedAt: completedAt, run };
      }

      return {
        placedAt: completedAt,
        run,
        warning:
          run.conclusion === "success"
            ? `a newer run has started since this release; it has not replaced the files yet (${newest?.url ?? ""})`
            : `this release replaced the files but did not complete (${run.conclusion ?? run.status ?? "unfinished"}): ${run.url}`,
      };
    }
  }

  fail(
    `None of the ${scanned.length} delivery runs for "${branch}" placed files on the server, ` +
      `so nothing is known to be deployed to ${name}.`,
  );
}

function readFromRef(ref: string, file: string): string | undefined {
  const result = execute("git", ["show", `${ref}:${file}`]);

  return result.status === 0 ? result.stdout : undefined;
}

function environmentsFromRef(ref: string): RuntimeEnvironments | undefined {
  const htaccess = readFromRef(ref, ".htaccess");

  if (htaccess === undefined) {
    return undefined;
  }

  try {
    return deriveEnvironments(htaccess, readFromRef(ref, ".htaccess.develop"));
  } catch {
    return undefined;
  }
}

function currentBranch(): string | undefined {
  const result = execute("git", ["rev-parse", "--abbrev-ref", "HEAD"]);
  const branch = result.stdout.trim();

  return result.status === 0 && branch !== "" && branch !== "HEAD" ? branch : undefined;
}

/**
 * Whether another environment publishes into the same web log, which is what makes attribution and
 * a widened inspection window necessary.
 */
export function sharesLogDomain(
  environments: RuntimeEnvironments,
  environment: RuntimeEnvironment,
): boolean {
  return Object.values(environments).some(
    (candidate) =>
      candidate.host !== environment.host &&
      candidate.xserverLogDomain === environment.xserverLogDomain,
  );
}

// Apache opens every error line with a timestamp and a module:level tag; a bare line of
// client-supplied text does not, so requiring the shape keeps such text out of attribution.
const APACHE_ERROR_LINE = /^\[[^\]]+\]\s*\[[^\]]*:[^\]]*\]/;

function escapeHost(host: string): string {
  return host.replaceAll(".", "\\.");
}

/**
 * The trailing whole lines that fit a byte budget. Cutting mid-line would emit a fragment whose
 * start is missing, and counting before the cut would report more lines than the output holds, so
 * the budget is spent on complete lines and the count follows from what survives.
 */
export function fitWithinBytes(lines: readonly string[], maxBytes: number): string[] {
  const kept: string[] = [];
  let total = 0;

  for (let index = lines.length - 1; index >= 0; index -= 1) {
    const line = lines[index] ?? "";
    const size = Buffer.byteLength(line, "utf8") + (kept.length > 0 ? 1 : 0);

    if (total + size > maxBytes) {
      break;
    }

    total += size;
    kept.unshift(line);
  }

  return kept;
}

/**
 * The trailing bytes of one line, cut on a character boundary. Slicing a UTF-8 buffer at an
 * arbitrary offset can land inside a multi-byte sequence and leave a replacement character at the
 * front, so the cut advances past any continuation bytes it opens on.
 */
export function tailWithinBytes(text: string, maxBytes: number): string {
  const buffer = Buffer.from(text, "utf8");

  if (buffer.length <= maxBytes) {
    return text;
  }

  let start = buffer.length - maxBytes;

  while (start < buffer.length && ((buffer[start] ?? 0) & 0xc0) === 0x80) {
    start += 1;
  }

  return buffer.subarray(start).toString("utf8");
}

/**
 * The site an error log line belongs to, read from the deployment path. XServer lays an account out
 * as `/home/<account>/<apex>/<site>/`, where `<site>` is `public_html` for the apex itself and the
 * hostname for a subdomain. Anchoring on `/home/<account>/` keeps a request URI that merely contains
 * the apex from being read as a path, and only those two shapes name a site: a sibling directory
 * such as `cache` or `htpasswd` belongs to no environment and must not fall through to the apex.
 */
function siteOfPath(candidate: string, apex: string): string | undefined {
  const segment = new RegExp(`^/home/[^/]+/${escapeHost(apex)}/([^/]+)/`).exec(candidate)?.[1];

  if (segment === undefined) {
    return undefined;
  }

  if (segment === "public_html") {
    return apex;
  }

  return segment.endsWith(`.${apex}`) && HOSTNAME_PATTERN.test(segment) ? segment : undefined;
}

/**
 * The message forms whose path the server resolved itself. Scanning for any `/home/...` token is
 * not safe: a message can quote a string the request supplied — a referer, a header, a PHP value —
 * and that string can name any directory. A form that is not listed here yields nothing, so an
 * unrecognised message is counted as unattributed instead of being credited to a guess.
 */
const SERVER_PATH_ANCHORS: readonly RegExp[] = [
  /client denied by server configuration:\s+(\S+?)[\s,]*$/,
  /File does not exist:\s+(\S+?)[\s,]*$/,
  /Cannot serve directory\s+(\S+?):/,
  /Symbolic link not allowed or link target not accessible:\s+(\S+?)[\s,]*$/,
  /attempt to invoke directory as script:\s+(\S+?)[\s,]*$/,
  /script\s+'([^']+)'\s+not found/,
  // PHP names the failing script after " in ", and may repeat the construct for values it was
  // given, so every occurrence is collected rather than the first one being trusted.
  /\bin (\/home\/\S+?\.php)(?::\d+)?(?=[\s,]|$)/g,
];

export function errorLogSite(line: string, apex: string): string | undefined {
  // Only Apache's own message names a file on disk, and the referer it appends is client-supplied,
  // so the line must carry the bracketed prefix and everything after the referer is cut away.
  if (!APACHE_ERROR_LINE.test(line)) {
    return undefined;
  }

  const [message = ""] = line.split(/,\s*referer:\s*/i);
  const sites = new Set<string>();

  for (const anchor of SERVER_PATH_ANCHORS) {
    for (const match of message.matchAll(
      new RegExp(anchor.source, anchor.flags.replace("g", "") + "g"),
    )) {
      const site = match[1] === undefined ? undefined : siteOfPath(match[1], apex);

      if (site !== undefined) {
        sites.add(site);
      }
    }
  }

  // A message naming more than one site is quoting a path it was given. Refusing to choose keeps
  // the line out of both environments instead of crediting it to the wrong one.
  const [only] = sites;

  return sites.size === 1 ? only : undefined;
}

/**
 * The host an access log line was served by. XServer writes it as the first field, which is the
 * only part of the line that states the vhost; a hostname appearing in the URL, referer, or user
 * agent says nothing about which environment answered.
 */
export function accessLogHost(line: string): string | undefined {
  const [token] = line.split(" ");

  return token !== undefined && HOSTNAME_PATTERN.test(token) ? token : undefined;
}

export type LineAttribution = "other" | "target" | "unattributable";

/**
 * Decides which environment produced one line, positively rather than by subtraction. A substring
 * test is not good enough in either direction: a production request whose referer points at
 * `dev.example.com` would be handed to develop, and the same line would be subtracted from
 * production. A line that names no site is reported as unattributable instead of being credited to
 * whichever environment happened to be asked.
 */
export function classifyLogLine(
  line: string,
  kind: "access" | "error",
  host: string,
  logDomain: string,
): LineAttribution {
  if (kind === "access") {
    const served = accessLogHost(line);

    if (served === undefined) {
      return "unattributable";
    }

    return served === host || served === `www.${host}` ? "target" : "other";
  }

  const site = errorLogSite(line, logDomain);

  if (site === undefined) {
    return "unattributable";
  }

  return site === host ? "target" : "other";
}

export function attributeLogLines(
  log: string,
  kind: "access" | "error",
  host: string,
  logDomain: string,
): { kept: string[]; other: number; unattributable: number } {
  const counts = { other: 0, unattributable: 0 };
  const kept: string[] = [];

  for (const line of log.split("\n")) {
    if (line === "") {
      continue;
    }

    const attribution = classifyLogLine(line, kind, host, logDomain);

    if (attribution === "target") {
      kept.push(line);
    } else {
      counts[attribution] += 1;
    }
  }

  return { kept, ...counts };
}

/**
 * XServer files a subdomain's web logs under its parent domain, so one log carries several sites and
 * every line has to be attributed before it can be trusted. When the log is shared the server-side
 * window is widened to the hard cap first, because trimming to the caller's line count before
 * attribution can return nothing while the environment's own lines sit just outside the window.
 * What was inspected, attributed, and set aside is always stated.
 */
function xserverLog(
  environments: RuntimeEnvironments,
  environment: RuntimeEnvironment,
  kind: "access" | "error",
  flags: Record<string, string>,
): string {
  const lines = requireLines(flags.lines);
  const keyword = requireKeyword(flags.keyword);
  const shared = sharesLogDomain(environments, environment);
  const distinguishable = environment.host !== environment.xserverLogDomain;
  const applied = keyword ?? (distinguishable ? environment.host : undefined);
  const inspected = shared ? MAX_LINES : lines;

  const stdout = runXserver([
    "--format",
    "json",
    "server",
    "log",
    kind,
    "--domain",
    environment.xserverLogDomain,
    "--lines",
    String(inspected),
    ...(applied === undefined ? [] : ["--keyword", applied]),
  ]);

  const payload = extractLogPayload(stdout);
  const { kept, other, unattributable } = attributeLogLines(
    payload,
    kind,
    environment.host,
    environment.xserverLogDomain,
  );
  const selected = kept.slice(-lines);
  const shown = fitWithinBytes(selected, MAX_OUTPUT_BYTES);

  // A line larger than the whole budget still has to say something, so its tail is shown alone and
  // counted as its own kind of result rather than as a line that was withheld.
  const oversized = shown.length === 0 && selected.length > 0;
  const displayed = oversized ? "a truncated tail of 1 oversized line" : `${shown.length} line(s)`;
  const withheld = selected.length - (oversized ? 1 : shown.length);

  const notes = [`${kind} log of ${environment.xserverLogDomain}`];

  notes.push(
    shared
      ? `inspected the last ${inspected} shared line(s), showing ${displayed} attributed to ` +
          environment.host
      : `showing ${displayed}`,
  );

  if (applied !== undefined) {
    notes.push(`server-side filter "${applied}"`);
  }

  if (other > 0) {
    notes.push(`${other} line(s) belonged to another site`);
  }

  if (unattributable > 0) {
    notes.push(`${unattributable} line(s) named no site and were not attributed`);
  }

  if (kept.length > selected.length) {
    notes.push(`${kept.length - selected.length} older attributed line(s) not shown`);
  }

  if (withheld > 0) {
    notes.push(`${withheld} line(s) withheld to stay within ${MAX_OUTPUT_BYTES} bytes`);
  }

  if (keyword !== undefined && distinguishable) {
    notes.push(`WARNING: "${keyword}" replaced the "${environment.host}" filter`);
  }

  const body = oversized
    ? tailWithinBytes(selected.at(-1) ?? "", MAX_OUTPUT_BYTES)
    : shown.length === 0
      ? "[no matching lines]"
      : shown.join("\n");

  return `[${notes.join("; ")}]\n${body}`;
}

function basicAuthCredentials(name: string): string | undefined {
  const variable = `RUNTIME_OPS_BASIC_AUTH_${name.toUpperCase().replaceAll(/[^A-Z0-9]/g, "_")}`;
  const value = process.env[variable];

  return value === undefined || value === "" ? undefined : value;
}

async function checkHealth(name: string, environment: RuntimeEnvironment): Promise<string> {
  const url = `https://${environment.host}${HEALTH_PATH}`;
  const credentials = basicAuthCredentials(name);
  const headers: Record<string, string> =
    credentials === undefined
      ? {}
      : { authorization: `Basic ${Buffer.from(credentials, "utf8").toString("base64")}` };

  let response: Response;

  try {
    response = await fetch(url, {
      headers,
      redirect: "manual",
      signal: AbortSignal.timeout(5_000),
    });
  } catch (error) {
    return `${url} -> no response (${error instanceof Error ? error.message : "unknown error"})`;
  }

  if (response.status === 401 && environment.basicAuth && credentials === undefined) {
    return (
      `${url} -> 401 (expected: this environment is behind basic auth). ` +
      `Set RUNTIME_OPS_BASIC_AUTH_${name.toUpperCase()}="user:password" to check it.`
    );
  }

  return `${url} -> ${response.status}`;
}

function lastDelivery(environment: RuntimeEnvironment): string {
  const stdout = executeOrFail("gh", [
    "run",
    "list",
    "--workflow",
    "delivery.yml",
    "--branch",
    environment.branch,
    "--limit",
    "1",
    "--json",
    "conclusion,createdAt,displayTitle,headSha,status,url",
  ]);

  return formatCliOutput(stdout, 40);
}

function describe(
  name: string,
  environment: RuntimeEnvironment,
  source: string,
  deployment: DeliveryRun,
): string {
  return [
    `environment:        ${name}`,
    `settings read from: ${source}`,
    `deployment run:     ${deployment.url}`,
    `branch:             ${environment.branch}`,
    `host:               ${environment.host}`,
    `xserver log domain: ${environment.xserverLogDomain}`,
    `basic auth:         ${environment.basicAuth ? "yes" : "no"}`,
  ].join("\n");
}

async function main(argv: readonly string[]): Promise<string> {
  const { command, flags } = parseArgs(argv);

  if (command === "cron") {
    requireKnownFlags(flags, []);

    return formatCliOutput(runXserver(["--format", "json", "server", "cron", "list"]), MAX_LINES);
  }

  switch (command) {
    case "env":
    case "status": {
      requireKnownFlags(flags, ["env"]);
      break;
    }
    case "access":
    case "errors": {
      requireKnownFlags(flags, ["env", "keyword", "lines"]);
      break;
    }
    default: {
      fail(`Unknown command "${command}". Available: env, status, errors, access, cron.`);
    }
  }

  const name = resolveEnvironmentName({ branch: currentBranch(), env: flags.env });
  const branch = ENVIRONMENT_BRANCHES[name] ?? fail(`No branch is mapped to "${name}".`);
  const { placedAt, run: deployment, warning } = liveDeployment(branch, name);

  if (execute("git", ["cat-file", "-e", `${deployment.headSha}^{commit}`]).status !== 0) {
    fail(
      `The deployed commit ${deployment.headSha} is not in this clone. Run \`git fetch\` before ` +
        `investigating ${name}.`,
    );
  }

  const environments = environmentsFromRef(deployment.headSha);

  if (environments === undefined) {
    fail(`Could not read the rewrite rules from ${deployment.headSha}, so ${name} is unresolved.`);
  }

  const environment = environments[name];

  if (environment === undefined) {
    fail(`The deployed commit ${deployment.headSha} declares no ${name} environment.`);
  }

  const placed =
    placedAt === undefined ? `run updated ${deployment.updatedAt}` : `placed ${placedAt}`;
  // Inferred, never asserted: the run history it rests on can be pruned, and a deleted run takes
  // the release it placed out of view with it.
  const source = `${deployment.headSha.slice(0, 7)} (${placed}; inferred from delivery runs)`;
  // Disclosure is the mitigation for a resolution that cannot be proven, so it travels with every
  // answer that rests on it, not only with the ones that describe the environment.
  const resolution = `[${name} resolved from ${source}]\n`;
  const banner = warning === undefined ? "" : `WARNING: ${warning}\n\n`;

  switch (command) {
    case "access":
    case "errors": {
      return (
        banner +
        resolution +
        xserverLog(environments, environment, command === "errors" ? "error" : "access", flags)
      );
    }
    case "env": {
      return banner + describe(name, environment, source, deployment);
    }
    case "status": {
      return [
        banner + describe(name, environment, source, deployment),
        "",
        "last delivery run:",
        lastDelivery(environment),
        "",
        "health:",
        await checkHealth(name, environment),
      ].join("\n");
    }
  }
}

if (path.resolve(process.argv[1] ?? "") === fileURLToPath(import.meta.url)) {
  try {
    console.log(await main(process.argv.slice(2)));
  } catch (error) {
    console.error(error instanceof RuntimeOpsError ? error.message : error);
    process.exitCode = 1;
  }
}
