import { test as base, expect, type Locator, type Page, type Request } from '@playwright/test';

// Shared fixtures and helpers for the filterablearchive specs.
//
// Every spec first asks the fixture reset endpoint (fixtures/FaBResetAdmin.php) for a freshly
// seeded, published archive of its own: a holder with the date (month), category and tag filters
// on, and five items, newest first:
//   Jun 2024 events (Events; Blue, Green)   May 2024 plain (-)   May 2024 press (Press; Blue, Green)
//   Jan 2023 events (Events; Blue)    Dec 2022 press (Press)
// The holder page is the README's Templates section: FilterableArchiveFilter, the paginated items
// with FilterableProperties (LinkFilterProps=1), and both pagination templates
// (fixtures/FaBHolderController.php).

export const ALL = ['Jun 2024 events', 'May 2024 plain', 'May 2024 press', 'Jan 2023 events', 'Dec 2022 press'];

/**
 * test, extended with an automatic guard: every spec fails if the page logs a console error (a
 * failed request included), throws an uncaught exception, or opens a dialog. Warnings do not count.
 */
export const test = base.extend<{ guard: void }>({
    guard: [
        async ({ page }, use, testInfo) => {
            const errors: string[] = [];
            page.on('console', (msg) => {
                if (msg.type() === 'error') {
                    errors.push(`console.error: ${msg.text()} (${msg.location().url})`);
                }
            });
            page.on('pageerror', (err) => {
                if (isKnownSs5EntwineError(err)) {
                    return;
                }
                errors.push(`uncaught: ${err.message} | ${(err.stack ?? '').split('\n').slice(1, 3).join(' | ').trim()}`);
            });
            page.on('dialog', async (dialog) => {
                if (dialog.type() !== 'beforeunload') {
                    errors.push(`unexpected ${dialog.type()}(): ${dialog.message()}`);
                }
                await (dialog.type() === 'beforeunload' ? dialog.accept() : dialog.dismiss());
            });

            await use();

            if (errors.length) {
                await testInfo.attach('console-errors', { body: errors.join('\n'), contentType: 'text/plain' });
            }
            expect(errors, 'no console errors, uncaught exceptions or dialogs').toEqual([]);
        },
        { auto: true },
    ],
});

export { expect };

/**
 * The one uncaught error that is not counted, and only where it comes from: the Silverstripe 5
 * admin's bundled entwine (silverstripe/admin 2.x, client/dist/js/vendor.js). Its MutationObserver
 * hands every added node except "#text" to the onadd rules, so an added comment node reaches the
 * selector matcher, which calls getAttribute() on it: "el.getAttribute is not a function". It fires
 * on CMS pages that load a script with an entwine onadd rule, as symbiote/gridfieldextensions'
 * GridFieldExtensions.js does (the holder's category/tag grids use it). The Silverstripe 6 admin
 * (3.x) passes element nodes only (nodeType === 1) and never shows it. Not this module's; any other
 * uncaught error still fails the spec. (Measured on groupable-gridfield, 2026-10-02.)
 */
function isKnownSs5EntwineError(err: Error): boolean {
    return (
        err.message === 'el.getAttribute is not a function' &&
        /\/silverstripe\/admin\/client\/dist\/js\/vendor\.js/.test(err.stack ?? '') &&
        /\bas matches\b/.test(err.stack ?? '')
    );
}

export type Archive = { id: number; link: string; items: Record<string, number> };

/** Ask the fixture reset endpoint for a fresh archive (perpage: the holder's "items per page"). */
export async function reseed(page: Page, title: string, perpage = 0): Promise<Archive> {
    const response = await page.request.get('/admin/fa-reset/reseed', { params: { title, perpage: String(perpage) } });
    expect(response.status(), `reseed "${title}"`).toBe(200);
    return response.json();
}

/** The item titles the holder page lists, in order. */
export async function listed(page: Page): Promise<string[]> {
    return page.locator('ol.fa-items > li.fa-item').evaluateAll((lis) => lis.map((li) => li.getAttribute('data-title') ?? ''));
}

/** The filter form's dropdowns. */
export function filters(page: Page) {
    const form = page.locator('.archivefilter form');
    return {
        form,
        cat: form.locator('select[name="cat"]'),
        tag: form.locator('select[name="tag"]'),
        date: form.locator('select[name="date"]'),
        // The wrappers carry the "filtering" classes; the reset link (x) sits inside them.
        catWrap: form.locator('.cat-filter'),
        tagWrap: form.locator('.tag-filter'),
        dateWrap: form.locator('.date-filter'),
    };
}

/** A dropdown's options as [value, label] pairs. */
export async function options(select: Locator): Promise<[string, string][]> {
    return select.locator('option').evaluateAll((os) => os.map((o) => [(o as HTMLOptionElement).value, (o.textContent ?? '').trim()]));
}

/**
 * Choose a dropdown option and wait for the document navigation its onchange submit causes: the
 * dropdowns submit the form themselves (inline onchange="this.form.submit()"), there is no button.
 */
export async function choose(page: Page, select: Locator, value: string): Promise<URL> {
    const navigated = page.waitForRequest((r) => r.isNavigationRequest() && r.frame() === page.mainFrame());
    // The 'load' event of the NEW document (waitForLoadState would see the old one, already loaded).
    const loaded = page.waitForEvent('load');
    await select.selectOption(value);
    const request = await navigated;
    await loaded;
    return new URL(request.url());
}

/** Click the reset (x) link of a filter and wait for the navigation it causes. */
export async function reset(page: Page, wrap: Locator): Promise<URL> {
    const navigated = page.waitForRequest((r) => r.isNavigationRequest() && r.frame() === page.mainFrame());
    const loaded = page.waitForEvent('load');
    await wrap.locator('a.input-group-text').click();
    const request = await navigated;
    await loaded;
    return new URL(request.url());
}

/** Click a link and wait until the page it leads to has loaded. */
export async function follow(page: Page, link: Locator): Promise<void> {
    const loaded = page.waitForEvent('load');
    await link.click();
    await loaded;
}

/** The CMS page editor for a page. */
export async function openInCms(page: Page, id: number): Promise<Locator> {
    await page.goto(`/admin/pages/edit/show/${id}`);
    const form = page.locator('form#Form_EditForm');
    await expect(form.locator('input[name="Title"]')).toBeVisible();
    return form;
}

/** Save the page in the CMS ("Save" = write the draft) and wait for the AJAX save to come back 200. */
export async function saveDraft(page: Page): Promise<Request> {
    const posted = page.waitForRequest((r) => r.method() === 'POST' && /\/admin\/pages\/edit\/EditForm/.test(r.url()));
    await page.locator('button[name="action_save"]').click();
    const request = await posted;
    expect(['xhr', 'fetch']).toContain(request.resourceType());
    expect((await request.response())?.status(), 'save answered 200').toBe(200);
    // The CMS swaps in the re-rendered form after the save.
    await expect(page.locator('form#Form_EditForm input[name="Title"]')).toBeVisible();
    return request;
}

/** A form field's holder by field name; a GridField is its own holder (no _Holder wrapper). */
export function holder(form: Locator, name: string): Locator {
    return form.locator(`#Form_EditForm_${name}_Holder, fieldset#Form_EditForm_${name}.grid-field`);
}

/** The values of a set of inputs, in order. */
export async function values(inputs: Locator): Promise<string[]> {
    return inputs.evaluateAll((els) => els.map((el) => (el as HTMLInputElement).value));
}
