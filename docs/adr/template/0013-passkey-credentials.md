# Passkey credentials

Passkeys sit beside passwords and Google sign-in as an alternative credential, so losing one
provider never locks an account out. Password confirmation guards the security page and passkey
management only where it can succeed: an account holding a password confirms with it, an account
holding a passkey confirms with that, and an account with neither reaches them unguarded — the
alternative strands Google-only users outside the one screen that issues their first credential.
`PASSKEYS_USER_HANDLE_SECRET` stays independent of `APP_KEY`; rotating the application key would
otherwise invalidate every stored passkey.
