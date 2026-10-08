# Passkey credentials

Passkeys sit beside passwords and Google sign-in as an alternative credential, so losing one
provider never locks an account out. Password confirmation therefore guards the security page for
any account holding a password, a passkey, or a linked Google account, confirmed by a fresh Google
sign-in ([TADR-0016](0016-account-takeover-defenses.md)). An account with none of them reaches it
unguarded, because the alternative strands it outside the one screen that issues its first
credential. A linked account stays guarded even when Google sign-in is not configured, so a
configuration gap never opens settings to a stolen session.

Passkey user handles derive from `PASSKEYS_USER_HANDLE_SECRET`, which stays independent of
`APP_KEY` so rotating the application key does not change the handle used for new registrations.
Fortify silently falls back to `APP_KEY` when the secret is missing, so production refuses to boot
without it, while local and CI environments accept the fallback. An app that already holds passkeys
registered under the fallback pins the secret to its current `APP_KEY` value.

New registrations use a 64-byte ASCII hex HMAC because iOS Safari can corrupt raw binary handles
([issue #41](https://github.com/laravel/passkeys-server/issues/41)). The template overrides only
`User::getPasskeyUserHandle()` until a stable release of the Composer package `laravel/passkeys`
implements the same derivation for the same table, primary key, and secret
([PR #42](https://github.com/laravel/passkeys-server/pull/42)); updating the npm package
`@laravel/passkeys` alone does not meet this condition. Verification uses each credential's stored
handle, so binary and hex credentials coexist without rewriting stored credentials. Handles already
corrupted on an authenticator cannot be repaired server-side; affected users replace the passkey
after signing in with another credential.
