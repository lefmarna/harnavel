# Account takeover defenses

A stolen session must not outlive the owner's response to it. Resetting or changing the password,
and confirming a Google link on an unverified account, advance the user's session generation and
rotate `remember_token`; every web request logs out a session that recorded an older generation
before `auth` runs. Deleting the user's `sessions` rows was rejected because a login racing a reset
writes its row after the deletion. Laravel's `AuthenticateSession` was rejected because it skips
users without a password and adopts the current hash for a session that has none. Rejected sessions
use `logoutCurrentDevice()`, so the owner's devices that signed in after the revocation stay signed in.

Writes a stolen session could race (password and address changes, passkey registration, account
deletion) lock the user row and refuse with 409 when the generation moved after the request began,
so an in-flight change cannot land after the owner's reset. Deleting a passkey or
unlinking Google also revokes the other sessions, because a thief may already have signed in with
it. Unlinking Google requires a password, so every account keeps a password or a Google link and a
passkey only adds a way to sign in. The security page also logs out every other device, so an
owner who never set a password can still revoke a stolen session or remember cookie. The acting
session survives by regenerating its ID, and gets a new remember cookie when it signed in with one.
Laravel offers no public way to reissue that cookie, so the template calls the guard's own method.
Revocation covers sessions only: an app that issues API tokens revokes them on the same events.

An unverified account may have been registered by someone other than the email's owner. When the
owner proves the address through a Google link or a password reset, the account becomes verified
and loses the registrant's password, passkeys, Google link, and sessions instead of merging into
them. Passkey registration requires a verified email so the registrant cannot add one beforehand.

Changing the email address hands over password reset, so the profile page, its update, account
deletion, and the security page require password confirmation. Accounts without a password confirm
with a passkey or by signing in again with Google through the existing OAuth callback URI.
Confirmation regenerates the session ID and keeps Laravel's three-hour window. A shorter window
only narrows the time a cookie stolen after confirmation can change the address or add a passkey,
which does not justify asking for the password on most visits to settings in apps that hold little
beyond an email address.

Signing in again with Google proves only that the browser holds a Google session for the linked
account; Google offers no forced reauthentication. That stops a thief who copied only the app's
session cookie, which is what confirmation guards against, but not one who also holds the Google
session, who could sign in directly anyway and, when the address is that mailbox, also receive
password reset. The template does not request Google's `auth_time` claim: Google returns it only to
verified apps, only in the ID token that Socialite does not verify, and a stale value could only be
refused. Google confirmation is offered only to accounts without a password, checked both when it
starts and when Google returns. Against an attacker holding the Google session, the template relies
on Google's own protection, the mail sent when a credential or the address changes, and the owner's
ability to revoke sessions.

A new email address takes effect only after a link sent to it is opened. The switch verifies the
account and tells the previous address, without a link to act on. There is no self-service restore:
after someone else changes the address, the owner can change it back only from a session that is
still signed in, and may otherwise be unable to recover the account. The apps hold little beyond an
email address, so that risk is accepted instead of a restore link and the limit on further changes
it needed; an app holding more should add its own recovery.

The link sent to a new address, and the mail that confirms a Google link, record the session
generation, and the new-address link also the verification time; they stop working once either has
moved, so a change a thief requested cannot complete after the owner resets the password or switches
the address back. Links are confirmed with a POST so mail scanners that prefetch links do not act on
them.

Changing the password, adding a passkey, and linking Google mail the account's address after the
change commits, so the owner learns of a credential a thief added. The mail carries no signed
action, and a delivery failure does not undo the change. Password reset is not announced, because it
starts from the mailbox that would receive the notice.

Account deletion relies on password confirmation and does not ask for the password again. A second
prompt would stop owners who confirmed with a passkey or Google, and would not stop a thief inside
the confirmation window, who can take over by changing the address instead. The dialog that shows
what will be deleted remains, to catch accidents.

Registration and address changes report an address already in use, so anyone can learn whether an
address has an account. Hiding it would need registration to continue from a mailed link, turning
the form into a way to mail any address; the answer reveals only that the owner uses the app,
sign-in and password reset respond the same either way, and registration is limited per IP address.

Trusted hosts are the `APP_URL` host without its subdomains, because password reset links are built
from the request host and a subdomain the app does not serve may be taken over. An app serving more
hosts lists them itself.

Inertia encrypts the page data it keeps in browser history and clears that history after logout,
account deletion, and a revoked session's rejection, so the back button on a shared device does not
redraw the previous user's pages without asking the server. The clear reaches the browser with the next
page it receives. When a revoked session is first rejected on a JSON request, the back button can
still redraw pages that same session already showed until then; that data was already disclosed to
it, so this is accepted instead of handling 401 in every fetch.

URLs carrying a reset token, an email-verification hash, or a signature are kept out of analytics,
advertising scripts, logs, and Discord notifications, because each copy is a usable credential held
by a third party.
