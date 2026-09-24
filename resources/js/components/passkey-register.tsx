import { router, usePage } from "@inertiajs/react";
import { usePasskeyRegister } from "@laravel/passkeys/react";
import { useState } from "react";

import InputError from "@/components/input-error";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { passkeyErrorMessage } from "@/lib/passkey-error";
import type { SharedData } from "@/types";

type Props = {
  onSuccess: () => void;
};

const defaultPasskeyName = (): string => {
  const ua = navigator.userAgent;

  const browser = [
    { pattern: /Edg|Edge/, name: "Edge" },
    { pattern: /OPR|Opera|OPiOS/, name: "Opera" },
    { pattern: /Firefox|FxiOS/, name: "Firefox" },
    { pattern: /Chrome|CriOS/, name: "Chrome" },
    { pattern: /Safari/, name: "Safari" },
  ].find(({ pattern }) => pattern.test(ua))?.name;

  const os = [
    { pattern: /iPhone/, name: "iPhone" },
    { pattern: /iPad|Macintosh(?=.*Mobile)/, name: "iPad" },
    { pattern: /Android/, name: "Android" },
    { pattern: /Mac/, name: "Mac" },
    { pattern: /Windows/, name: "Windows" },
  ].find(({ pattern }) => pattern.test(ua))?.name;

  return [os, browser].filter(Boolean).join(" の ");
};

export default function PasskeyRegister(props: Props) {
  const { passkeyAllowedOrigins } = usePage<SharedData>().props;

  if (!passkeyAllowedOrigins.includes(window.location.origin)) {
    return (
      <p className="text-sm text-muted-foreground" data-test="passkey-register-unavailable">
        このURLではパスキーを追加できません。
      </p>
    );
  }

  return <AvailablePasskeyRegister {...props} />;
}

function AvailablePasskeyRegister({ onSuccess }: Props) {
  const [name, setName] = useState(defaultPasskeyName);
  const [showForm, setShowForm] = useState(false);

  const { register, isLoading, errorInstance, isSupported } = usePasskeyRegister({
    onSuccess: () => {
      setName(defaultPasskeyName());
      setShowForm(false);
      onSuccess();
    },
    // 423 を含む汎用エラーでは画面を更新し、期限切れのパスワード確認へ遷移できるようにする。
    onError: (passkeyError) => {
      if (passkeyError.name === "PasskeyError") {
        router.reload();
      }
    },
  });

  const errorMessage = passkeyErrorMessage(errorInstance);

  const handleSubmit = async (event: React.FormEvent) => {
    event.preventDefault();

    if (!name.trim()) {
      return;
    }

    await register(name);
  };

  const handleCancel = () => {
    setShowForm(false);
    setName(defaultPasskeyName());
  };

  if (!isSupported) {
    return (
      <div className="text-sm text-muted-foreground">
        このブラウザはパスキーに対応していません。
      </div>
    );
  }

  if (!showForm) {
    return (
      <Button variant="outline" onClick={() => setShowForm(true)} data-test="add-passkey-button">
        パスキーを追加
      </Button>
    );
  }

  return (
    <form
      onSubmit={handleSubmit}
      className="space-y-4 rounded-lg border border-border bg-muted/50 p-4"
    >
      <div className="grid gap-2">
        <Label htmlFor="passkey-name">パスキーの名前</Label>

        <Input
          id="passkey-name"
          type="text"
          value={name}
          onChange={(event) => setName(event.target.value)}
          placeholder="例: MacBook Pro、iPhone"
          className="mt-1 block w-full border-foreground/20"
          autoFocus
        />

        <p className="text-xs text-muted-foreground">あとで見分けるための名前です。</p>
      </div>

      {errorMessage && <InputError message={errorMessage} />}

      <div className="flex gap-2">
        <Button
          type="submit"
          disabled={isLoading || !name.trim()}
          data-test="register-passkey-button"
        >
          {isLoading ? "登録しています..." : "パスキーを登録"}
        </Button>

        <Button type="button" variant="ghost" onClick={handleCancel}>
          キャンセル
        </Button>
      </div>
    </form>
  );
}
