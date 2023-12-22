<?php

namespace Restruct\SilverStripe\FilterableArchive\Extensions;

use Restruct\SilverStripe\FilterableArchive\FilterProp;
use SilverStripe\CMS\Model\SiteTreeExtension;
use SilverStripe\Forms\FieldList;
use SilverStripe\Core\Config\Config;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\HeaderField;
use SilverStripe\Forms\NumericField;
use SilverStripe\Forms\GridField\GridFieldConfig;
use SilverStripe\Forms\GridField\GridFieldButtonRow;
use SilverStripe\Forms\TextField;
use SilverStripe\ORM\DataList;
use SilverStripe\ORM\FieldType\DBDate;
use SilverStripe\ORM\FieldType\DBEnum;
use SilverStripe\ORM\FieldType\DBInt;
use Symbiote\GridFieldExtensions\GridFieldEditableColumns;
use SilverStripe\Forms\GridField\GridFieldDeleteAction;
use Symbiote\GridFieldExtensions\GridFieldAddNewInlineButton;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Control\Controller;

class HolderExtension extends SiteTreeExtension
{
    private static $managed_object_class = "Page";
    private static $managed_object_date_field = "Created";

    private static $pagination_control_tab = "Root.Main";
    private static $pagination_insert_before = null;
    private static $pagination_active = true;

    private static $datearchive_active = 'dates';
    private static $categories_active = 'categories';
    private static $tags_active = 'tags';

    private static $db = [
        'CategoriesTitle' => 'Varchar',
        'TagsTitle'       => 'Varchar',
        'ItemsPerPage'    => DBInt::class,
        'ArchiveUnit'     => DBEnum::class . '("year, month, day")',
    ];

    private static $has_many = [
//        "Tags"       => FilterTag::class,
//        "Categories" => FilterCategory::class,
        "Categories" => FilterProp::class.'.CatHolderPage',
        "Tags" => FilterProp::class.'.TagHolderPage',
    ];

    // add fields to CMS
    public function updateCMSFields(FieldList $fields)
    {
        // check if the insertbefore field is present (may be added later, in which case the above fields never get added)
        $insertOnTab = Config::inst()->get($this->owner->className, 'pagination_control_tab');
        $insertBefore = Config::inst()->get($this->owner->className, 'pagination_insert_before');
        if ( !$fields->fieldByName("$insertOnTab.$insertBefore") ) {
            $insertBefore = null;
        }

        // Pagination
        if ( Config::inst()->get($this->owner->className, 'pagination_active') ) {
            $fields->addFieldToTab(
                $insertOnTab,
                NumericField::create(
                    "ItemsPerPage",
                    _t("filterablearchive.ItemsPerPage", "Pagination: items per page"))
                    ->setRightTitle(_t("filterablearchive.LeaveEmptyForNone",
                            "Leave empty or '0' for no pagination")
                    ),
                $insertBefore
            );
        }

        // Date-archive
        if ( Config::inst()->get($this->owner->className, 'datearchive_active') ) {
            $fields->addFieldToTab(
                $insertOnTab,
                DropdownField::create(
                    'ArchiveUnit',
                    _t('filterablearchive.ARCHIVEUNIT', 'Archive unit'),
                    [
                        'year'  => _t('filterablearchive.YEAR', 'Year'),
                        'month' => _t('filterablearchive.MONTH', 'Month'),
                        'day'   => _t('filterablearchive.DAY', 'Day'),
                    ]),
                $insertBefore
            );
        }

        // Create categories and tag config
        $config = GridFieldConfig::create()
            ->addComponent(new GridFieldEditableColumns())
            ->addComponent(new GridFieldDeleteAction())
            ->addComponent(new GridFieldButtonRow('after'))
            ->addComponent(new GridFieldAddNewInlineButton('buttons-after-left'));

        if ( Config::inst()->get($this->owner->ClassName, 'categories_active') ) {
            $fields->addFieldsToTab($insertOnTab, [
                    HeaderField::create('CategoriesHeader', _t("FilterableArchive.Categories", "Categories")),
                    TextField::create('CategoriesTitle'),
                    $categories = GridField::create(
                        "Categories",
                        _t("FilterableArchive.Categories", "Categories"),
                        $this->owner->Categories(),
                        GridFieldConfig::create()
                            ->addComponent(new GridFieldEditableColumns())
                            ->addComponent(new GridFieldDeleteAction())
                            ->addComponent(new GridFieldButtonRow('after'))
                            ->addComponent(
                                (new GridFieldAddNewInlineButton('buttons-after-left'))
                                    ->setTitle(_t("FilterableArchive.AddCategory", "Add category"))
                            )
                    ),
                ], $insertBefore);
            $categories->getConfig()
                ->getComponentByType(GridFieldAddNewInlineButton::class)
                ->setTitle(_t("FilterableArchive.AddCategory", "Add category"));
        }

        if ( Config::inst()->get($this->owner->ClassName, 'tags_active') ) {
            $fields->addFieldsToTab($insertOnTab, [
                    HeaderField::create('TagsHeader', _t("FilterableArchive.Tags", "Tags")),
                    TextField::create('TagsTitle'),
                    $tagsGF = GridField::create(
                        "Tags",
                        _t("FilterableArchive.Tags", "Tags"),
                        $this->owner->Tags(),
                        GridFieldConfig::create()
                            ->addComponent(new GridFieldEditableColumns())
                            ->addComponent(new GridFieldDeleteAction())
                            ->addComponent(new GridFieldButtonRow('after'))
                            ->addComponent(
                                (new GridFieldAddNewInlineButton('buttons-after-left'))
                                    ->setTitle(_t("FilterableArchive.AddTag", "Add tag"))
                            )
                    ),
                ], $insertBefore);
        }
    }

