import { router } from "@inertiajs/react";
import type { LucideIcon } from "lucide-react";
import type { ReactNode } from "react";

import { Button } from "@/components/ui/button";
import AuthLayout from "@/layouts/auth-layout";
import { cn } from "@/lib/utils";

export type EmailLinkPageProps = {
  status: "confirm" | "success" | "error";
  actionUrl?: string;
  email?: string;
};

const iconStyles = {
  confirm: "bg-primary/10 text-primary",
  success: "bg-green-100 text-green-600 dark:bg-green-950 dark:text-green-400",
  error: "bg-destructive/10 text-destructive",
} as const;

type Props = {
  status: EmailLinkPageProps["status"];
  icon: LucideIcon;
  message: ReactNode;
  children: ReactNode;
};

export default function EmailLinkStatus({ status, icon: Icon, message, children }: Props) {
  return (
    <AuthLayout>
      <div className="space-y-6 text-center">
        <div
          className={cn(
            "mx-auto flex size-14 items-center justify-center rounded-full",
            iconStyles[status],
          )}
        >
          <Icon className="size-7" />
        </div>

        <p className="text-sm leading-6 text-muted-foreground">{message}</p>

        {children}
      </div>
    </AuthLayout>
  );
}

export function EmailLinkConfirmButton({
  actionUrl,
  children,
}: {
  actionUrl?: string;
  children: ReactNode;
}) {
  return (
    <Button
      type="button"
      className="w-full"
      onClick={() => {
        if (actionUrl) {
          router.post(actionUrl);
        }
      }}
    >
      {children}
    </Button>
  );
}
