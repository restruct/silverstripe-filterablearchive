<?php

namespace Restruct\FaBrowser;

use Restruct\SilverStripe\FilterableArchive\Extensions\HolderExtension;
use Restruct\SilverStripe\FilterableArchive\FilterProp;
use SilverStripe\CMS\Model\SiteTree;

/**
 * BROWSER-TEST FIXTURE ONLY - the content behind the README screenshots
 * (tests/browser/screenshots/, see its README.md): a published "News" holder at /news with the date
 * archive (by month), categories and tags switched on, four items per page, and nine
 * FaShowcaseItem pages with neutral demo content. FaShowcaseHolderController renders it.
 *
 * Set up as the README's Setup section shows, including the optional pagination_control_tab, so
 * the holder's filter settings sit on their own "Filtering" tab in the CMS.
 *
 * Never loaded by a real install: it lives under tests/browser/, which carries a _manifest_exclude
 * marker, and the browser-test runner copies it into a scratch host's app/ before dev/build.
 * Extends SiteTree, not Page: the scratch host has no app Page class.
 */
class FaShowcaseHolder extends SiteTree
{
    # Short table name: no namespaced default.
    private static $table_name = 'FaShowcaseHolder';

    private static $singular_name = 'News holder';

    private static $extensions = [
        HolderExtension::class,
    ];

    private static $managed_object_class = FaShowcaseItem::class;

    private static $managed_object_date_field = 'Date';

    private static $pagination_control_tab = 'Root.Filtering';

    private static $allowed_children = [FaShowcaseItem::class];

    /** URL segment of the seeded holder; the screenshot script opens /news. */
    public const SEGMENT = 'news';

    /**
     * The seeded items, newest first: title, date, categories, tags, summary. Neutral demo
     * content: an imaginary open-source project's news.
     */
    private const ITEMS = [
        ['Autumn meetup: call for speakers', '2026-10-14', ['Events'], ['Community'],
            'Share what you built this year: the autumn meetup is looking for short talks and demos.'],
        ['Version 4.2 released', '2026-09-30', ['Releases'], ['Performance', 'Accessibility'],
            'Faster page loads, clearer focus outlines and a long list of smaller fixes.'],
        ['A new colour palette for the documentation', '2026-09-08', ['Announcements'], ['Design', 'Accessibility'],
            'Higher contrast throughout, tested against the latest accessibility guidelines.'],
        ['Summer workshop recap', '2026-07-21', ['Events'], ['Community', 'Design'],
            'Three days, forty participants and a stack of ideas for the next release.'],
        ['Version 4.1 released', '2026-06-02', ['Releases'], ['Performance'],
            'Caching improvements that halve the time to the first byte on busy sites.'],
        ['Better keyboard navigation', '2026-04-15', ['Announcements'], ['Accessibility'],
            'Every menu and dialog can now be reached and closed without a mouse.'],
        ['Spring meetup announced', '2026-02-10', ['Events'], ['Community'],
            'Save the date: talks, a hands-on session and time to meet the maintainers.'],
        ['The year in review', '2025-12-03', ['Announcements'], ['Community'],
            'Looking back on twelve months of releases, events and new contributors.'],
        ['Version 4.0 released', '2025-11-12', ['Releases'], ['Design', 'Performance'],
            'A refreshed interface, a new build pipeline and an upgrade guide.'],
    ];

    public function requireDefaultRecords()
    {
        parent::requireDefaultRecords();

        # Seeded once; delete the page to have the next db build seed it again.
        if (self::get()->filter('URLSegment', self::SEGMENT)->exists()) {
            return;
        }

        $holder = self::create([
            'Title' => 'News',
            'URLSegment' => self::SEGMENT,
            'Content' => '<p>Releases, events and announcements from the project.</p>',
            'DateFilterEnabled' => true,
            'DateTitle' => 'Any month',
            'ArchiveUnit' => 'month',
            'CategoriesFilterEnabled' => true,
            'CategoriesTitle' => 'All categories',
            'TagsFilterEnabled' => true,
            'TagsTitle' => 'All topics',
            'ItemsPerPage' => 4,
        ]);
        $holder->write();
        $holder->publishSingle();

        # Categories and tags belong to this holder (FilterProp's has_one back to it)
        $props = [];
        foreach (['Announcements', 'Events', 'Releases'] as $title) {
            $props[$title] = FilterProp::create(['Title' => $title, 'CatHolderPageID' => $holder->ID]);
            $props[$title]->write();
        }
        foreach (['Accessibility', 'Community', 'Design', 'Performance'] as $title) {
            $props[$title] = FilterProp::create(['Title' => $title, 'TagHolderPageID' => $holder->ID]);
            $props[$title]->write();
        }

        foreach (self::ITEMS as [$title, $date, $cats, $tags, $summary]) {
            $item = FaShowcaseItem::create([
                'Title' => $title,
                'ParentID' => $holder->ID,
                'Date' => $date,
                'Content' => '<p>' . htmlspecialchars($summary) . '</p>',
            ]);
            $item->write();
            foreach ($cats as $cat) {
                $item->Categories()->add($props[$cat]);
            }
            foreach ($tags as $tag) {
                $item->Tags()->add($props[$tag]);
            }
            $item->publishSingle();
        }
    }
}