    /**
     * Return unfiltered items
     *
     * @return DataList of managed_object_class
     **/
    public function getItems()
    {
        $class = Config::inst()->get($this->owner->className, 'managed_object_class');
        $dateField = Config::inst()->get($this->owner->className, 'managed_object_date_field');
        $items = $class::get()->filter('ParentID', $this->owner->ID)->sort("$dateField DESC");

        //Allow decorators to manipulate list, eg to use this to manage non SiteTree Items
        $this->owner->extend('updateGetItems', $items);

        return $items;
    }

    public function ArchiveActive()
    {
        return Config::inst()->get($this->owner->className, 'datearchive_active');
    }

    public function TagsActive()
    {
        return Config::inst()->get($this->owner->className, 'tags_active');
    }

    public function CategoriesActive()
    {
        return Config::inst()->get($this->owner->className, 'categories_active');
    }

    //
    // Dropdowns for available archiveitems
    //
    public function ArchiveUnitDropdown($emptyString = null)
    {
        if ( !$this->ArchiveActive() ) return;

        // build array with available archive 'units'
        $dateField = Config::inst()->get($this->owner->className, 'managed_object_date_field');
        $itemArr = [];
        foreach ( $this->owner->getItems() as $item ) {
            if ( !$item->$dateField ) {
                continue;
            }
            $dateObj = DBDate::create()->setValue(strtotime($item->$dateField));
            // So apparently DBDate format was switched from PHP to CLDR formatting:
            // http://userguide.icu-project.org/formatparse/datetime#TOC-Date-Field-Symbol-Table
            if ( $this->owner->ArchiveUnit === 'day' ) {
                $arrkey = $dateObj->Format('yyyy-MM-dd');
                $arrval = $dateObj->Format('d MMMM yyyy');
            } elseif ( $this->owner->ArchiveUnit === 'month' ) {
                $arrkey = $dateObj->Format('yyyy-MM');
                $arrval = $dateObj->Format('MMMM yyyy');
            } else {
                $arrkey = $dateObj->Format('yyyy');
                $arrval = $dateObj->Format('yyyy');
            }
            // add date if not yet in array
            if ( !array_key_exists($arrkey, $itemArr) ) {
                $itemArr[ $arrkey ] = $arrval;
            }
        }

        $DrDown = DropdownField::create('date', '', $itemArr);
        $DrDown->setEmptyString($emptyString ?: sprintf(_t('filterablearchive.FILTERBY', 'Filter by %s'), 'date'));
        $DrDown->addExtraClass("dropdown form-control");
        $DrDown->setAttribute('onchange', "this.form.submit()");

        $ctrl = Controller::curr();
        if ( $ctrl::has_extension(HolderControllerExtension::class) ) {
            $DrDown->setValue( $ctrl->getFilteredDate() );
        }

        return $DrDown;
    }

    public function FilterDropdown($CatOrTag = 'cat', $emptyString = null)
    {
        if ( $CatOrTag == 'tag' && !$this->TagsActive() ) return;
        if ( $CatOrTag == 'cat' && !$this->CategoriesActive() ) return;

        $itemArr = [];
        $items = $CatOrTag == 'cat' ? $this->owner->Categories() : $this->owner->Tags();
        foreach ( $items as $item ) {
            if ( !array_key_exists($item->URLSegment, $itemArr) ) {
                $itemArr[ $item->URLSegment ] = $item->Title;
            }
        }

        $DrDown = new DropdownField($CatOrTag, '', $itemArr);
        $DrDown->addExtraClass("dropdown form-control");
        $term = ( $CatOrTag == 'cat' ? _t('filterablearchive.CAT', 'category') : _t('filterablearchive.TAG', 'tag') );
        $DrDown->setEmptyString($emptyString ?: sprintf(_t('filterablearchive.FILTERBY', 'Filter by %s'), $term));
        $DrDown->setAttribute('onchange', "this.form.submit()");

        $ctrl = Controller::curr();
        if ( $ctrl::has_extension(HolderControllerExtension::class) ) {
            $DrDown->setValue( $CatOrTag == 'cat' ? $ctrl->getFilteredCatSegment() : $ctrl->getFilteredTagSegment() );
        }

        return $DrDown;
    }
}
