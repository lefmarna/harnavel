<?php

declare(strict_types=1);

namespace App\Services\Account;

use App\Mail\EmailChangedMail;
use App\Mail\VerifyNewEmailMail;
use App\Models\User;
use App\Repositories\User\UserRepositoryContract;
use App\Support\SignedPayloadLink;
use Carbon\CarbonInterface;
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

    private const REVERT_LINK_DAYS = 7;

    public function __construct(private readonly UserRepositoryContract $userRepository) {}

    /**
     * 使える取り消しリンクを1つに保つ（TADR-0016）。
     */
    public function canChangeEmail(User $user): bool
    {
        // Laravel の署名付き URL は期限の秒が終わるまで使えるので、同じく秒で比べる。
        return $user->email_changed_at === null
            || now()->getTimestamp() > $this->revertLinkExpiresAt($user)->getTimestamp();
    }

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

                // 申請の後に別の切り替えが確定していれば、7日間の制限もここで守る。
                if ($user === null
                    || ! $this->sameAddress($user->email, $from)
                    || $this->accountState($user) !== $state
                    || ! $this->canChangeEmail($user)) {
                    return null;
                }

                $user->email = $to;
                $user->email_verified_at = now();
                $user->email_changed_at = now();
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
            $this->sendRevertLink($changed, $from);
        }

        return true;
    }

    /**
     * すでに戻っていれば、その後に持ち主が設定し直した資格情報を消さないよう何もしない。
     */
    public function revert(string $userId, string $email): bool
    {
        try {
            $reverted = DB::transaction(function () use ($userId, $email): ?bool {
                $user = $this->userRepository->find($userId, lock: true);

                if ($user === null) {
                    return null;
                }

                if ($this->sameAddress($user->email, $email)) {
                    return false;
                }

                $user->email = $email;
                $user->reclaimForEmailOwner();
                $user->revokeSessions();
                $this->userRepository->save($user);

                return true;
            });
        } catch (UniqueConstraintViolationException) {
            $reverted = null;
        }

        if ($reverted === null) {
            Log::warning('Email change could not be reverted.', ['user_id' => $userId]);

            return false;
        }

        if ($reverted) {
            Log::info('User email change was reverted.', ['user_id' => $userId]);
        }

        return true;
    }

    /**
     * 確認リンクを使えなくする出来事で変わる値。セッションの失効で世代が進み、メールアドレスの切り替え・
     * 取り消し・確認で確認日時が変わるので、A から B へ変えて A に戻した後も、その前の申請は確定できない。
     */
    private function accountState(User $user): string
    {
        return $user->session_generation.'|'.$user->email_verified_at?->getTimestamp();
    }

    /**
     * プロフィールの保存もこの判定に従う。
     */
    public function sameAddress(string $current, string $other): bool
    {
        return Str::lower($current) === Str::lower($other);
    }

    public function revertLink(User $user, string $previousEmail): string
    {
        /** @var int|string $key */
        $key = $user->getKey();

        return SignedPayloadLink::make('email-change.revert', $this->revertLinkExpiresAt($user), $key, [
            'email' => $previousEmail,
        ]);
    }

    private function revertLinkExpiresAt(User $user): CarbonInterface
    {
        return ($user->email_changed_at ?? now())->addDays(self::REVERT_LINK_DAYS);
    }

    /**
     * 切り替えは確定済みのため、送信の失敗は報告だけにする。
     */
    private function sendRevertLink(User $user, string $previousEmail): void
    {
        $url = $this->revertLink($user, $previousEmail);

        try {
            Mail::to($previousEmail)->send(new EmailChangedMail($url));
        } catch (Throwable $e) {
            report($e);
        }
    }
}
