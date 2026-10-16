import { ShieldX } from "lucide-react";

import ErrorPage, { type ErrorPageProps } from "@/components/error-page";

export default function Forbidden(props: ErrorPageProps) {
  return <ErrorPage icon={ShieldX} {...props} />;
}
