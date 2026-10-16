import { Link } from "@inertiajs/react";
import type { LucideIcon } from "lucide-react";

import { Button } from "@/components/ui/button";
import { home } from "@/routes";

export type ErrorPageProps = {
  seo: { title: string; description: string };
};

export default function ErrorPage({
  icon: Icon,
  seo,
}: ErrorPageProps & {
  icon: LucideIcon;
}) {
  return (
    <div className="flex min-h-svh flex-col items-center justify-center gap-6 bg-background p-6 text-foreground md:p-10">
      <div className="w-full max-w-md space-y-6 text-center">
        <div className="mx-auto flex size-14 items-center justify-center rounded-full bg-muted text-muted-foreground">
          <Icon className="size-7" />
        </div>

        <div className="space-y-2">
          <h1 className="text-xl font-medium">{seo.title}</h1>
          <p className="text-sm leading-6 text-muted-foreground">{seo.description}</p>
        </div>

        <Button asChild className="w-full">
          <Link href={home()}>トップページへ戻る</Link>
        </Button>
      </div>
    </div>
  );
}
