import { Hourglass } from "lucide-react";

import ErrorPage, { type ErrorPageProps } from "@/components/error-page";

export default function TooManyRequests(props: ErrorPageProps) {
  return <ErrorPage icon={Hourglass} {...props} />;
}
