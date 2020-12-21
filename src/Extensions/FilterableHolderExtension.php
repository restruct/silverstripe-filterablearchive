<?php

namespace Restruct\SilverStripe\FilterableArchive\Extensions {

    use Restruct\FilterableArchive\FilterableCategory;
    use Restruct\FilterableArchive\FilterableTag;
    use SilverStripe\CMS\Model\SiteTreeExtension;
    use Page;
    use SilverStripe\Control\Controller;
    use SilverStripe\Core\Config\Config;
    use SilverStripe\Core\Convert;
    use SilverStripe\Forms\DropdownField;
    use SilverStripe\Forms\FieldList;
    use SilverStripe\Forms\GridField\GridField;
    use SilverStripe\Forms\GridField\GridFieldButtonRow;
    use SilverStripe\Forms\GridField\GridFieldConfig;
    use SilverStripe\Forms\GridField\GridFieldDeleteAction;
    use SilverStripe\Forms\GridField\GridFieldToolbarHeader;
    use SilverStripe\Forms\NumericField;
    use SilverStripe\Forms\TextField;
    use SilverStripe\ORM\DataList;
    use SilverStripe\ORM\FieldType\DBField;
    use Symbiote\GridFieldExtensions\GridFieldAddNewInlineButton;
    use Symbiote\GridFieldExtensions\GridFieldEditableColumns;
    use Symbiote\GridFieldExtensions\GridFieldOrderableRows;
    use Symbiote\GridFieldExtensions\GridFieldTitleHeader;
    use DateTime;

    class FilterableHolderExtension extends SiteTreeExtension
    {

        private static $managed_object_class = Page::class;
//	private static $managed_object_holder_id_field = "ParentID";
        private static $managed_object_date_field = "Created";
        private static $managed_object_date_sort = "DESC";

        private static $paginate_objects = 'true';
        private static $pagination_control_tab = "Root.Main";
        private static $pagination_insert_before = null;

        // moved to managed_object_class to allow filtering (data)objects without a relation to holder
//	private static $datearchive_active = true;
//	private static $tags_active = true;
//	private static $categories_active = true;

        static $db = [
            'DateArchivesTitle' => 'Varchar',
            'CategoriesTitle'   => 'Varchar',
            'TagsTitle'         => 'Varchar',
            'ItemsPerPage'      => 'Int',
            'ArchiveUnit'       => 'Enum("year, month, day")',
        ];

        private static $has_many = [
            "Tags"       => FilterableTag::class,
            "Categories" => FilterableCategory::class,
        ];

        public function getManagedClass()
        {
            return $this->owner->config()->managed_object_class;
        }

        // add fields to CMS
        public function updateCMSFields(FieldList $fields)
        {

            // check if the insertbefore field is present (may be added later, in which case the earlier
            // fields may never get added)
            $insertOnTab = $this->owner->config()->pagination_control_tab;
            $insertBefore = $this->owner->config()->pagination_insert_before;
            if ( !$fields->fieldByName("$insertOnTab.$insertBefore") ) {
                $insertBefore = null;
            }

//		if( $this->owner->config()->pagination_active ){
            if ( filter_var($this->owner->config()->paginate_objects, FILTER_VALIDATE_BOOLEAN) ) {
                $pagerField = NumericField::create("ItemsPerPage",
                    _t("filterablearchive.ItemsPerPage", "Pagination: items per page"))
                    ->setRightTitle(_t("filterablearchive.LeaveEmptyForNone",
                        "Leave empty or '0' for no pagination"));

                $fields->addFieldToTab(
                    $insertOnTab,
                    $pagerField,
                    $insertBefore
                );
            }

//		if($this->owner->getFilterableArchiveConfigValue('datearchive_active')){
//			$fields->addFieldToTab($this->owner->getFilterableArchiveConfigValue('pagination_control_tab'), 
//		if( $this->owner->config()->datearchive_active ){

            if ( filter_var(Config::inst()->get($this->owner->getManagedClass(), 'datearchive_active'), FILTER_VALIDATE_BOOLEAN) ) {
                $fields->addFieldToTab($insertOnTab, TextField::create('DateArchivesTitle'), $insertBefore);
                $fields->addFieldToTab($insertOnTab,
                    DropdownField::create('ArchiveUnit',
                        _t('filterablearchive.ARCHIVEUNIT', 'Archive unit'),
                        [
                            'year'  => _t('filterablearchive.YEAR', 'Year'),
                            'month' => _t('filterablearchive.MONTH', 'Month'),
                            'day'   => _t('filterablearchive.DAY', 'Day'),
                        ]), $insertBefore);
            }

            // Lets just use what others have made already...
            $config = GridFieldConfig::create()
                ->addComponent(new GridFieldButtonRow('before'))
                ->addComponent(new GridFieldToolbarHeader())
                ->addComponent(new GridFieldTitleHeader())
                ->addComponent(new GridFieldOrderableRows())
                ->addComponent(new GridFieldEditableColumns())
                ->addComponent(new GridFieldDeleteAction())
                ->addComponent(new GridFieldAddNewInlineButton('toolbar-header-right'));

//		if($this->owner->getFilterableArchiveConfigValue('categories_active')){
//		if($this->owner->config()->categories_active){
            if ( filter_var(Config::inst()->get($this->owner->getManagedClass(), 'categories_active'), FILTER_VALIDATE_BOOLEAN) ) {
                $fields->addFieldToTab($insertOnTab, TextField::create('CategoriesTitle'), $insertBefore);
                $fields->addFieldToTab($insertOnTab,
                    $categories = GridField::create(
                        "Categories",
                        _t("FilterableArchive.Categories", "Categories"),
                        $this->owner->Categories(),
                        $config
                    ), $insertBefore);
                $categories->setModelClass(FilterableCategory::class);
            }
//		if($this->owner->getFilterableArchiveConfigValue('tags_active')){
//		if($this->owner->config()->tags_active){
            if ( filter_var(Config::inst()->get($this->owner->getManagedClass(), 'tags_active'), FILTER_VALIDATE_BOOLEAN) ) {
                $fields->addFieldToTab($insertOnTab, TextField::create('TagsTitle'), $insertBefore);
                $fields->addFieldToTab($insertOnTab,
                    $tags = GridField::create(
                        "Tags",
                        _t("FilterableArchive.Tags", "Tags"),
                        $this->owner->Tags(),
                        $config
                    ), $insertBefore);
                $tags->setModelClass('Filterable_Tag');
            }

        }

