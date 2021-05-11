<?php

namespace Restruct\SilverStripe\FilterableArchive\Extensions;

use Restruct\SilverStripe\FilterableArchive\FilterProp;
use Restruct\SilverStripe\FilterableArchive\FilterPropRelation;
use SilverStripe\Core\Config\Config;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\Core\Extension;
use SilverStripe\ORM\ArrayList;
use SilverStripe\ORM\DataList;
use SilverStripe\ORM\FieldType\DBDate;
use SilverStripe\ORM\FieldType\DBField;
use SilverStripe\ORM\PaginatedList;

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
        return $this->owner->request->requestVar('date') ?: $this->owner->request->param('Date');
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
            [ $year, $month, $day ] = array_pad(explode('-', $filteredDate), 3, null);

            $dateFilter = [];
            $dateField = Config::inst()->get($this->owner->className, 'managed_object_date_field');
            if ( $year ) $dateFilter[ "YEAR(\"{$dateField}\")" ] = $year;
            if ( $month ) $dateFilter[ "MONTH(\"{$dateField}\")" ] = $month;
            if ( $day ) $dateFilter[ "DAY(\"{$dateField}\")" ] = $day;
            if ( count($dateFilter) ) {
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
        $items = PaginatedList::create($this->getFilteredArchiveItems(), $this->owner->request);
        // If pagination is set to '0' then no pagination will be shown.
        if ( $this->owner->ItemsPerPage > 0 ) {
            $items->setPageLength($this->owner->ItemsPerPage);
        } else {
            $items->setPageLength($items->getTotalItems());
        }

        return $items;
    }

}
