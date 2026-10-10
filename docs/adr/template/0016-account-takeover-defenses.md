# Account takeover defenses

A stolen session must not outlive the owner's response to it. Changing or resetting a password, and
confirming a Google link on an account whose email was never verified, advance the user's
`session_generation` and rotate `remember_token`. Each login records the generation of the user it
authenticated in the session, and every web request logs out a session whose recorded generation
differs from the user's before `auth` runs, then continues as a guest: pages that need a login send
it to the login page, and links that need none, such as restoring an email address, still open. The value comes from the user loaded when credentials were checked, so a
login that passed the check just before a reset keeps the old generation and is rejected on its next
request. Deleting the user's rows from the `sessions` table could not do this: such a login writes
its row after the deletion. Sessions from before the column existed count as generation 0, so
introducing it logs no one out. Every revocation advances the generation of the row it has locked,
because advancing a value read earlier would move it back past a concurrent reset and revive the
sessions that reset revoked. A request that began before a revocation can still be running when it
commits, so writes to the password or the address lock the user row, compare its generation with
the one the request started with, and refuse with 409 when it moved; otherwise an in-flight change
from a stolen session would land after the owner's reset or restore. Passkey registration does the
same: the package's store action is replaced with one that takes the lock and compares the
generation before inserting, because a passkey removed only after it is stored could already be used
to sign in by another request. Changing the password keeps the current session by
recording the new generation in it. This works with any session driver.

Laravel's `AuthenticateSession` is not used: it skips users without a password, so Google-only and
passkey-only accounts would keep every stolen session, and it adopts the current password hash for a
session that has none, so a login racing a reset survives. Rejected sessions are logged out with
`logoutCurrentDevice()`, which leaves `remember_token` alone; `logout()` would rotate it and sign out
the owner's devices that logged in after the revocation.

An unverified account may have been registered by someone other than the email's owner. When the
owner proves the address through a Google link or a password reset, the account becomes verified
and loses the password, passkeys, Google link, and sessions the registrant may have set up, instead
of merging into them. Passkey registration requires a verified email so the registrant cannot add
one beforehand.

Changing the email address hands over password reset, so the profile page, its update, and account
deletion sit behind password confirmation alongside the security page, at the cost of a prompt when
opening settings. Accounts without a password confirm with a passkey or by signing in again with
the linked Google account. That sign-in returns to the existing OAuth callback URI, so apps do not
register a second redirect URI with Google, and the callback treats an authenticated request as a
reconfirmation only when the session started one. Any confirmation regenerates the session ID, so a
copied session cookie never inherits the owner's confirmation.

A new email address takes effect only after a link sent to it is opened, so a session thief cannot
point password reset at an address they do not control without the owner's old address hearing of
it. The switch marks the account verified and mails the previous address a link, valid for seven
days, that restores it. Restoring treats the holder of the previous address as the owner reclaiming
the account: it verifies that address and removes the password, passkeys, Google link, and sessions
whoever made the change may have set up, then sends the owner to password reset. It does nothing
when the address is already restored, so a second click cannot erase credentials the owner set
afterwards. Seven days give the owner time to notice; for that long, someone who controls the
previous mailbox can also take the account back. Restoring fails when another account has taken the
previous address in the meantime.

After a switch, the address cannot be changed again until the restore link has expired, so at most
one restore link is usable at a time. The limit is checked when a change is requested and again,
on the locked row, when it is confirmed, so a switch that commits in between cannot slip past it. Without that limit, a thief who routed the address through a
mailbox they control, A to B to C, would also hold a link back to B: using it first would leave the
owner's link to A pointing at an account the thief controls again, and invalidating it on restore
would let the thief cancel the owner's link. The owner can still change the name, and can restore
with the link at any time; the cost is that an owner who changes the address twice within a week
must wait.

A link to the new address records the session generation and the verification time, and stops
working once either has moved since the request: revoking sessions, switching, restoring, or
verifying the address all move one of them. A change a thief requested therefore cannot be completed
after the owner resets the password or restores the address. Both links are confirmed with a POST so
mail scanners that prefetch links do not act on them, and only the signed query is read, so a POST
body cannot substitute the payload of another link. The mail to the previous address is sent after
the switch commits; a delivery failure is reported but does not undo the switch.

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
