<?php

namespace Restruct\SilverStripe\FilterableArchive\Extensions;

use Restruct\SilverStripe\FilterableArchive\FilterPropRelation;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Extension;
use SilverStripe\ORM\DataList;
# PaginatedList is NOT imported: it moved from SilverStripe\ORM (5) to SilverStripe\Model\List (6)
# with no alias left behind, so the class name is resolved per major - see paginatedListClass().

/**
 * Class FilterableArchiveHolderControllerExtension
 *
 * @package Restruct\SilverStripe\FilterableArchive
 */
class HolderControllerExtension extends Extension
{
    private static $allowed_actions = [
        'archive', # renamed to 'date'
        'date',
        'tag',
        'cat',
    ];

    private static $url_handlers = [
        'archive/$Year!/$Month/$Day' => 'date', # renamed to 'date'
        'date/$Date!' => 'date',
        'tag/$Tag!' => 'tag',
        'cat/$Category!' => 'cat',
    ];

    /**
     * Renders an archive for a specificed date. This can be by year or year/month
     **/
    public function date()
    {
        return $this->owner;
    }

    public function getFilteredDate()
    {
        $request = $this->owner->request;
        $date = $request->requestVar('date') ?: $request->param('Date');
        if ($date) {
            return $date;
        }

        # Legacy route archive/$Year!/$Month/$Day (see url_handlers) still points at date(): turn its
        # params into the same yyyy[-mm[-dd]] form, or old archive links silently show every item
        $parts = array_filter([$request->param('Year'), $request->param('Month'), $request->param('Day')]);

        return $parts ? implode('-', $parts) : null;
    }

    /**
     * Renders the blog posts for a given tag
     **/
    public function tag()
    {
        return $this->owner;
    }

    public function getFilteredTagSegment()
    {
        return $this->owner->request->requestVar('tag') ?: $this->owner->request->param('Tag');
    }

    /**
     * Renders the blog posts for a given category
     **/
    public function cat()
    {
        return $this->owner;
    }

    public function getFilteredCatSegment()
    {
        return $this->owner->request->requestVar('cat') ?: $this->owner->request->param('Category');
    }

    /**
     * Returns items for a given date period.
     *
     * @param $year  int
     * @param $month int
     * @param $dat   int
     *
     * @return DataList
     **/
    public function getFilteredArchiveItems()
    {
        /** @var DataList $items */
        $items = $this->owner->getItems();

        // get items filtered by date and then filter by cat (GET yyyy-mm-dd or params date/$Date)
        $filteredDate = $this->getFilteredDate();
        if ( $this->owner->ArchiveActive() && $filteredDate ) {
            [ $year, $month, $day ] = array_pad(explode('-', (string) $filteredDate), 3, null);

            $dateField = Config::inst()->get($this->owner->className, 'managed_object_date_field');
            # Was: YEAR("field") = y AND MONTH(...) = m AND DAY(...) = d, which only exists on
            # MySQL/MariaDB (#4). The same period as a range on the stored value works on every
            # database adapter and lets the database use an index on the date field:
            # start of the period inclusive, start of the next period exclusive.
            // $dateFilter = [];
            // if ($year) {
            //     $dateFilter[ sprintf('YEAR("%s")', $dateField) ] = $year;
            // }
            // if ($month) {
            //     $dateFilter[ sprintf('MONTH("%s")', $dateField) ] = $month;
            // }
            // if ($day) {
            //     $dateFilter[ sprintf('DAY("%s")', $dateField) ] = $day;
            // }
            // if ( $dateFilter !== [] ) {
            //     $items = $items->where($dateFilter);
            // }
            $period = $this->datePeriod($year, $month, $day);
            if ($period === null) {
                # Names no real period (month 13, 30 February, not a number): YEAR()/MONTH()/DAY()
                # matched no row for these either, so the result stays empty
                $items = $items->filter('ID', -1);
            } else {
                $items = $items->filter([
                    $dateField . ':GreaterThanOrEqual' => $period[0],
                    $dateField . ':LessThan' => $period[1],
                ]);
            }
        }

        // filter by Cat
        $catSegment = $this->getFilteredCatSegment();
        if ( $catSegment && $catObj = $this->owner->Categories()->filter("URLSegment", $catSegment)->first() ) {
            $itemIDs = FilterPropRelation::get()->filter('CategoryID',$catObj->ID)->column('ItemID');
            $items = $items->filter('ID', count($itemIDs) ? $itemIDs : -1);
        }

        // filter by Tag
        $tagSegment = $this->getFilteredTagSegment();
        if ( $tagSegment && $tagObj = $this->owner->Tags()->filter("URLSegment", $tagSegment)->first() ) {
            $itemIDs = FilterPropRelation::get()->filter('TagID',$tagObj->ID)->column('ItemID');
            $items = $items->filter('ID', count($itemIDs) ? $itemIDs : -1);
        }

        return $items;
    }

