<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schedule;

Schedule::command('model:prune')->dailyAt('04:00');
Schedule::command('auth:clear-resets')->dailyAt('04:00');
Schedule::command('cache:prune-expired')->dailyAt('04:00');
