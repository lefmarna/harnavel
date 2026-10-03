# Passkey credentials

Passkeys sit beside passwords and Google sign-in as an alternative credential, so losing one
provider never locks an account out. Password confirmation therefore guards the security page only
where it can succeed: an account holding neither a password nor a passkey reaches it unguarded,
because the alternative strands Google-only users outside the one screen that issues their first
credential.

Passkey user handles derive from `PASSKEYS_USER_HANDLE_SECRET`, which must stay independent of
`APP_KEY` so rotating the application key does not change the handle used for new registrations. Fortify
falls back to `APP_KEY` silently when the secret is missing, so production refuses to boot unless
the secret is configured explicitly, while local and CI environments accept the fallback for
convenience. An app that already holds passkeys registered under the fallback pins the secret to
its current `APP_KEY` value to preserve the registration identity and decouple it from later key
rotation. Verification uses each credential's stored handle, rather than deriving it from the current
secret again.

New registrations use the 64-byte ASCII hex HMAC proposed in
[upstream PR #42](https://github.com/laravel/passkeys-server/pull/42), because iOS Safari can corrupt
raw binary handles ([issue #41](https://github.com/laravel/passkeys-server/issues/41)). The template
overrides only `User::getPasskeyUserHandle()` while the PHP implementation lacks the fix. It is
maintained in the [`laravel/passkeys-server` repository](https://github.com/laravel/passkeys-server)
and distributed as the Composer package `laravel/passkeys` (currently v0.2.1), separately from the
frontend npm package `@laravel/passkeys`. Once a stable release of that PHP Composer package
implements the same hex HMAC derivation for the same table, primary key, and secret, update that
dependency and remove the override after the registration and legacy-login tests pass. Updating
the frontend package alone does not satisfy this removal condition.

Existing credential records must keep their original handles: intact binary credentials still verify
against their stored values, and binary and hex credentials can coexist on one account. Do not rewrite
credential JSON or rotate the handle secret when adopting this change. Handles already corrupted on
an authenticator cannot be repaired server-side; affected users must sign in with another credential,
register a replacement passkey, and then remove the affected one. This override does not change
the PHP package's v0.2.1 exception handling for those already-corrupted credentials.
