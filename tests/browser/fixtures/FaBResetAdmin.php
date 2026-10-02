<?php

namespace Restruct\FaBrowser;

use Restruct\SilverStripe\FilterableArchive\FilterProp;
use SilverStripe\Admin\LeftAndMain;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\View\Parsers\URLSegmentFilter;

/**
 * BROWSER-TEST FIXTURE ONLY - lets a spec start from a known, published archive:
 * GET /admin/fa-reset/reseed?title=...&perpage=N answers
 * {"id": holder ID, "link": holder URL, "items": {title: ID}}.
 *
 * The archive: a holder with the date (unit: month), category and tag filters on, two categories
 * (Events, Press), two tags (Blue, Green) and five items, newest first:
 *   Jun 2024 events (2024-06-11 08:15, Events, Blue + Green)
 *   May 2024 plain  (2024-05-20 14:00, nothing)
 *   May 2024 press  (2024-05-03 09:30, Press, Blue + Green)
 *   Jan 2023 events (2023-01-15 10:00, Events, Blue)
 *   Dec 2022 press  (2022-12-24 18:00, Press)
 *
 * A LeftAndMain because the admin routes those by url_segment with no YAML. LeftAndMain's own
 * access check applies, so only the logged-in admin can call it. See FaBHolder for why this never
 * loads in a real install.
 */
class FaBResetAdmin extends LeftAndMain
{
    private static $url_segment = 'fa-reset';

    private static $menu_title = 'Filterable browser reset';

    private static $allowed_actions = ['reseed'];

    public function reseed(HTTPRequest $request): HTTPResponse
    {
        $title = (string) $request->getVar('title');
        if ($title === '') {
            return $this->httpError(400, 'title is required');
        }
        $segment = 'fa-' . URLSegmentFilter::create()->filter($title);

        foreach (FaBHolder::get()->filter('URLSegment', $segment) as $old) {
            foreach (FaBItem::get()->filter('ParentID', $old->ID) as $item) {
                $item->doArchive();
            }
            foreach ($old->Categories() as $prop) {
                $prop->delete();
            }
            foreach ($old->Tags() as $prop) {
                $prop->delete();
            }
            $old->doArchive();
        }

        $holder = FaBHolder::create([
            'Title' => $title,
            'URLSegment' => $segment,
            'DateFilterEnabled' => true,
            'ArchiveUnit' => 'month',
            'CategoriesFilterEnabled' => true,
            'TagsFilterEnabled' => true,
            'ItemsPerPage' => (int) $request->getVar('perpage'),
        ]);
        $holder->write();
        $holder->publishSingle();

        $props = [];
        foreach (['Events' => 'CatHolderPageID', 'Press' => 'CatHolderPageID', 'Blue' => 'TagHolderPageID', 'Green' => 'TagHolderPageID'] as $propTitle => $key) {
            $prop = FilterProp::create(['Title' => $propTitle, $key => $holder->ID]);
            $prop->write();
            $props[$propTitle] = $prop;
        }

        $seeds = [
            ['Jun 2024 events', '2024-06-11 08:15:00', ['Events'], ['Blue', 'Green']],
            ['May 2024 plain', '2024-05-20 14:00:00', [], []],
            ['May 2024 press', '2024-05-03 09:30:00', ['Press'], ['Blue', 'Green']],
            ['Jan 2023 events', '2023-01-15 10:00:00', ['Events'], ['Blue']],
            ['Dec 2022 press', '2022-12-24 18:00:00', ['Press'], []],
        ];
        $ids = [];
        foreach ($seeds as [$itemTitle, $date, $cats, $tags]) {
            $item = FaBItem::create([
                'Title' => $itemTitle,
                'URLSegment' => $segment . '-' . URLSegmentFilter::create()->filter($itemTitle),
                'ParentID' => $holder->ID,
                'Date' => $date,
            ]);
            $item->write();
            foreach ($cats as $cat) {
                $item->Categories()->add($props[$cat]);
            }
            foreach ($tags as $tag) {
                $item->Tags()->add($props[$tag]);
            }
            $item->publishSingle();
            $ids[$itemTitle] = $item->ID;
        }

        return HTTPResponse::create(json_encode(['id' => $holder->ID, 'link' => $holder->Link(), 'items' => $ids]))
            ->addHeader('Content-Type', 'application/json');
    }
}
