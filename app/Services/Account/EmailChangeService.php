<?php

declare(strict_types=1);

namespace App\Services\Account;

use App\Mail\EmailChangedMail;
use App\Mail\VerifyNewEmailMail;
use App\Models\User;
use App\Repositories\User\UserRepositoryContract;
use App\Support\SignedPayloadLink;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

/**
 * 設計の理由は TADR-0016。
 */
class EmailChangeService
{
    private const VERIFY_LINK_MINUTES = 60;

    public function __construct(private readonly UserRepositoryContract $userRepository) {}

    public function request(User $user, string $email): void
    {
        /** @var int|string $key */
        $key = $user->getKey();

        $url = SignedPayloadLink::make('email-change.verify', now()->addMinutes(self::VERIFY_LINK_MINUTES), $key, [
            'from' => $user->email,
            'to' => $email,
            'state' => $this->accountState($user),
        ]);

        Mail::to($email)->send(new VerifyNewEmailMail($url));

        Log::info('User email change was requested.', ['user_id' => $key]);
    }

    public function confirm(string $userId, string $from, string $to, string $state): bool
    {
        try {
            // 切り替えたユーザー、すでに切り替わっていれば false、前提が崩れていれば null。
            $changed = DB::transaction(function () use ($userId, $from, $to, $state): User|false|null {
                $user = $this->userRepository->find($userId, lock: true);

                // 同じリンクをもう一度開いたときは、通知を重ねずに成功とする。
                if ($user !== null && $this->sameAddress($user->email, $to)) {
                    return false;
                }

                if ($user === null
                    || ! $this->sameAddress($user->email, $from)
                    || $this->accountState($user) !== $state) {
                    return null;
                }

                $user->email = $to;
                $user->email_verified_at = $this->nextVerificationTime($user);
                $this->userRepository->save($user);

                return $user;
            });
        } catch (UniqueConstraintViolationException) {
            $changed = null;
        }

        if ($changed === null) {
            Log::warning('Email change could not be confirmed.', ['user_id' => $userId]);

            return false;
        }

        if ($changed instanceof User) {
            Log::info('User email was changed.', ['user_id' => $userId]);
            $this->notifyPreviousAddress($from);
        }

        return true;
    }

    /**
     * 確認リンクを使えなくする出来事で変わる値。セッションの失効で世代が進み、メールアドレスの切り替えと
     * 確認で確認日時が変わるので、A から B へ変えて A に戻した後も、その前の申請は確定できない。
     */
    private function accountState(User $user): string
    {
        return $user->session_generation.'|'.$user->email_verified_at?->getTimestamp();
    }

    private function nextVerificationTime(User $user): CarbonImmutable
    {
        $now = now()->toImmutable()->startOfSecond();
        $previous = $user->email_verified_at;

        return $previous !== null && $previous->greaterThanOrEqualTo($now) ? $previous->addSecond() : $now;
    }

    public function sameAddress(string $current, string $other): bool
    {
        return Str::lower($current) === Str::lower($other);
    }

    /**
     * 切り替えは確定済みのため、送信の失敗は報告だけにする。
     */
    private function notifyPreviousAddress(string $previousEmail): void
    {
        try {
            Mail::to($previousEmail)->send(new EmailChangedMail);
        } catch (Throwable $e) {
            report($e);
        }
    }
}
