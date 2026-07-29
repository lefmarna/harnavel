# External infrastructure state

This file records production facts that are not represented by repository configuration.

## XServer toolchain

- PHP CLI is `/usr/bin/php8.5`.
- Composer is manually uploaded to every deployment environment as
  `~/opt/bin/composer-<exact-version>`.

Upload a new Composer PHAR to Develop and Production before merging matching Dockerfile and
delivery-workflow version changes. The repository can verify version agreement but cannot provision
or inspect the remote file.

## OPcache

All XServer applications currently report web SAPI `cgi-fcgi`, PHP 8.5.5,
`opcache.validate_timestamps=1`, `opcache.revalidate_freq=2`, and no preload. Deployment therefore
waits two seconds before leaving maintenance mode.

Recheck this assumption after changing the hosting provider, PHP version, or server-side `.user.ini`,
or when a deployment serves stale behavior.
