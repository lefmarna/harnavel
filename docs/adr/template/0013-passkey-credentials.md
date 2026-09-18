# Passkey credentials

Passkeys sit beside passwords and Google sign-in as an alternative credential, so losing one
provider never locks an account out. Password confirmation therefore guards the security page only
where it can succeed: an account holding neither a password nor a passkey reaches it unguarded,
because the alternative strands Google-only users outside the one screen that issues their first
credential.

Passkey user handles derive from `PASSKEYS_USER_HANDLE_SECRET`, which must stay independent of
`APP_KEY`: rotating the application key would otherwise invalidate every stored passkey. Fortify
falls back to `APP_KEY` silently when the secret is missing, so production refuses to boot unless
the secret is configured explicitly, while local and CI environments accept the fallback for
convenience. An app that already holds passkeys registered under the fallback pins the secret to
its current `APP_KEY` value, which keeps those passkeys valid and decouples them from later key
rotation.
