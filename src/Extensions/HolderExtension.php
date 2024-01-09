<?php

namespace Restruct\SilverStripe\FilterableArchive\Extensions;

use Restruct\SilverStripe\FilterableArchive\FilterProp;
use SilverStripe\CMS\Model\SiteTreeExtension;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Forms\CheckboxField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Core\Config\Config;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\HeaderField;
use SilverStripe\Forms\NumericField;
use SilverStripe\Forms\GridField\GridFieldConfig;
use SilverStripe\Forms\GridField\GridFieldButtonRow;
use SilverStripe\Forms\TextField;
use SilverStripe\ORM\DataList;
use SilverStripe\ORM\FieldType\DBBoolean;
use SilverStripe\ORM\FieldType\DBDate;
use SilverStripe\ORM\FieldType\DBEnum;
use SilverStripe\ORM\FieldType\DBInt;
use SilverStripe\ORM\FieldType\DBVarchar;
use Symbiote\GridFieldExtensions\GridFieldEditableColumns;
use SilverStripe\Forms\GridField\GridFieldDeleteAction;
use Symbiote\GridFieldExtensions\GridFieldAddNewInlineButton;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Control\Controller;

class HolderExtension
    extends SiteTreeExtension
{
    use Configurable;

    private static $managed_object_class = "Page";
    private static $managed_object_date_field = "Created";

    private static $pagination_control_tab = "Root.Main";
    private static $pagination_insert_before = null;
    private static $pagination_active = true;

    /** @config string|bool works as available/unavailable toggle (eg false) as well as placeholder label */
    private static $datearchive_active = 'Date';
    private static $categories_active = 'Categories';
    private static $tags_active = 'Tags';

    private static $db = [
        'CategoriesFilterEnabled' => DBBoolean::class,
        'CategoriesTitle' => DBVarchar::class,
        'TagsFilterEnabled' => DBBoolean::class,
        'TagsTitle' => DBVarchar::class,
        'DateFilterEnabled' => DBBoolean::class,
        'DateTitle' => DBVarchar::class,
        'ArchiveUnit' => DBEnum::class . '("year, month, day")',
        'ItemsPerPage' => DBInt::class,
    ];

    private static $has_many = [
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
                    _t("FilterableArchive.PaginationItemsPerPage", "Pagination: items per page"))
                    ->setRightTitle(_t("filterablearchive.LeaveEmptyForNoPagination",
                            "Leave empty or '0' for no pagination")
                    ),
                $insertBefore
            );
        }

        // Date-archive
        if ( Config::inst()->get($this->owner->className, 'datearchive_active') ) {
            $dateFields = [
                HeaderField::create('ArchiveHeader', _t("FilterableArchive.ArchiveHeader", "Date archive")),
                CheckboxField::create('DateFilterEnabled', _t("FilterableArchive.EnableDateArchive", 'Enable Date/archive'))
                    ->setDescription($this->owner->DateFilterEnabled ? null : _t("FilterableArchive.CurrentlyDisabled", 'Currently disabled - enable and save/publish to activate')),
            ];
            if($this->owner->DateFilterEnabled) {
                $dateFields[] = TextField::create('DateTitle', _t("FilterableArchive.DateTitle", 'DateTitle'))
                    ->setAttribute('placeholder', self::config()->get('datearchive_active'));
                $dateFields[] = DropdownField::create(
                    'ArchiveUnit',
                    _t('FilterableArchive.ArchiveUnit', 'Archive unit'),
                    [
                        'year' => _t('FilterableArchive.Year', 'Year'),
                        'month' => _t('FilterableArchive.Month', 'Month'),
                        'day' => _t('FilterableArchive.Day', 'Day'),
                    ]);
            }
            $fields->addFieldsToTab($insertOnTab, $dateFields, $insertBefore);
        }

        // Create categories and tag config
        $GFConfig = GridFieldConfig::create()
            ->addComponent(new GridFieldEditableColumns())
            ->addComponent(new GridFieldDeleteAction())
            ->addComponent(new GridFieldButtonRow('after'))
            ->addComponent(new GridFieldAddNewInlineButton('buttons-after-left'));

        if ( Config::inst()->get($this->owner->ClassName, 'categories_active') ) {
            $catFields = [
                HeaderField::create('CategoriesHeader', _t("FilterableArchive.Categories", "Categories")),
                CheckboxField::create('CategoriesFilterEnabled', _t("FilterableArchive.EnableCategories", 'Enable Categories'))
                    ->setDescription($this->owner->CategoriesFilterEnabled ? null : _t("FilterableArchive.CurrentlyDisabled", 'Currently disabled - enable and save/publish to activate')),
            ];
            if($this->owner->CategoriesFilterEnabled) {
                $catFields[] = TextField::create('CategoriesTitle', _t("FilterableArchive.CategoriesTitle", 'CategoriesTitle'))
                    ->setAttribute('placeholder', self::config()->get('categories_active'));
                $catFields[] = GridField::create(
                    "Categories",
                    _t("FilterableArchive.Categories", "Categories"),
                    $this->owner->Categories(),
                    $GFConfig
                );
            }
            $fields->addFieldsToTab($insertOnTab, $catFields, $insertBefore);
        }

        if ( Config::inst()->get($this->owner->ClassName, 'tags_active') ) {
            $tagFields = [
                HeaderField::create('TagsHeader', _t("FilterableArchive.Tags", "Tags")),
                CheckboxField::create('TagsFilterEnabled', _t("FilterableArchive.EnableTags", 'Enable Tags'))
                    ->setDescription($this->owner->TagsFilterEnabled ? null : _t("FilterableArchive.CurrentlyDisabled", 'Currently disabled - enable and save/publish to activate')),
            ];
            if($this->owner->TagsFilterEnabled) {
                $tagFields[] = TextField::create('TagsTitle', _t("FilterableArchive.TagsTitle", 'TagsTitle'))
                    ->setAttribute('placeholder', self::config()->get('tags_active'));
                $tagFields[] = GridField::create(
                    "Tags",
                    _t("FilterableArchive.Tags", "Tags"),
                    $this->owner->Tags(),
                    $GFConfig
                );
            }
            $fields->addFieldsToTab($insertOnTab, $tagFields, $insertBefore);
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
        return Config::inst()->get($this->owner->className, 'datearchive_active') && $this->owner->DateFilterEnabled;
    }

    public function CategoriesActive()
    {
        return Config::inst()->get($this->owner->className, 'categories_active') && $this->owner->CategoriesFilterEnabled;
    }

    public function TagsActive()
    {
        return Config::inst()->get($this->owner->className, 'tags_active') && $this->owner->TagsFilterEnabled;
    }

    //
    // Dropdowns for available archiveitems
    //
    public function ArchiveFilterDropdown($emptyString = null)
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
        $DrDown->addExtraClass("dropdown form-select");
        $DrDown->setAttribute('onchange', "this.form.submit()");
        $DrDown->UnsetAndSubmitOnClick = "event.preventDefault(); drd = document.getElementById('{$DrDown->getName()}'); drd.selectedIndex = 0; drd.onchange();";
        $DrDown->setEmptyString($this->owner->DateTitle ?: ($emptyString ?: self::config()->get('datearchive_active')));

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
        $DrDown->addExtraClass("dropdown form-select");
        $DrDown->setAttribute('onchange', "this.form.submit()");
        $DrDown->UnsetAndSubmitOnClick = "event.preventDefault(); drd = document.getElementById('{$DrDown->getName()}'); drd.selectedIndex = 0; drd.onchange();";

        $drdLabel = ($CatOrTag == 'cat' ? $this->owner->CategoriesTitle : $this->owner->TagsTitle);
        if(!$drdLabel) $drdLabel = $emptyString;
        if(!$drdLabel) $drdLabel = ($CatOrTag == 'cat' ? self::config()->get('categories_active') : self::config()->get('tags_active'));
        $DrDown->setEmptyString($drdLabel);

        $ctrl = Controller::curr();
        if ( $ctrl::has_extension(HolderControllerExtension::class) ) {
            $DrDown->setValue( $CatOrTag == 'cat' ? $ctrl->getFilteredCatSegment() : $ctrl->getFilteredTagSegment() );
        }

        return $DrDown;
    }
}
