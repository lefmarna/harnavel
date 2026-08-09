# In-place XServer deployments

XServer releases update the live directory in place, so a runnable release enters Laravel's
standard maintenance mode before transfer; the maintenance state remains repairable without
booting an incomplete target. A failure before `artisan up` leaves maintenance active, while a
failed health check after `up` reports failure without re-entering it. The resulting gap for
JSON-preferring requests is accepted, and custom responders or automatic maintenance withdrawal
are rejected because they add more failure states than they remove.
