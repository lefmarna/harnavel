import { usePage } from "@inertiajs/react";

import SubmissionErrorAlert from "@/components/submission-error-alert";
import AuthLayoutTemplate from "@/layouts/auth/auth-simple-layout";
import type { AuthLayoutProps, SharedData } from "@/types";

export default function AuthLayout({ children, title, description }: AuthLayoutProps) {
  const { appDisplayName, seo } = usePage<SharedData>().props;
  const resolvedTitle = title ?? seo.title ?? appDisplayName;
  const resolvedDescription = description ?? seo.description ?? "";

  return (
    <AuthLayoutTemplate title={resolvedTitle} description={resolvedDescription}>
      <SubmissionErrorAlert />
      {children}
    </AuthLayoutTemplate>
  );
}
