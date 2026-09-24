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

            $dateFilter = [];
            $dateField = Config::inst()->get($this->owner->className, 'managed_object_date_field');
            if ($year) {
                $dateFilter[ sprintf('YEAR("%s")', $dateField) ] = $year;
            }

            if ($month) {
                $dateFilter[ sprintf('MONTH("%s")', $dateField) ] = $month;
            }

            if ($day) {
                $dateFilter[ sprintf('DAY("%s")', $dateField) ] = $day;
            }

            if ( $dateFilter !== [] ) {
                $items = $items->where($dateFilter);
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
