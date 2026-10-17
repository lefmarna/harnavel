import PageExpiredAlert from "@/components/page-expired-alert";
import AppLayoutTemplate from "@/layouts/app/app-sidebar-layout";
import type { AppLayoutProps } from "@/types";

export default ({ children, ...props }: AppLayoutProps) => (
  <AppLayoutTemplate {...props}>
    <PageExpiredAlert className="mx-4 mt-4 w-auto" />
    {children}
  </AppLayoutTemplate>
);
