import { execFileSync } from "node:child_process";

export const e2eContainerName = (): string => {
  const container = process.env.PLAYWRIGHT_E2E_CONTAINER;

  if (!container || !/^playwright-e2e-\d+-\d+$/.test(container)) {
    throw new Error("Playwright E2E container name is missing or invalid.");
  }

  return container;
};

export const runArtisan = (args: string[], stdio: "inherit" | "pipe" = "pipe"): string => {
  if (process.env.CI) {
    return execFileSync("php", ["artisan", ...args], { encoding: "utf8", stdio });
  }

  return execFileSync(
    "docker",
    ["exec", "-i", e2eContainerName(), "scripts/run-e2e-artisan.sh", ...args],
    { encoding: "utf8", stdio },
  );
};

/**
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

const phpString = (value: string): string =>
  `'${value.replaceAll("\\", "\\\\").replaceAll("'", "\\'")}'`;

export type UserCredentials = {
  email: string;
  password: string;
};

/** 共有のシードユーザーを汚さない。 */
export const createUser = (): UserCredentials => {
  const credentials = {
    email: `e2e-${Date.now()}-${Math.random().toString(36).slice(2)}@example.com`,
    password: "Test1234",
  };

  runArtisan([
    "tinker",
    "--execute",
    "App\\Models\\User::query()->forceCreate([" +
      "'name' => 'E2E User'," +
      ` 'email' => ${phpString(credentials.email)},` +
      ` 'password' => ${phpString(credentials.password)},` +
      " 'email_verified_at' => now()]);",
  ]);

  return credentials;
};

export const setUserPassword = (email: string, password: string | null): void => {
  const hashed = password === null ? "null" : `Hash::make(${phpString(password)})`;

  runArtisan([
    "tinker",
    "--execute",
    `App\\Models\\User::query()->where('email', ${phpString(email)})->update(['password' => ${hashed}]);`,
  ]);
};

const lastLine = (output: string): string => output.trim().split("\n").at(-1)?.trim() ?? "";

export const requestEmailChangeLink = (email: string, newEmail: string): string =>
  lastLine(
    runArtisan([
      "tinker",
      "--execute",
      "Illuminate\\Support\\Facades\\Mail::fake();" +
        " app(App\\Services\\Account\\EmailChangeService::class)->request(" +
        `App\\Models\\User::query()->where('email', ${phpString(email)})->firstOrFail(), ${phpString(newEmail)});` +
        " echo Illuminate\\Support\\Facades\\Mail::sent(App\\Mail\\VerifyNewEmailMail::class)->first()->url, PHP_EOL;",
    ]),
  );

export const setUserGoogleId = (email: string, googleId: string): void => {
  runArtisan([
    "tinker",
    "--execute",
    `App\\Models\\User::query()->where('email', ${phpString(email)})->update(['google_id' => ${phpString(googleId)}]);`,
  ]);
};
