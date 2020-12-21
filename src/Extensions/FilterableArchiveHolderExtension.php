<?php

namespace Restruct\Silverstripe\FilterableArchive;

use \SilverStripe\CMS\Model\SiteTreeExtension;
use SilverStripe\Forms\FieldList;
use SilverStripe\Core\Config\Config;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\NumericField;
use SilverStripe\Forms\GridField\GridFieldConfig;
use SilverStripe\Forms\GridField\GridFieldButtonRow;
use SilverStripe\Forms\GridField\GridFieldToolbarHeader;
use SilverStripe\ORM\FieldType\DBDate;
use SilverStripe\ORM\FieldType\DBEnum;
use SilverStripe\ORM\FieldType\DBInt;
use Symbiote\GridFieldExtensions\GridFieldTitleHeader;
use Symbiote\GridFieldExtensions\GridFieldEditableColumns;
use SilverStripe\Forms\GridField\GridFieldDeleteAction;
use Symbiote\GridFieldExtensions\GridFieldAddNewInlineButton;
use SilverStripe\Forms\GridField\GridField;
//use EmbargoExpirySchedulerExtension;
use SilverStripe\Core\Convert;
use SilverStripe\ORM\FieldType\DBField;
use SilverStripe\Control\Controller;

class FilterableArchiveHolderExtension extends SiteTreeExtension
{
    
    private static $managed_object_class = "Page";
    private static $managed_object_date_field = "Created";
    
    private static $pagination_control_tab = "Root.Main";
    private static $pagination_insert_before = null;
    private static $pagination_active = true;
    
    private static $datearchive_active = 'dates';
    private static $categories_active = 'categories';
    private static $tags_active = 'tags';

    private static $db = array(
        'ItemsPerPage' => DBInt::class,
        'ArchiveUnit' => DBEnum::class.'("year, month, day")',
    );
    
    private static $has_many = array(
        "Tags" => FilterTag::class,
        "Categories" => FilterCategory::class,
    );

