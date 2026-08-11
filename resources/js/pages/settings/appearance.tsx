import AppearanceTabs from "@/components/appearance-tabs";
import Heading from "@/components/heading";
import AppLayout from "@/layouts/app-layout";
import SettingsLayout from "@/layouts/settings/layout";
import { edit as editAppearance } from "@/routes/appearance";
import type { BreadcrumbItem } from "@/types";

const breadcrumbs: BreadcrumbItem[] = [
  {
    title: "表示設定",
    href: editAppearance().url,
  },
];

export default function Appearance() {
  return (
    <AppLayout breadcrumbs={breadcrumbs}>
      <h1 className="sr-only">表示設定</h1>

      <SettingsLayout>
        <div className="space-y-6">
          <Heading
            variant="small"
            title="表示設定"
            description="アカウントの表示設定を変更します"
          />
          <AppearanceTabs />
        </div>
      </SettingsLayout>
    </AppLayout>
  );
}
