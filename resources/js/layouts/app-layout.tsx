import SubmissionErrorAlert from "@/components/submission-error-alert";
import AppLayoutTemplate from "@/layouts/app/app-sidebar-layout";
import type { AppLayoutProps } from "@/types";

export default ({ children, ...props }: AppLayoutProps) => (
  <AppLayoutTemplate {...props}>
    <SubmissionErrorAlert className="mx-4 mt-4 w-auto" />
    {children}
  </AppLayoutTemplate>
);
