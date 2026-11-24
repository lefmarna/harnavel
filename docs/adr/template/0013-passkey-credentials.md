# Passkey credentials

Passkeys sit beside passwords and Google sign-in as an alternative credential so losing one method
does not lock an account out. Only an account with no password, passkey, or Google link reaches the
security page without confirmation, because that page issues its first credential.

Passkey user handles derive from `PASSKEYS_USER_HANDLE_SECRET` rather than `APP_KEY` so rotating
`APP_KEY` preserves handles for new registrations. Because Fortify silently falls back to
`APP_KEY`, production refuses to boot without this secret.

New handles use a 64-byte ASCII hex HMAC because iOS Safari can corrupt raw binary handles
([issue #41](https://github.com/laravel/passkeys-server/issues/41)). Verification matches each
credential's stored handle, so binary and hex credentials coexist.
