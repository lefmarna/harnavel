import { usePage } from "@inertiajs/react";
import { Passkeys } from "@laravel/passkeys";

import type { SharedData } from "@/types";

export const usePasskeyAvailable = (): boolean => {
  const { passkeyAllowedOrigins } = usePage<SharedData>().props;

  return passkeyAllowedOrigins.includes(window.location.origin) && Passkeys.isSupported();
};
