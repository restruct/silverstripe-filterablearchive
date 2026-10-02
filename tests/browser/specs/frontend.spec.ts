import { test, expect, ALL, choose, filters, follow, listed, options, reseed, reset } from './support';

// The holder page as a visitor uses it: the filter form (whose dropdowns submit themselves and
// whose x links reset them, both through the module's inline JavaScript), the filtered listing,
// pagination, the filter URLs, and an item's properties linking back to the filtered holder.

test('the filter form has one dropdown per active filter, and the list shows every item, newest first', async ({ page }) => {
    const archive = await reseed(page, 'Render');
    await page.goto(archive.link);
    const f = filters(page);

    expect(await options(f.cat)).toEqual([['', 'Categories'], ['events', 'Events'], ['press', 'Press']]);
    expect(await options(f.tag)).toEqual([['', 'Tags'], ['blue', 'Blue'], ['green', 'Green']]);
    // Archive unit "month": one option per month that has items, newest first.
    expect(await options(f.date)).toEqual([
        ['', 'Date'],
        ['2024-06', 'June 2024'],
        ['2024-05', 'May 2024'],
        ['2023-01', 'January 2023'],
        ['2022-12', 'December 2022'],
    ]);
    // Nothing filtered: no reset links, no "filtering" classes.
    await expect(f.form.locator('a.input-group-text')).toHaveCount(0);
    await expect(f.form.locator('.cat-filtering, .tag-filtering, .currently-filtering')).toHaveCount(0);

    expect(await listed(page)).toEqual(ALL);

    // FilterableProperties: the date with its time (a Datetime), the category as a badge and the
    // tags as pills, each linking to the holder filtered by it.
    const item = page.locator('li.fa-item[data-title="May 2024 press"]');
    await expect(item.locator('small.fst-italic')).toHaveText('3 May 2024 9:30');
    await expect(item.locator('small.badge:not(.rounded-pill) a')).toHaveText(['Press']);
    await expect(item.locator('small.badge.rounded-pill a')).toHaveText(['Blue', 'Green']);
    await expect(item.locator('small.badge.rounded-pill a', { hasText: 'Green' })).toHaveAttribute('href', `${archive.link}?tag=green`);
});

test('choosing a category submits the form and lists only its items; its x link resets the filter', async ({ page }) => {
    const archive = await reseed(page, 'Category');
    await page.goto(archive.link);

    const url = await choose(page, filters(page).cat, 'press');
    expect(url.searchParams.get('cat')).toBe('press');
    expect(await listed(page)).toEqual(['May 2024 press', 'Dec 2022 press']);

    const f = filters(page);
    await expect(f.cat).toHaveValue('press');
    await expect(f.catWrap).toHaveClass(/\bcat-filtering\b/);
    await expect(f.catWrap).toHaveClass(/\bcurr-cat-press\b/);
    await expect(f.catWrap.locator('a.input-group-text')).toHaveText('×');

    // The x link sets its dropdown back to the empty option and submits the form again.
    const after = await reset(page, f.catWrap);
    expect(after.pathname).toBe(archive.link);
    expect(after.searchParams.get('cat') ?? '').toBe('');
    expect(await listed(page)).toEqual(ALL);
    await expect(filters(page).cat).toHaveValue('');
    await expect(filters(page).form.locator('a.input-group-text')).toHaveCount(0);
});

test('filters combine: a month and then a tag narrow the list together, and both show as active', async ({ page }) => {
    const archive = await reseed(page, 'Combine');
    await page.goto(archive.link);

    await choose(page, filters(page).date, '2024-05');
    expect(await listed(page)).toEqual(['May 2024 plain', 'May 2024 press']);

    // The form submits every dropdown, so the month stays when the tag is chosen.
    const url = await choose(page, filters(page).tag, 'green');
    expect(url.searchParams.get('date')).toBe('2024-05');
    expect(url.searchParams.get('tag')).toBe('green');
    expect(await listed(page)).toEqual(['May 2024 press']);

    // Both wrappers mark the active value (3.1.0: the tag and date classes never rendered before).
    const f = filters(page);
    await expect(f.tagWrap).toHaveClass(/\btag-filtering\b/);
    await expect(f.tagWrap).toHaveClass(/\bcurr-tag-green\b/);
    await expect(f.dateWrap).toHaveClass(/\bcurrently-filtering\b/);
    await expect(f.dateWrap).toHaveClass(/\bcurr-date-2024-05\b/);
    await expect(f.catWrap).not.toHaveClass(/cat-filtering/);

    // Resetting the month keeps the tag.
    await reset(page, f.dateWrap);
    expect(await listed(page)).toEqual(['Jun 2024 events', 'May 2024 press']);
    await expect(filters(page).tag).toHaveValue('green');
});

