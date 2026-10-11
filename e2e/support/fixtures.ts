import { expect, test as base } from "@playwright/test";

type PageErrorFixtures = {
  pageErrors: void;
};

export const formatPageError = (error: Error): string =>
  error.stack?.trim() || `${error.name}: ${error.message}`;

export const test = base.extend<PageErrorFixtures>({
  pageErrors: [
    async ({ page }, use) => {
      const pageErrors: Error[] = [];
      const collectPageError = (error: Error): void => {
        pageErrors.push(error);
      };

      // CSP に止められたスクリプトは例外にならない。デバッグ時は報告だけのポリシーなので、
      // 強制と報告の両方で起きる securitypolicyviolation を拾う。
      const cspViolations: string[] = [];
      await page.exposeFunction("__recordCspViolation", (violation: string): void => {
        cspViolations.push(violation);
      });
      await page.addInitScript(() => {
        document.addEventListener("securitypolicyviolation", (event) => {
          const record = (
            window as unknown as { __recordCspViolation: (violation: string) => Promise<void> }
          ).__recordCspViolation;
          void record(`${event.effectiveDirective} ${event.blockedURI}`);
        });
      });

      page.on("pageerror", collectPageError);

      await use();

      page.off("pageerror", collectPageError);

      expect(pageErrors.map(formatPageError)).toEqual([]);
      expect(cspViolations).toEqual([]);
    },
    { auto: true },
  ],
});

export { expect };
