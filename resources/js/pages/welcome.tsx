import { Link, usePage } from "@inertiajs/react";

import AppLogoIcon from "@/components/app-logo-icon";
import { Button } from "@/components/ui/button";
import { dashboard, login, register } from "@/routes";
import type { SharedData } from "@/types";

export default function Welcome({ canRegister = true }: { canRegister?: boolean }) {
  const { auth, appDisplayName, appDescription } = usePage<SharedData>().props;

  return (
    <div className="flex min-h-svh flex-col items-center justify-center gap-6 bg-background p-6 text-center">
      <AppLogoIcon className="size-12 fill-current text-foreground" />
      <h1 className="text-2xl font-semibold">{appDisplayName}</h1>
      <p className="max-w-md text-sm text-muted-foreground">{appDescription}</p>

      <nav className="flex gap-3">
        {auth.user ? (
          <Button asChild>
            <Link href={dashboard()}>ダッシュボード</Link>
          </Button>
        ) : (
          <>
            <Button asChild variant="outline">
              <Link href={login()}>ログイン</Link>
            </Button>
            {canRegister && (
              <Button asChild>
                <Link href={register()}>新規登録</Link>
              </Button>
            )}
          </>
        )}
      </nav>
    </div>
  );
}
