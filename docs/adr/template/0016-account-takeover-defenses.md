# Account takeover defenses

The apps hold little beyond an email address, so the template stops a stolen session or cookie from
outliving the owner's response and stops a squatter of an unverified address from keeping the
account, while accepting residual risks whose mitigations would hurt everyday usability.

Credential changes, revoking another login method, logging out other devices, and reclaiming an
unverified account advance the user's session generation and rotate `remember_token`; stale
sessions are rejected before `auth` runs. Deleting `sessions` rows was rejected because a racing
login writes afterwards, and Laravel's `AuthenticateSession` because it skips passwordless users
and adopts the current hash when absent. Sensitive writes lock the user row and return 409 if the
generation moved after the request began. Removing a passkey or Google link revokes other sessions
in case it was already used by a thief; unlinking Google requires a password so an account always
retains a password or Google link. API token revocation stays app-owned.

Every sign-in is remembered, and signing out keeps `remember_token` so other devices stay signed
in; a remember cookie stolen from the signed-out device therefore remains valid until other devices
are logged out.

Proving ownership of an unverified address via Google link or password reset verifies the account
and wipes the registrant's password, passkeys, Google link, and sessions instead of merging.
Passkey registration requires a verified email so a squatter cannot plant one beforehand.

Email changes control password reset, so settings and account deletion require confirmation within
Laravel's three-hour window (a shorter window hurts usability more than it narrows post-confirm
cookie theft, and deletion adds no extra prompt because an attacker inside the window could change
the email instead). Re-authenticating with Google is limited to passwordless accounts because
Google offers no forced reauthentication; `auth_time` was rejected as unverified by Socialite and
limited to verified apps.

New addresses activate only via a signed link sent to the new address, with a passive notice to the
old address and no self-service restore; apps with richer data add their own recovery. Security
action emails carry no signed links, send after commit, and ignore delivery failure; password reset
is not announced because it originates from the same mailbox. Signed links bind the session
generation (and verification time for email changes) so revocation invalidates pending links.

Registration and email changes report an already-used address rather than sending continuation
links to arbitrary mailboxes; sign-in and password reset stay uniform and registration is
IP-throttled. Inertia encrypts browser history and clears it on logout, deletion, and session
revocation; a session first rejected on a JSON request may still redraw already-seen pages until
the next navigation, which is accepted over intercepting every 401 fetch. URLs carrying tokens or
signatures stay out of analytics, ads, logs, and Discord alerts.
