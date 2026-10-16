import { FileQuestion } from "lucide-react";

import ErrorPage, { type ErrorPageProps } from "@/components/error-page";

export default function NotFound(props: ErrorPageProps) {
  return <ErrorPage icon={FileQuestion} {...props} />;
}
