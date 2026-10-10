# Account takeover defenses

A stolen session must not outlive the owner's response to it. Changing or resetting a password, and
confirming a Google link on an account whose email was never verified, advance the user's
`session_generation` and rotate `remember_token`. Each login records the generation of the user it
authenticated in the session, and every web request rejects a session whose recorded generation
differs from the user's. The value comes from the user loaded when credentials were checked, so a
login that passed the check just before a reset keeps the old generation and is rejected on its next
request. Deleting the user's rows from the `sessions` table could not do this: such a login writes
its row after the deletion. Sessions from before the column existed count as generation 0, so
introducing it logs no one out. Every revocation advances the generation of the row it has locked,
because advancing a value read earlier would move it back past a concurrent reset and revive the
sessions that reset revoked. Changing the password keeps the current session by recording the new
generation in it. This works with any session driver.

Laravel's `AuthenticateSession` is not used: it skips users without a password, so Google-only and
passkey-only accounts would keep every stolen session, and it adopts the current password hash for a
session that has none, so a login racing a reset survives. Rejected sessions are logged out with
`logoutCurrentDevice()`, which leaves `remember_token` alone; `logout()` would rotate it and sign out
the owner's devices that logged in after the revocation.

An unverified account may have been registered by someone other than the email's owner. When the
owner proves the address through a Google link or a password reset, the account becomes verified
and loses the password, passkeys, Google link, and sessions the registrant may have set up, instead
of merging into them. Passkey registration requires a verified email so the registrant cannot add
one beforehand. Because changing the email address unverifies the account, an owner who resets
before verifying a new address also loses their own passkeys and Google link; the reset cannot tell
that owner from a registrant.

Changing the email address hands over password reset, so the profile page, its update, and account
deletion sit behind password confirmation alongside the security page, at the cost of a prompt when
opening settings. Accounts without a password confirm with a passkey or by signing in again with
the linked Google account. That sign-in returns to the existing OAuth callback URI, so apps do not
register a second redirect URI with Google, and the callback treats an authenticated request as a
reconfirmation only when the session started one. Any confirmation regenerates the session ID, so a
copied session cookie never inherits the owner's confirmation.

Account deletion relies on that confirmation and does not ask for the password again. A second
password prompt would stop owners who confirmed with a passkey or Google from deleting with the
credential they chose, and it would not stop a session thief inside the confirmation window, who
can change the email address and take over the account instead. The dialog that shows what will be
deleted remains, to catch accidental deletion.

Trusted hosts are limited to the `APP_URL` host without its subdomains. Password reset links are
built from the request host, and a subdomain the app does not serve may be unresolved or taken
over; an app serving more hosts through Laravel lists them itself.

URLs carrying a reset token, an email-verification hash, or a signature are kept out of analytics,
advertising scripts, logs, and Discord notifications, because each of those copies is a usable
credential held by a third party.
