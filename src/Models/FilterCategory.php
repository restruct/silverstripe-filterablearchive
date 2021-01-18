<?php

namespace Restruct\SilverStripe\FilterableArchive;

use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\ORM\DataObject;
use SilverStripe\Forms\FieldList;
use SilverStripe\ORM\FieldType\DBVarchar;
use SilverStripe\Forms\TextField;
use SilverStripe\View\Parsers\URLSegmentFilter;
use SilverStripe\Control\Controller;


/**
 * A tag for keyword descriptions of a page
 *
 * @package    silverstripe
 * @subpackage filterablearchive
 *
 * @author     Michael Strong, adapted by Michael van Schaik
 **/
class FilterCategory extends DataObject
{
    private static $table_name = 'FilterCategory';

    private static $db = [
        'Title'      => DBVarchar::class . '(255)',
        'URLSegment' => DBVarchar::class . '(255)',
    ];

    private static $has_one = [
        'HolderPage' => SiteTree::class,
    ];

//    private static $many_many = array(
//        'Pages' => SiteTree::class,
//    );
    // has_many works, but belongs_many_many will not
    private static $has_many = [
        'MappedPages' => FilterMapping::class,
    ];

    /**
     * Example iterator placeholder for belongs_many_many.
     * This is a list of arbitrary types of objects
     *
     * @return Generator|DataObject[]
     */
    public function Pages()
    {
        foreach ( $this->MappedPages() as $mapping ) {
            yield $mapping->Item();
        }
    }

    public function getCMSFields()
    {
        $fields = new FieldList(
            TextField::create('Title', _t("FilterableArchive.CategoryTitle", "Category"))
        );
        $this->extend("updateCMSFields", $fields);

        return $fields;
    }

    public function onBeforeWrite()
    {
        parent::onBeforeWrite();

        // set URLSegment
        if ( $this->Title ) {
            $filter = URLSegmentFilter::create();
            $this->URLSegment = $filter->filter($this->Title);
            //$this->URLSegment = SiteTree::GenerateURLSegment($this->Title);
        }
    }

    /**
     * Returns a relative URL for the tag link
     *
     * @return string URL
     **/
    public function getLink()
    {
        return Controller::join_links($this->HolderPage()->Link(), "cat", $this->URLSegment);
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
    public function canCreate($member = null, $context = [])
    {
        parent::canCreate();
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
}
