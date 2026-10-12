import type { BrowserContext, Page } from "@playwright/test";

/** CDP 依存のため Chromium 専用。 */
export const enableVirtualAuthenticator = async (
  page: Page,
  context: BrowserContext,
): Promise<void> => {
  const client = await context.newCDPSession(page);

  await client.send("WebAuthn.enable");
  await client.send("WebAuthn.addVirtualAuthenticator", {
    options: {
      protocol: "ctap2",
      transport: "internal",
      hasResidentKey: true,
      hasUserVerification: true,
      isUserVerified: true,
      automaticPresenceSimulation: true,
    },
  });
};
