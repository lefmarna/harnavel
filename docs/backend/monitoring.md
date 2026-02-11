# Performance Monitoring

This document defines the monitoring and observability strategy.
It ensures proactive detection and resolution of performance issues.

## 1. Metrics & Thresholds

- **Request Duration**: Average response time per route. (Target: < 200ms)
- **Database Query Time**: Slow query logs (MySQL). (Target: < 50ms)
- **Error Rates**: Exception frequency and type. (Target: < 1%)
- **Queue Backlog**: Number of pending jobs. (Target: < 100)
- **Uptime**: Service availability. (Target: 99.9%)

## 2. Tools & Stack

### Local Development

- **Laravel Telescope**: Inspect requests, queries, exceptions, mail, logs.
- **Laravel Debugbar**: Inline profiling (memory, queries, timeline).
- **Xdebug Profiler**: Analyze function calls and bottlenecks.

### Production Monitoring

- **Application Performance Monitoring (APM)**:
  - **New Relic**: Full-stack observability (PHP + Browser).
  - **Sentry**: Error tracking and performance monitoring.
  - **Bugsnag**: Error tracking.
  - **Datadog**: Infrastructure and APM.

### Infrastructure Monitoring

- **Prometheus**: Metrics collection (CPU, Memory, Disk).
- **Grafana**: Dashboard visualization.
- **CloudWatch**: AWS metrics (if applicable).

## 3. Alerting Rules

- **High Error Rate**: > 5% requests failing (5xx).
- **High Latency**: > 1s response time (p95).
- **Queue Backlog**: > 1000 pending jobs.
- **Disk Space**: < 10% free space.

## Verification Checklist

- [ ] **Telescope**: Enabled locally (`APP_ENV=local`).
- [ ] **Sentry**: DSN configured in `.env` (`SENTRY_LARAVEL_DSN`).
- [ ] **Log Level**: Set to `error` in production (`APP_LOG_LEVEL=error`).
- [ ] **Health Check**: Endpoint `/up` returns 200 OK.
- [ ] **Alerts**: Test notification channel (Slack/Email).