    // add fields to CMS
    public function updateCMSFields(FieldList $fields)
    {
        
        // check if the insertbefore field is present (may be added later, in which case the above 
        // fields never get added
        //$insertOnTab = $this->owner->getFilterableArchiveConfigValue('pagination_control_tab');
        //$insertBefore = $this->owner->getFilterableArchiveConfigValue('pagination_insert_before');
        $insertOnTab = Config::inst()->get($this->owner->className, 'pagination_control_tab');
        $insertBefore = Config::inst()->get($this->owner->className, 'pagination_insert_before');
        if (!$fields->fieldByName("$insertOnTab.$insertBefore")) {
            $insertBefore = null;
        }
        
        //if($this->owner->getFilterableArchiveConfigValue('datearchive_active')){
        if (Config::inst()->get($this->owner->className, 'datearchive_active')) {
            //$fields->addFieldToTab($this->owner->getFilterableArchiveConfigValue('pagination_control_tab'), 
            $fields->addFieldToTab(Config::inst()->get($this->owner->className, 'pagination_control_tab'),
                DropdownField::create('ArchiveUnit',
                    _t('filterablearchive.ARCHIVEUNIT', 'Archive unit'),
                    array(
                        'year' => _t('filterablearchive.YEAR', 'Year'),
                        'month' => _t('filterablearchive.MONTH', 'Month'),
                        'day' => _t('filterablearchive.DAY', 'Day'),
                    )), $insertBefore);
        }
        
        $pagerField = NumericField::create("ItemsPerPage",
                _t("filterablearchive.ItemsPerPage", "Pagination: items per page"))
                ->setRightTitle(_t("filterablearchive.LeaveEmptyForNone",
                        "Leave empty or '0' for no pagination"));
        
        $fields->addFieldToTab(
                $insertOnTab,
                $pagerField,
                $insertBefore
                );
        
        //
        // Create categories and tag config
        //
        $config = GridFieldConfig::create()
        ->addComponent(new GridFieldButtonRow())
        ->addComponent(new GridFieldEditableColumns())
        ->addComponent(new GridFieldDeleteAction())
        ->addComponent(new GridFieldAddNewInlineButton());

        if (Config::inst()->get($this->owner->className, 'categories_active')) {
            $fields->addFieldToTab($insertOnTab,
                    $categories = GridField::create(
                        "Categories",
                        _t("FilterableArchive.Categories", "Categories"),
                        $this->owner->Categories(),
                        $config
                    ), $insertBefore);
            $categories->getConfig()
                ->getComponentByType(GridFieldAddNewInlineButton::class)
                ->setTitle(_t("FilterableArchive.AddCategory", "Add category"));
        }

        if (Config::inst()->get($this->owner->className, 'tags_active')) {
            $fields->addFieldToTab($insertOnTab,
                    $tags = GridField::create(
                        "Tags",
                        _t("FilterableArchive.Tags", "Tags"),
                        $this->owner->Tags(),
                        $config
                    ), $insertBefore);
            $tags->getConfig()
                ->getComponentByType(GridFieldAddNewInlineButton::class)
                ->setTitle(_t("FilterableArchive.AddTag", "Add tag"));
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
        
        // workaround for Embargo/Expiry (augmentSQL for embargo/expiry is not working yet);
        if ($class::has_extension("EmbargoExpirySchedulerExtension")) {
            $items = $items->where(EmbargoExpirySchedulerExtension::extraWhereQuery($class));
        }
        
        //Allow decorators to manipulate list, eg to use this to manage non SiteTree Items
        $this->owner->extend('updateGetItems', $items);
        
        return $items;
    }
    
    //
    // Dropdowns for available archiveitems
    //
    public function ArchiveActive(){
        return Config::inst()->get($this->owner->className, 'datearchive_active');
    }
    public function ArchiveUnitDropdown($emptyString = null)
    {
        if(!$this->ArchiveActive()) return;

        // build array with available archive 'units'
        $dateField = Config::inst()->get($this->owner->className, 'managed_object_date_field');
        $itemArr = array();
        foreach ($this->owner->getItems() as $item) {
            if (!$item->$dateField) {
                continue;
            }
            $dateObj = DBDate::create()->setValue(strtotime($item->$dateField));
            // So apparently DBDate format was switched from PHP to CLDR formatting:
            // http://userguide.icu-project.org/formatparse/datetime#TOC-Date-Field-Symbol-Table
            if ($this->owner->ArchiveUnit == 'day') {
                $arrkey = $dateObj->Format('yyyy-MM-dd');
                $arrval = $dateObj->Format('d MMMM yyyy');
            } elseif ($this->owner->ArchiveUnit == 'month') {
                $arrkey = $dateObj->Format('yyyy-MM');
                $arrval = $dateObj->Format('MMMM yyyy');
            } else {
                $arrkey = $dateObj->Format('yyyy');
                $arrval = $dateObj->Format('yyyy');
            }
            // add date if not yet in array
            if (!array_key_exists($arrkey, $itemArr)) {
                $itemArr[$arrkey] =  $arrval;
            }
        }
        
        $DrDown = new DropdownField('date', '', $itemArr);
//        $DrDown->setEmptyString($emptyString ?: _t('filterablearchive.FILTERDATE', 'Filter by date'));
        $DrDown->setEmptyString($emptyString ?: sprintf(_t('filterablearchive.FILTERBY', 'Filter by %s'), 'date'));
        $DrDown->addExtraClass("dropdown form-control");

        $ctrl = Controller::curr();
        $reqParams = $ctrl->getRequest()->params();

//        $DrDown->setValue(
//        $DrDown->setAttribute('onchange', "location = '{$this->owner->AbsoluteLink()}date/'+this.value.replace('-','/')+'/';");

        $DrDown->setValue($ctrl->request->requestVar('date'));
        $DrDown->setAttribute('onchange', "this.form.submit()");

        return $DrDown;
    }

    public function TagsActive(){
        return Config::inst()->get($this->owner->className, 'tags_active');
    }
    public function CategoriesActive(){
        return Config::inst()->get($this->owner->className, 'categories_active');
    }
    public function FilterDropdown($CatOrTag='cat', $emptyString = null)
    {
        if($CatOrTag=='tag' && !$this->TagsActive()) return;
        if($CatOrTag=='cat' && !$this->CategoriesActive()) return;

        $itemArr = array();
        $items = $CatOrTag=='cat' ? $this->owner->Categories() : $this->owner->Tags();
        foreach($items as $item){
            if (!array_key_exists($item->URLSegment, $itemArr)) {
                $itemArr[$item->URLSegment] = $item->Title;
            }
        }

        $DrDown = new DropdownField( $CatOrTag, '', $itemArr );
        $DrDown->addExtraClass("dropdown form-control");
        $term = ($CatOrTag=='cat' ? _t('filterablearchive.CAT', 'category') : _t('filterablearchive.TAG', 'tag'));
        $DrDown->setEmptyString($emptyString ?: sprintf(_t('filterablearchive.FILTERBY', 'Filter by %s'), $term));

        $ctrl = Controller::curr();
        if ($ctrl::has_extension(FilterableArchiveHolderControllerExtension::class)) {
            $DrDown->setValue($ctrl->request->requestVar($CatOrTag));
        }
        $DrDown->setAttribute('onchange', "this.form.submit()");

        return $DrDown;
    }
}
