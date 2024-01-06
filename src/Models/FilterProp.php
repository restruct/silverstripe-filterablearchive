<?php

namespace Restruct\SilverStripe\FilterableArchive;

use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Forms\Filter;
use SilverStripe\ORM\DataObject;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\TextField;
use SilverStripe\ORM\FieldType\DBVarchar;
use SilverStripe\View\Parsers\URLSegmentFilter;
use SilverStripe\Control\Controller;

/**
 *
 **/
class FilterProp extends DataObject
{
    private static $table_name = 'FilterProp';

    private static $db = [
        'Title'      => DBVarchar::class . '(255)',
        'URLSegment' => DBVarchar::class . '(255)',
    ];

    private static $has_one = [
        "CatHolderPage" => SiteTree::class,
        "TagHolderPage" => SiteTree::class,
    ];

    private static $belongs_to = [
        'CatItems' => FilterPropRelation::class.'.Category',
        'TagItems' => FilterPropRelation::class.'.Tag',
    ];

//    /**
//     * Example iterator placeholder for belongs_many_many.
//     * This is a list of arbitrary types of objects (NOT a traditional ArrayList, so cannot do '->column(XY)' etc).
//     *
//     * For a regular ArrayList, eg: Page::get()->filter('ID',  FilterPropRelation::get()->filter('CategoryID', $cat->ID)->column('ItemID'));
//     *
//     * @return Generator|DataObject[]
//     */
//    public function CatItems()
//    {
//        foreach ( $this->CatItems() as $filterPropRelItem ) {
//            yield $filterPropRelItem->Item();
//        }
//    }
//
//    /**
//     * Example iterator placeholder for belongs_many_many.
//     * This is a list of arbitrary types of objects (NOT a traditional ArrayList, so cannot do '->column(XY)' etc).
//     *
//     * For a regular ArrayList, eg: Page::get()->filter('ID',  FilterPropRelation::get()->filter('TagID', $cat->ID)->column('ItemID'));
//     *
//     * @return Generator|DataObject[]
//     */
//    public function TagItems()
//    {
//        foreach ( $this->TagItems() as $filterPropRelItem ) {
//            yield $filterPropRelItem->Item();
//        }
//    }

    public function Items()
    {
        $itemIDs = FilterPropRelation::get()
            ->filter(($this->CatHolderPageID ? 'CategoryID' : 'TagID'), $this->ID)
            ->column('ItemID');
        return SiteTree::get()->filter('ID', count($itemIDs) ? $itemIDs : -1);
    }

//    public function CatItems()
//    {
//        $itemIDs = FilterPropRelation::get()->filter('CategoryID', $this->ID)->column('ItemID');
//        return SiteTree::get()->filter('ID', count($itemIDs) ? $itemIDs : -1);
//    }
//
//    public function TagItems()
//    {
//        $itemIDs = FilterPropRelation::get()->filter('TagID', $this->ID)->column('ItemID');
//        return SiteTree::get()->filter('ID', count($itemIDs) ? $itemIDs : -1);
//    }

    public function onBeforeWrite()
    {
        parent::onBeforeWrite();
        if ( $this->Title ) {
            $filter = URLSegmentFilter::create();
            $this->URLSegment = $filter->filter($this->Title);
        }
    }

    /**
     * Returns a relative URL for the cat/tag/prop link
     *
     * @return string URL
     **/
    public function getLink($relSegment=null)
    {
        $HolderPage = null;
        if($this->CatHolderPageID) {
            $HolderPage = $this->CatHolderPage();
            $relSegment = 'cat';
        } else if($this->TagHolderPageID) {
            $HolderPage = $this->TagHolderPage();
            $relSegment = 'tag';
        }
        if($HolderPage) {
            $link = $HolderPage->Link();
            return $link . (strpos($link, '?') ? '&' : '?') . "{$relSegment}={$this->URLSegment}";
        }

        return null;
    }

    /**
     * @return boolean
     */
    public function canCreate($member = null, $context = [])
    {
        $extended = $this->extendedCan(__FUNCTION__, $member, $context);
        if ( $extended !== null ) {
            return $extended;
        }

        return true;
    }

    /**
     * @return boolean
     */
    public function canView($member = null, $context = [])
    {
        $extended = $this->extendedCan(__FUNCTION__, $member, $context);
        if ( $extended !== null ) {
            return $extended;
        }

        return true;
    }

    /**
     * @return boolean
     */
    public function canEdit($member = null, $context = [])
    {
        $extended = $this->extendedCan(__FUNCTION__, $member, $context);
        if ( $extended !== null ) {
            return $extended;
        }

        return true;
    }

    /**
     * @return boolean
     */
    public function canDelete($member = null, $context = [])
    {
        $extended = $this->extendedCan(__FUNCTION__, $member, $context);
        if ( $extended !== null ) {
            return $extended;
        }

        return true;
    }
}
