import { usePage } from "@inertiajs/react";

import PageExpiredAlert from "@/components/page-expired-alert";
import AuthLayoutTemplate from "@/layouts/auth/auth-simple-layout";
import type { SharedData } from "@/types";

export default function AuthLayout({
  children,
  title,
  description,
  ...props
}: {
  children: React.ReactNode;
  title?: string;
  description?: string;
}) {
  const { appDisplayName, seo } = usePage<SharedData>().props;
  const resolvedTitle = title ?? seo.title ?? appDisplayName;
  const resolvedDescription = description ?? seo.description ?? "";

  return (
    <AuthLayoutTemplate title={resolvedTitle} description={resolvedDescription} {...props}>
      <PageExpiredAlert />
      {children}
    </AuthLayoutTemplate>
  );
}
