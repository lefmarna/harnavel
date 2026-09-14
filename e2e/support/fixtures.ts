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

      page.on("pageerror", collectPageError);

      await use();

      page.off("pageerror", collectPageError);

      expect(pageErrors.map(formatPageError)).toEqual([]);
    },
    { auto: true },
  ],
});

export { expect };
