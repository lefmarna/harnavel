<?php

namespace App\Services\Observability;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use PDOException;
use Stringable;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

class ProductionExceptionNotifier
{
    public function report(Throwable $exception, ?Request $request = null): void
    {
        try {
            if (! $this->shouldNotify($exception)) {
                return;
            }

            if (! $this->reserveNotificationSlot($exception, $request)) {
                return;
            }

            $payload = $this->payload($exception, $request);

            app()->terminating(fn () => $this->send($payload));
        } catch (Throwable $notifierException) {
            // 通知経路の失敗が元例外のログ記録やレスポンス描画を妨げてはならない
            // （report コールバック内の例外は Laravel の通常ログ出力より前に伝播する）
            try {
                Log::warning('Discord exception notification was skipped.', [
                    'exception' => $notifierException,
                ]);
            } catch (Throwable) {
                // ログ基盤まで壊れている場合は握りつぶす
            }
        }
    }

    private function shouldNotify(Throwable $exception): bool
    {
        if (! app()->environment(['production', 'develop'])) {
            return false;
        }

        if (! $this->webhookUrl()) {
            return false;
        }

        return $this->statusCode($exception) >= 500;
    }

    private function reserveNotificationSlot(Throwable $exception, ?Request $request): bool
    {
        $seconds = $this->deduplicationSeconds();

        if ($seconds < 1) {
            return true;
        }

        try {
            return Cache::add($this->deduplicationKey($exception, $request), true, $seconds);
        } catch (Throwable) {
            return true;
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function send(array $payload): void
    {
        $webhookUrl = $this->webhookUrl();

        if (! $webhookUrl) {
            return;
        }

        try {
            Http::timeout($this->discordTimeout())
                ->connectTimeout($this->discordConnectTimeout())
                ->post($webhookUrl, $payload)
                ->throw();
        } catch (Throwable $exception) {
            Log::warning('Discord exception notification failed.', [
                'exception' => $exception,
            ]);
        }
    }

    /**
     * @return array{content: string, embeds: list<array<string, mixed>>}
     */
    private function payload(Throwable $exception, ?Request $request): array
    {
        $requestId = $this->requestId($request);
        $statusCode = $this->statusCode($exception);

        $title = ucfirst(app()->environment()).' exception';

        return [
            'content' => sprintf('[%s][%s] %s: %s', $this->appName(), $requestId, $title, $exception::class),
            'embeds' => [
                [
                    'title' => $title,
                    'color' => 15_141_160,
                    'fields' => $this->fields([
                        'app' => $this->appName(),
                        'request_id' => $requestId,
                        'environment' => app()->environment(),
                        'status' => (string) $statusCode,
                        'exception' => $exception::class,
                        'message' => $this->exceptionMessage($exception),
                        'method' => $request?->method(),
                        'url' => $request?->url(),
                        'route' => $request?->route()?->getName(),
                        'ip' => $request?->ip(),
                        'user_agent' => $this->userAgent($request),
                        'user_id' => $this->userId(),
                        'file' => $exception->getFile().':'.$exception->getLine(),
                    ]),
                ],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $values
     * @return list<array{name: string, value: string, inline: bool}>
     */
    private function fields(array $values): array
    {
        $fields = [];

        foreach ($values as $name => $value) {
            $stringValue = $this->stringFieldValue($value);

            if ($stringValue === null || $stringValue === '') {
                continue;
            }

            $fields[] = [
                'name' => $name,
                'value' => Str::limit($stringValue, 1024),
                'inline' => true,
            ];
        }

        return $fields;
    }

    private function deduplicationKey(Throwable $exception, ?Request $request): string
    {
        $fingerprint = implode('|', [
            app()->environment(),
            $exception::class,
            hash('sha256', $this->exceptionMessage($exception)),
            $request?->path() ?? '',
            $request?->route()?->getName() ?? '',
        ]);

        return 'observability:discord-exception:'.hash('sha256', $fingerprint);
    }

    private function statusCode(Throwable $exception): int
    {
        return $exception instanceof HttpExceptionInterface
            ? $exception->getStatusCode()
            : 500;
    }

    private function requestId(?Request $request): string
    {
        $requestId = $request?->attributes->get('request_id');

        return is_string($requestId) && $requestId !== '' ? $requestId : 'unavailable';
    }

    private function webhookUrl(): ?string
    {
        $webhookUrl = config('observability.discord.webhook_url');

        return is_string($webhookUrl) && $webhookUrl !== '' ? $webhookUrl : null;
    }

    private function deduplicationSeconds(): int
    {
        $seconds = config('observability.discord.deduplication_seconds', 300);

        return is_numeric($seconds) ? (int) $seconds : 300;
    }

    private function discordTimeout(): float
    {
        $timeout = config('observability.discord.timeout', 2.0);

        return is_numeric($timeout) ? (float) $timeout : 2.0;
    }

    private function discordConnectTimeout(): float
    {
        $timeout = config('observability.discord.connect_timeout', 1.0);

        return is_numeric($timeout) ? (float) $timeout : 1.0;
    }

    private function userAgent(?Request $request): ?string
    {
        $userAgent = $request?->userAgent();

        return is_string($userAgent) ? Str::limit($userAgent, 200) : null;
    }

    private function exceptionMessage(Throwable $exception): string
    {
        if ($exception instanceof PDOException) {
            return 'Database exception message omitted.';
        }

        return Str::limit($exception->getMessage(), 500);
    }

    private function userId(): int|string|null
    {
        if (! Auth::hasUser()) {
            return null;
        }

        $userId = Auth::user()?->getAuthIdentifier();

        return is_int($userId) || is_string($userId) ? $userId : null;
    }

    private function stringFieldValue(mixed $value): ?string
    {
        if (is_string($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value) || is_bool($value)) {
            return (string) $value;
        }

        if ($value instanceof Stringable) {
            return (string) $value;
        }

        return null;
    }

    private function appName(): string
    {
        $appName = config('app.name');

        return is_string($appName) && $appName !== '' ? $appName : 'Laravel';
    }
}
