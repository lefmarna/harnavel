import SubmissionErrorAlert from "@/components/submission-error-alert";
import AppSidebarLayout from "@/layouts/app/app-sidebar-layout";
import type { AppLayoutProps } from "@/types";

export default function AppLayout({ children, ...props }: AppLayoutProps) {
  return (
    <AppSidebarLayout {...props}>
      <SubmissionErrorAlert className="mx-4 mt-4 w-auto" />
      {children}
    </AppSidebarLayout>
  );
}
