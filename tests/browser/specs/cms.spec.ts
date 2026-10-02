import { test, expect, filters, holder, openInCms, options, reseed, saveDraft, values } from './support';

// The module's CMS fields: the holder's filter settings (toggles, labels, the inline category/tag
// grids) and an item's date and tag fields. Front-end checks after a CMS save read the draft
// (?stage=Stage): Save writes the draft, the seeded live pages stay as they were.

test('a holder shows a filter\'s label and grid only while the filter is enabled', async ({ page }) => {
    const archive = await reseed(page, 'Holder toggles');
    let form = await openInCms(page, archive.id);

    await expect(holder(form, 'ItemsPerPage')).toBeVisible();
    for (const name of ['DateTitle', 'ArchiveUnit', 'CategoriesTitle', 'Categories', 'TagsTitle', 'Tags']) {
        await expect(holder(form, name), `${name} shown while enabled`).toBeVisible();
    }
    await expect(form.locator('select[name="ArchiveUnit"]')).toHaveValue('month');
    // The label fields show the config default as placeholder.
    await expect(form.locator('input[name="TagsTitle"]')).toHaveAttribute('placeholder', 'Tags');

    // Switch tags off and save: the label and the grid go, a note says how to switch it back on.
    await form.locator('input[name="TagsFilterEnabled"]').uncheck();
    await saveDraft(page);
    form = page.locator('form#Form_EditForm');
    await expect(holder(form, 'TagsTitle')).toHaveCount(0);
    await expect(holder(form, 'Tags')).toHaveCount(0);
    await expect(holder(form, 'TagsFilterEnabled')).toContainText('Currently disabled - enable and save/publish to activate');
    // Categories are untouched.
    await expect(holder(form, 'CategoriesTitle')).toBeVisible();

    // The draft holder page has no tag dropdown any more; the others stay.
    await page.goto(`${archive.link}?stage=Stage`);
    await expect(filters(page).tag).toHaveCount(0);
    await expect(filters(page).cat).toHaveCount(1);
    await expect(filters(page).date).toHaveCount(1);
});

test('a dropdown label set on the holder replaces the default', async ({ page }) => {
    const archive = await reseed(page, 'Holder labels');
    const form = await openInCms(page, archive.id);
    await form.locator('input[name="CategoriesTitle"]').fill('All topics');
    await saveDraft(page);
    await page.goto(`${archive.link}?stage=Stage`);
    expect((await options(filters(page).cat))[0]).toEqual(['', 'All topics']);
});

test('a category added inline in the holder\'s grid is saved with the page and offered in the filter', async ({ page }) => {
    const archive = await reseed(page, 'Holder inline');
    const form = await openInCms(page, archive.id);
    const grid = form.locator('#Form_EditForm_Categories');
    await expect(grid.locator('tr.ss-gridfield-item')).toHaveCount(2);

    // GridFieldAddNewInlineButton adds an editable row; it is written when the page is saved.
    await grid.locator('.ss-gridfield-add-new-inline').click();
    const row = grid.locator('tr.ss-gridfield-inline-new');
    await expect(row).toHaveCount(1);
    await row.locator('input[name*="[Title]"]').fill('Interviews');
    await saveDraft(page);

    const saved = page.locator('#Form_EditForm_Categories');
    await expect(saved.locator('tr.ss-gridfield-item')).toHaveCount(3);
    expect(await values(saved.locator('tr.ss-gridfield-item input[name*="[Title]"]'))).toEqual(['Events', 'Press', 'Interviews']);

    await page.goto(`${archive.link}?stage=Stage`);
    expect(await options(filters(page).cat)).toContainEqual(['interviews', 'Interviews']);
});

test('an item\'s Datetime keeps its time when the item is saved', async ({ page }) => {
    const archive = await reseed(page, 'Item date');
    const id = archive.items['May 2024 press'];
    let form = await openInCms(page, id);

    // A date-and-time field for the Datetime the holder filters on (3.1.0: it was a date field,
    // which reset the time to 00:00 on every save).
    const date = form.locator('input[name="Date"]');
    await expect(date).toHaveAttribute('type', 'datetime-local');
    await expect(date).toHaveValue(/^2024-05-03T09:30/);

    await form.locator('input[name="MenuTitle"]').fill('Press, May');
    await saveDraft(page);
    form = await openInCms(page, id);
    await expect(form.locator('input[name="Date"]')).toHaveValue(/^2024-05-03T09:30/);
    await expect(form.locator('input[name="MenuTitle"]')).toHaveValue('Press, May');
});

test('a new tag typed into an item\'s tag field is created on its holder and linked to the item', async ({ page }) => {
    const archive = await reseed(page, 'Item tags');
    const id = archive.items['May 2024 plain'];
    const form = await openInCms(page, id);

    // The tag field offers the holder's tags only, and may create new ones.
    const tags = holder(form, 'Tags');
    const input = tags.locator('input[type="text"]');
    await input.click();
    await expect(page.locator('[class*="-menu"] [class*="-option"]')).toHaveText(['Blue', 'Green']);
    await input.fill('Fresh');
    await expect(page.locator('[class*="-menu"] [class*="-option"]').first()).toContainText('Fresh');
    await input.press('Enter');
    // And an existing one: typing filters the holder's tags.
    await input.fill('Blu');
    await expect(page.locator('[class*="-menu"] [class*="-option"]')).toHaveText(['Blue', 'Create "Blu"']);
    await input.press('Enter');
    await expect(tags.locator('[class*="-multiValue"]')).toHaveText(['Fresh', 'Blue']);
    await saveDraft(page);

    const reopened = await openInCms(page, id);
    // The tag field is a React component: wait for it to render its values.
    const chosen = holder(reopened, 'Tags').locator('[class*="-multiValue"]');
    await expect.poll(async () => (await chosen.allTextContents()).map((t) => t.trim()).sort()).toEqual(['Blue', 'Fresh']);

    // The new tag belongs to the holder: it is in the holder's filter, and filters to this item.
    await page.goto(`${archive.link}?stage=Stage`);
    expect(await options(filters(page).tag)).toEqual([['', 'Tags'], ['blue', 'Blue'], ['green', 'Green'], ['fresh', 'Fresh']]);
    await page.goto(`${archive.link}?stage=Stage&tag=fresh`);
    await expect(page.locator('ol.fa-items > li.fa-item')).toHaveText([/May 2024 plain/]);
});
