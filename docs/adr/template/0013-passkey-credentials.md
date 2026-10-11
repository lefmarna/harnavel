# Passkey credentials

Passkeys sit beside passwords and Google sign-in as an alternative credential, so losing one
provider never locks an account out. Password confirmation guards the security page for any account
holding a password, a passkey, or a linked Google account ([TADR-0016](0016-account-takeover-defenses.md)),
even when Google sign-in is not configured. An account with none of them reaches it unguarded,
because that page issues its first credential.

Passkey user handles derive from `PASSKEYS_USER_HANDLE_SECRET`, independent of `APP_KEY` so that
rotating the application key does not change new handles. Fortify silently falls back to `APP_KEY`,
so production refuses to boot without the secret. An app that already holds passkeys registered
under the fallback pins the secret to its current `APP_KEY` value.

New handles are a 64-byte ASCII hex HMAC because iOS Safari can corrupt raw binary handles
([issue #41](https://github.com/laravel/passkeys-server/issues/41)). `User::getPasskeyUserHandle()`
overrides the package until a stable release of the Composer package `laravel/passkeys` derives the
same value from the same table, primary key, and secret
([PR #42](https://github.com/laravel/passkeys-server/pull/42)). Verification uses each credential's
stored handle, so binary and hex credentials coexist; a handle already corrupted on an authenticator
cannot be repaired, and its owner replaces the passkey after signing in another way.