        /**
         * Return unfiltered items
         * to be overridden in case managed object has no relation to holder
         *
         * @return DataList of managed_object_class
         **/
        public function getItems()
        {

            $class = $this->getManagedClass();
            $dateField = $this->owner->config()->managed_object_date_field;
            $dateSort = $this->owner->config()->managed_object_date_sort;
//		$HolderIDfield = $this->owner->config()->managed_object_holder_id_field;
            $Item_HolderField = Config::inst()->get($class, 'holder_relation_fieldname');

            $items = $class::get()->filter("{$Item_HolderField}ID", $this->owner->ID);
            if ( filter_var(Config::inst()->get($class, 'datearchive_active'), FILTER_VALIDATE_BOOLEAN) ) {
                $items = $items->sort("$dateField $dateSort");
            }

            //Allow pages to manipulate list, eg to use this to manage non SiteTree Items
            $items = $this->owner->updateGetItems($items);
            //Allow decorators to manipulate list, eg to use this to manage non SiteTree Items
            $this->owner->extend('updateGetItems', $items);

            return $items;

        }

        // stub, override from Extended page
        public function updateGetItems($items)
        {
            return $items;
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
        public function getFilteredArchiveItems($year, $month = null, $day = null, $items = null)
        {
//		$class = $this->owner->getFilterableArchiveConfigValue('managed_object_class');
//		$dateField = $this->owner->getFilterableArchiveConfigValue('managed_object_date_field');
            $class = $this->owner->config()->managed_object_class;
            $dateField = $this->owner->config()->managed_object_date_field;
            if ( !$items ) $items = $this->owner->getItems();

            // if Items = ArrayList (not DataList), filterby callback (cause ->where() doesn't exist on ArrayList)
            if ( get_class($items) === DataList::class ) {

                if ( $month ) {
                    if ( $day ) {
                        return $items
                            ->where("DAY({$dateField}) = '" . Convert::raw2sql($day) . "' 
								AND MONTH({$dateField}) = '" . Convert::raw2sql($month) . "'
								AND YEAR({$dateField}) = '" . Convert::raw2sql($year) . "'");
                    }

                    return $items
                        ->where("MONTH({$dateField}) = '" . Convert::raw2sql($month) . "'
							AND YEAR({$dateField}) = '" . Convert::raw2sql($year) . "'");
                } else {
                    return $items->where("YEAR({$dateField}) = '" . Convert::raw2sql($year) . "'");
                }

                // ArrayList, filter by callback
            } else {

                if ( $month ) {
                    if ( $day ) {
                        return $items->filterByCallback(
                            function ($item, $list) use ($dateField, $year, $month, $day) {
                                $date = new DateTime($item->{$dateField});

                                return ( $date->format('d') === $day
                                    && $date->format('m') === $month && $date->format('Y') === $year );
                            }
                        );
                    }

                    return $this->getItems()->filterByCallback(
                        function ($item, $list) use ($dateField, $year, $month, $day) {
                            $date = new DateTime($item->{$dateField});

                            return ( $date->format('m') === $month && $date->format('Y') === $year );
                        }
                    );
                }

                return $this->getItems()->filterByCallback(
                    function ($item, $list) use ($dateField, $year, $month, $day) {
                        $date = new DateTime($item->{$dateField});

                        return ( $date->format('Y') === "$year" );
                    }
                );

            }
        }

        public function getItemsFilteredByCatOrTag($catOrTagObj, $items = null)
        {
            $class = $this->owner->config()->managed_object_class;
            if ( !$items ) $items = $this->owner->getItems();

            // if Items = ArrayList (not DataList), filterby callback (cause ->where() doesn't exist on ArrayList)
            // TMP fix: somehow FW insists on filtering on 'DataObject' instead of going through many_many() for the correct class...
            if ( get_class($items) === DataList::class ) {
//            var_dump($catOrTagObj->many_many('Items'));die();
                $IDs = $catOrTagObj->Items()->column('ID');
//            $IDs = $catOrTagObj->getManyManyComponents('Items')->column('ID');
//            var_dump($IDs);die();
                return $items->filter('ID', $IDs);

                // ArrayList, filter by callback ($catOrTag has to have an ID for this to work)
            } else {
                return $items->filterByCallback(
                    function ($item, $list) use ($catOrTagObj) {
                        if ( get_class($catOrTagObj) === FilterableCategory::class ) {
                            $hasToBeIn = $item->Categories()->column('ID');
                        } else {
                            $hasToBeIn = $item->Tags()->column('ID');
                        }

                        return in_array($catOrTagObj->ID, $hasToBeIn);
                    });
            }
        }

        //
        // Dropdowns for available archiveitems
        //
        public function ArchiveUnitDropdown()
        {

            $months = [];
            $months[ '1' ] = _t('filterablearchive.JANUARY', 'Januari');
            $months[ '2' ] = _t('filterablearchive.FEBRUARY', 'Februari');
            $months[ '3' ] = _t('filterablearchive.MARCH', 'Maart');
            $months[ '4' ] = _t('filterablearchive.APRIL', 'April');
            $months[ '5' ] = _t('filterablearchive.MAY', 'Mei');
            $months[ '6' ] = _t('filterablearchive.JUNE', 'Juni');
            $months[ '7' ] = _t('filterablearchive.JULY', 'Juli');
            $months[ '8' ] = _t('filterablearchive.AUGUST', 'Augustus');
            $months[ '9' ] = _t('filterablearchive.SEPTEMBER', 'September');
            $months[ '10' ] = _t('filterablearchive.OCTOBER', 'Oktober');
            $months[ '11' ] = _t('filterablearchive.NOVEMBER', 'November');
            $months[ '12' ] = _t('filterablearchive.DECEMBER', 'December');

            // build array with available archive 'units'
            $items = $this->owner->getItems();
//		$dateField = $this->owner->getFilterableArchiveConfigValue('managed_object_date_field');
            $dateField = $this->owner->config()->managed_object_date_field;
            $itemArr = [];
            foreach ( $items as $item ) {
                if ( !$item->$dateField ) {
                    continue;
                }
                $dateObj = DBField::create_field('Date', strtotime($item->$dateField));
                // add month if not yet in array;
                if ( $this->owner->ArchiveUnit == 'day' ) {
                    $arrkey = $dateObj->Format('Y/m/d/');
                    $arrval = $dateObj->Format('d ') . $months[ $dateObj->Format('n') ] . $dateObj->Format(' Y');
                } elseif ( $this->owner->ArchiveUnit == 'month' ) {
                    $arrkey = $dateObj->Format('Y/m/');
                    $arrval = $months[ $dateObj->Format('n') ] . $dateObj->Format(' Y');
                } else {
                    $arrkey = $dateObj->Format('Y/');
                    $arrval = $dateObj->Format('Y');
                }
                if ( !array_key_exists($arrkey, $itemArr) ) {
                    $itemArr[ $arrkey ] = $arrval;
                }
            }

            $DrDown = new DropdownField('archiveunits', '', $itemArr);
            $DrDown->setEmptyString(_t('filterablearchive.FILTER', 'Filter items'));
            $DrDown->addExtraClass("dropdown form-control");

            // specific to the 'archive' action defined by FilterableArchiveHolder_ControllerExtension (if available)
            $ctrl = Controller::curr();
            $activeUnit = "";
            if ( $ctrl::has_extension("FilterableArchiveHolder_ControllerExtension") ) {
                if ( $cYear = $ctrl->getArchiveYear() ) $activeUnit .= "$cYear/";
                if ( $cMonth = $ctrl->getArchiveMonth() ) $activeUnit .= str_pad("$cMonth/", 3, "0", STR_PAD_LEFT);
                if ( $cDay = $ctrl->getArchiveDay() ) $activeUnit .= str_pad("$cDay/", 3, "0", STR_PAD_LEFT);
            }
            $DrDown->setValue($activeUnit);

            // again, tie this to the 'archive' action;
            $DrDown->setAttribute('onchange', "location = '{$this->owner->AbsoluteLink()}date/'+this.value;");

            return $DrDown;
        }

    }

}