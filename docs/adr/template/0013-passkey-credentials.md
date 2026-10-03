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
overrides only `User::getPasskeyUserHandle()` while `laravel/passkeys` v0.2.1 lacks the fix. Once a
stable upstream version implements the same derivation, update the dependency and remove that
override, retaining the registration and legacy-login tests.

Existing credential records must keep their original handles: intact binary credentials still verify
against their stored values, and binary and hex credentials can coexist on one account. Do not rewrite
credential JSON or rotate the handle secret when adopting this change. Handles already corrupted on
an authenticator cannot be repaired server-side; affected users must sign in with another credential,
register a replacement passkey, and then remove the affected one. This override does not change
v0.2.1's exception handling for those already-corrupted credentials.
