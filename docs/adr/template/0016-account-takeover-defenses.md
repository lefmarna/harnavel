# Account takeover defenses

The apps hold little beyond an email address. The template therefore stops a stolen session or
cookie from outliving the owner's response, and stops a registrant of someone else's address from
keeping the account, but accepts risks whose fixes would cost every owner usability.

Resetting or changing the password, deleting a passkey, unlinking Google, logging out other devices
(which lets an owner without a password revoke a stolen session), and reclaiming an unverified
account advance the user's session generation and rotate `remember_token`; a session that recorded
an older generation is logged out before `auth` runs. Deleting the user's `sessions` rows was
rejected because a login racing a reset writes its row after the deletion. Laravel's
`AuthenticateSession` was rejected because it skips users without a password and adopts the current
hash for a session that has none. Writes a stolen session could race (password and address changes,
passkey registration, account deletion) lock the user row and refuse with 409 when the generation
moved after the request began. A deleted passkey or unlinked Google account may already have signed
a thief in, so removing one revokes the other sessions; the acting session survives with a new ID
and, when it had one, a new remember cookie. Unlinking Google requires a password, so every account
keeps a password or a Google link. Revocation covers sessions only: an app that issues API tokens
revokes them on the same events. Signing out ends only the current device and keeps
`remember_token`: every sign-in is remembered, and rotating it would end other devices' remembered
sign-ins without ending their open sessions. A remember cookie stolen from the signing-out device
stays usable until the owner logs out other devices.

An unverified account may have been registered by someone other than the email's owner. When the
owner proves the address through a Google link or a password reset, the account becomes verified and
loses the registrant's password, passkeys, Google link, and sessions instead of merging into them.
Passkey registration requires a verified email so the registrant cannot add one beforehand.

Changing the email address hands over password reset, so the profile page, its update, account
deletion, and the security page require password confirmation; accounts without a password confirm
with a passkey or by signing in again with Google through the existing callback URI. Laravel's
three-hour window stays: a shorter one only narrows the time a cookie stolen after confirmation can
act, at the cost of a prompt on most visits to settings. Account deletion asks nothing more, because
a second prompt would stop owners who confirmed with a passkey or Google and would not stop a thief
inside the window, who can take over by changing the address instead.

Signing in again with Google proves only that the browser holds a Google session for the linked
account; Google offers no forced reauthentication. That stops a thief who copied only the app's
cookie, but not one who also holds the Google session, who could sign in directly anyway. Google's
`auth_time` claim was rejected: Google returns it only to verified apps, only in the ID token that
Socialite does not verify, and a stale value could only be refused. Google confirmation is therefore
offered only to accounts without a password, and against an attacker holding the Google session the
template relies on Google's protection, the change notices below, and session revocation.

A new email address takes effect only after a link sent to it is opened; the previous address is
told, without a link to act on. There is no self-service restore: after someone else changes the
address, the owner can change it back only from a session still signed in. An app holding more than
an email address should add its own recovery. Links sent for an address change or a Google link
record the session generation, and the address-change link also the verification time, so a change
a thief requested cannot complete after the owner resets the password or switches the address back.

Changing the password, adding a passkey, and linking Google mail the account's address after the
change commits; the mail carries no signed action, and a delivery failure does not undo the change.
Password reset is not announced, because it starts from the mailbox that would receive the notice.

Registration and address changes report an address already in use. Hiding it would need
registration to continue from a mailed link, turning the form into a way to mail any address; the
answer reveals only that the owner uses the app, sign-in and password reset respond the same either
way, and registration is limited per IP address.

Inertia encrypts the page data it keeps in browser history and clears it after logout, account
deletion, and a revoked session's rejection, so the back button on a shared device does not redraw
the previous user's pages. A revoked session first rejected on a JSON request can still redraw pages
it already showed until its next page load; that data was already disclosed to it, so handling 401
in every fetch was rejected.

URLs carrying a reset token, an email-verification hash, or a signature are kept out of analytics,
advertising scripts, logs, and Discord notifications, because each copy is a usable credential held
by a third party.