    /**
     * The [start, end) of the period a filtered date names, as Y-m-d strings: a year, a month or a
     * day, by how many of its parts are given. Null when the parts name no real date. A plain
     * Y-m-d bound compares correctly against a Date as well as a Datetime field on MySQL/MariaDB,
     * PostgreSQL and SQLite (where the stored value is text in the same sortable format).
     *
     * @param string|null $year
     * @param string|null $month
     * @param string|null $day
     * @return string[]|null
     */
    protected function datePeriod($year, $month, $day)
    {
        # Each given part must be a plain number; a day needs its month (the URL is yyyy[-mm[-dd]])
        foreach ([$year, $month, $day] as $part) {
            if ($part !== null && $part !== '' && !ctype_digit((string) $part)) {
                return null;
            }
        }
        $year = (int) $year;
        $month = ($month === null || $month === '') ? null : (int) $month;
        $day = ($day === null || $day === '') ? null : (int) $day;
        # Four-digit years only, and not 9999: the ORM formats each bound through DBDate, which
        # cannot parse a year below 1000 (it throws, so date/0050 would be a server error) nor the
        # 10000-01-01 end bound of 9999. YEAR() = such a year matched no item anyway.
        if ($year < 1000 || $year > 9998 || ($day !== null && $month === null)) {
            return null;
        }
        if ($month !== null && ($month < 1 || $month > 12)) {
            return null;
        }
        if ($day !== null && !checkdate($month, $day, $year)) {
            return null;
        }

        $start = new \DateTimeImmutable(sprintf('%04d-%02d-%02d', $year, $month ?? 1, $day ?? 1));
        if ($day !== null) {
            $end = $start->modify('+1 day');
        } elseif ($month !== null) {
            $end = $start->modify('+1 month');
        } else {
            $end = $start->modify('+1 year');
        }

        return [$start->format('Y-m-d'), $end->format('Y-m-d')];
    }

    /**
     * Returns a list of paginated blog posts based on the blogPost dataList
     *
     * @return PaginatedList
     **/
    public function PaginatedItems()
    {
        $listClass = $this->paginatedListClass();
        $items = $listClass::create($this->getFilteredArchiveItems(), $this->owner->request);
        // If pagination is set to '0' then no pagination will be shown.
        if ( $this->owner->ItemsPerPage > 0 ) {
            $items->setPageLength($this->owner->ItemsPerPage);
        } else {
            $items->setPageLength($items->getTotalItems());
        }

        return $items;
    }

    /**
     * PaginatedList class for the running Silverstripe major (moved to SilverStripe\Model\List in 6).
     *
     * @return string
     */
    protected function paginatedListClass()
    {
        return class_exists('SilverStripe\\Model\\List\\PaginatedList')
            ? 'SilverStripe\\Model\\List\\PaginatedList'
            : 'SilverStripe\\ORM\\PaginatedList';
    }
}
