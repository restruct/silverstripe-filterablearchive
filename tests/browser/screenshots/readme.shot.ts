import { test, expect, type Page } from '@playwright/test';
import * as path from 'path';

// Generates the README screenshots in docs/images/. Not a test suite: run it with
// playwright.screenshots.config.ts (see README.md next to this file). The content comes from the
// browser fixtures: FaShowcaseHolder seeds a published "News" holder at /news (date archive by
// month, categories, tags, four items per page) with nine FaShowcaseItem pages below it.

const IMAGES = path.join(__dirname, '..', '..', '..', 'docs', 'images');

/**
 * Hide what only the test host has: the browser-test fixtures' CMS menu entry ("Filterable browser
 * reset"). Presentation only; nothing about the module's own UI is changed.
 */
async function hideFixtureChrome(page: Page): Promise<void> {
    await page.addStyleTag({ content: '.cms-menu li:has(> a[href*="admin/fa-reset"]) { display: none !important; }' });
}

/** Wait for the front end's stylesheet (Bootstrap, from a CDN) to have been applied. */
async function styled(page: Page): Promise<void> {
    await expect
        .poll(() => page.evaluate(() => getComputedStyle(document.documentElement).getPropertyValue('--bs-body-font-family')), {
            message: 'Bootstrap stylesheet applied',
        })
        .not.toBe('');
}

/** Page IDs differ per host: the front end carries them as data-page-id. */
async function pageIds(page: Page): Promise<{ holder: string; items: Record<string, string> }> {
    await page.goto('/news');
    const holder = (await page.locator('#showcase').getAttribute('data-page-id'))!;
    const items: Record<string, string> = {};
    for (const article of await page.locator('article.fa-item').all()) {
        items[(await article.locator('h2').innerText()).trim()] = (await article.getAttribute('data-page-id'))!;
    }
    return { holder, items };
}

/** Let the CMS settle: no hover state, no focus ring, no running transitions. */
async function settle(page: Page): Promise<void> {
    // The empty bottom of the site tree: hovers nothing the screenshot shows.
    await page.mouse.move(200, page.viewportSize()!.height - 5);
    await page.evaluate(() => (document.activeElement as HTMLElement | null)?.blur());
    await page.waitForTimeout(600);
}

test('Front end: the archive with its filter form, item properties and pagination', async ({ page }) => {
    await page.goto('/news');
    await styled(page);
    await expect(page.locator('article.fa-item')).toHaveCount(4);
    await page.locator('#showcase').screenshot({ path: path.join(IMAGES, 'frontend-archive.png') });
});

test('Front end: two filters active, each with its reset link', async ({ page }) => {
    // What the filter form submits when a visitor picks a category and then a topic.
    await page.goto('/news?cat=events&tag=community');
    await styled(page);
    await expect(page.locator('.archivefilter a.input-group-text')).toHaveCount(2);
    await page.locator('#showcase').screenshot({ path: path.join(IMAGES, 'frontend-filtered.png') });
});

test('Front end: an item page with its related items', async ({ page }) => {
    await page.goto('/news');
    await page.locator('article.fa-item h2 a', { hasText: 'Version 4.2 released' }).click();
    await styled(page);
    await expect(page.locator('.fa-related li').first()).toBeVisible();
    await page.locator('#showcase').screenshot({ path: path.join(IMAGES, 'frontend-item.png') });
});

test('CMS: the holder page\'s Filtering tab', async ({ page }) => {
    const { holder } = await pageIds(page);
    // The CMS scrolls its edit panel internally, so a full-page capture shows no more than the
    // window: make the window tall enough for the whole tab instead, then crop.
    await page.setViewportSize({ width: 1280, height: 1600 });
    await page.goto(`/admin/pages/edit/show/${holder}`);
    await hideFixtureChrome(page);
    const form = page.locator('#Form_EditForm');
    await form.locator('a[href$="#Root_Filtering"]').click();
    await expect(form.locator('fieldset#Form_EditForm_Tags.grid-field')).toBeVisible();
    await settle(page);

    // The window down to just below the Tags grid.
    const box = (await form.locator('fieldset#Form_EditForm_Tags.grid-field').boundingBox())!;
    await page.screenshot({
        path: path.join(IMAGES, 'cms-holder-filtering.png'),
        clip: { x: 0, y: 0, width: 1280, height: Math.ceil(box.y + box.height + 16) },
    });
});

test('CMS: an item with its date, category and tag fields', async ({ page }) => {
    const { items } = await pageIds(page);
    await page.goto(`/admin/pages/edit/show/${items['Version 4.2 released']}`);
    await hideFixtureChrome(page);
    const form = page.locator('#Form_EditForm');
    const tags = form.locator('#Form_EditForm_Tags_Holder');
    await expect(tags).toBeVisible();
    await settle(page);

    const box = (await tags.boundingBox())!;
    await page.screenshot({
        path: path.join(IMAGES, 'cms-item.png'),
        // At least 500 px, so the crop does not cut through a row of the site tree.
        clip: { x: 0, y: 0, width: 1280, height: Math.max(Math.ceil(box.y + box.height + 16), 500) },
    });
});