test('items per page paginates the list, and the page links keep the active filter', async ({ page }) => {
    const archive = await reseed(page, 'Paginate', 2);
    await page.goto(archive.link);
    expect(await listed(page)).toEqual(ALL.slice(0, 2));

    // Both pagination templates render: three pages of two.
    const plain = page.locator('.fa-pagination-plain #pageNumbers');
    await expect(plain.locator('.pages.current')).toHaveText('1');
    await expect(plain.locator('a.pages:not(.next):not(.prev)')).toHaveText(['2', '3']);
    const bootstrap = page.locator('.fa-pagination-bootstrap .pagination');
    await expect(bootstrap.locator('li.page-item.active')).toHaveText('1');
    await expect(bootstrap.locator('li.page-item').first()).toHaveClass(/disabled/);

    await follow(page, plain.locator('a.pages.next'));
    expect(await listed(page)).toEqual(ALL.slice(2, 4));
    await follow(page, bootstrap.locator('a.page-link', { hasText: '3' }));
    expect(await listed(page)).toEqual(ALL.slice(4));
    await expect(page.locator('.fa-pagination-plain a.pages.next')).toHaveCount(0);

    // Filtered to two items at two per page: no pagination at all.
    await page.goto(`${archive.link}?cat=events`);
    expect(await listed(page)).toEqual(['Jun 2024 events', 'Jan 2023 events']);
    await expect(page.locator('.fa-pagination-plain #pageNumbers')).toHaveCount(0);
    await expect(page.locator('.fa-pagination-bootstrap nav')).toHaveCount(0);
});

test('a filtered list paginates within the filter', async ({ page }) => {
    const archive = await reseed(page, 'Paginate filter', 1);
    await page.goto(archive.link);
    await choose(page, filters(page).cat, 'press');
    expect(await listed(page)).toEqual(['May 2024 press']);

    await follow(page, page.locator('.fa-pagination-plain a.pages.next'));
    expect(new URL(page.url()).searchParams.get('cat')).toBe('press');
    expect(await listed(page)).toEqual(['Dec 2022 press']);
    await expect(filters(page).cat).toHaveValue('press');
});

test('the filter URLs work and select their dropdown: cat/, tag/, date/ and the old archive/ form', async ({ page }) => {
    const archive = await reseed(page, 'Routes');

    await page.goto(`${archive.link}/cat/events`);
    expect(await listed(page)).toEqual(['Jun 2024 events', 'Jan 2023 events']);
    await expect(filters(page).cat).toHaveValue('events');

    await page.goto(`${archive.link}/tag/blue`);
    expect(await listed(page)).toEqual(['Jun 2024 events', 'May 2024 press', 'Jan 2023 events']);
    await expect(filters(page).tag).toHaveValue('blue');

    await page.goto(`${archive.link}/date/2024-05`);
    expect(await listed(page)).toEqual(['May 2024 plain', 'May 2024 press']);
    await expect(filters(page).date).toHaveValue('2024-05');

    // 3.1.0: the old route's year/month were never read, so it listed every item.
    await page.goto(`${archive.link}/archive/2023/01`);
    expect(await listed(page)).toEqual(['Jan 2023 events']);

    // A category that is not on this holder is ignored.
    await page.goto(`${archive.link}?cat=nope`);
    expect(await listed(page)).toEqual(ALL);
});

test('an item page links its category and tags to the filtered holder, and lists related items once', async ({ page }) => {
    const archive = await reseed(page, 'Item page');
    await page.goto(archive.link);
    await follow(page, page.locator('li.fa-item[data-title="May 2024 press"] a.fa-item-link'));
    await expect(page.locator('h1')).toHaveText('May 2024 press');

    const props = page.locator('.fa-props');
    await expect(props.locator('small.badge:not(.rounded-pill) a')).toHaveText(['Press']);
    await expect(props.locator('small.badge.rounded-pill a')).toHaveText(['Blue', 'Green']);

    // Related: sharing a category (Dec 2022 press) or a tag (Jan 2023 events: Blue). Jun 2024
    // events shares both tags, and is listed once all the same (3.1.0).
    const related = await page.locator('ul.fa-related > li').allTextContents();
    expect([...related].sort()).toEqual(['Dec 2022 press', 'Jan 2023 events', 'Jun 2024 events']);

    await follow(page, props.locator('small.badge.rounded-pill a', { hasText: 'Green' }));
    expect(new URL(page.url()).pathname).toBe(archive.link);
    expect(await listed(page)).toEqual(['Jun 2024 events', 'May 2024 press']);
    await expect(filters(page).tag).toHaveValue('green');
});
