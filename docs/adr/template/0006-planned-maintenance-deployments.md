# Planned maintenance deployments

XServer deployment updates the live directory in place. A runnable release enters Laravel's standard
`artisan down --render=errors::503` mode before transfer. An incomplete tree without dependencies
remains retryable; if Artisan cannot boot, repair proceeds only when the existing
maintenance files fully validate. `rsync` preserves those files, and the target release regenerates
and validates them after Composer and before migrations. There is no fixed request-drain delay. The
workflow waits two seconds for the measured XServer OPcache revalidation interval before `artisan up`.

Release verification checks `/up` three times with short bounded requests. Build artifacts and every
file referenced by the Vite manifest are verified before transfer, so production HTTP checks do not
repeat asset validation. A failure after maintenance is enabled and before `up` leaves it active; a
failed `/up` check after `up` reports the deployment failure without automatically re-entering
maintenance. JSON-preferring requests still boot Laravel and may fail while files are being replaced;
this availability gap is accepted. Custom maintenance responders and automatic withdrawal add more
failure states than they remove and are not used.
