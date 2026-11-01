# Passkey credentials

Passkeys sit beside passwords and Google sign-in as an alternative credential, so losing one
provider never locks an account out. Only an account holding no password, passkey, or Google link
reaches the security page without confirmation, because that page issues its first credential.

Passkey user handles derive from `PASSKEYS_USER_HANDLE_SECRET` rather than `APP_KEY` so rotating
`APP_KEY` does not change new handles. Because Fortify silently falls back to `APP_KEY`, production
refuses to boot without the secret; an app that already holds passkeys registered under the fallback
pins the secret to its current `APP_KEY` value.

New handles are a 64-byte ASCII hex HMAC because iOS Safari can corrupt raw binary handles
([issue #41](https://github.com/laravel/passkeys-server/issues/41)). `User::getPasskeyUserHandle()`
overrides `laravel/passkeys` until a stable release derives the same value from the same table,
primary key, and secret ([PR #42](https://github.com/laravel/passkeys-server/pull/42)).
Verification uses each credential's stored handle, so binary and hex credentials coexist; a handle
already corrupted on an authenticator cannot be repaired, and its owner replaces the passkey after
signing in another way.
