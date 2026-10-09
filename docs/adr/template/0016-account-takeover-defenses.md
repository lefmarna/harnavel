# Account takeover defenses

A stolen session must not outlive the owner's response to it. Changing or resetting a password, and
confirming a Google link on an account whose email was never verified, delete the user's other rows
from the `sessions` table and rotate `remember_token`. Laravel's `AuthenticateSession` is not used:
it skips users without a password, so Google-only and passkey-only accounts would keep every stolen
session. Deleting rows only works with the database session driver; an app that changes the driver
must replace this revocation.

An unverified account may have been registered by someone other than the email's owner. When the
owner proves the address through a Google link, the link clears the account's password, passkeys,
and sessions instead of merging into whatever the registrant set up.

Changing the email address hands over password reset, so the profile page, its update, and account
deletion sit behind password confirmation alongside the security page, at the cost of a prompt when
opening settings. Accounts without a password confirm with a passkey or by signing in again with
the linked Google account. That sign-in returns to the existing OAuth callback URI, so apps do not
register a second redirect URI with Google, and the callback treats an authenticated request as a
reconfirmation only when the session started one. Any confirmation regenerates the session ID, so a
copied session cookie never inherits the owner's confirmation.

Trusted hosts are limited to the `APP_URL` host without its subdomains. Password reset links are
built from the request host, and a subdomain the app does not serve may be unresolved or taken
over; an app serving more hosts through Laravel lists them itself.

URLs carrying a reset token, an email-verification hash, or a signature are kept out of analytics,
advertising scripts, logs, and Discord notifications, because each of those copies is a usable
credential held by a third party.
