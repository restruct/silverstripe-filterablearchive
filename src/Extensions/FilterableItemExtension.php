<?php

namespace Restruct\SilverStripe\FilterableArchive\Extensions {

    use Restruct\FilterableArchive\FilterableCategory;
    use Restruct\FilterableArchive\FilterableTag;
    use SilverStripe\Control\Controller;
    use SilverStripe\Control\HTTPRequest;
    use SilverStripe\Core\Injector\Injector;
    use SilverStripe\Forms\FieldList;
    use SilverStripe\Forms\ListboxField;
    use SilverStripe\ORM\DataExtension;
    use SilverStripe\ORM\DataObject;
    use SilverStripe\TagField\TagField;

    class FilterableItemExtension extends DataExtension
    {

        // the name of the relation to the filterablearchive parent/holder
        private static $fields_insert_on_tab = "Root.Main";
        private static $fields_insert_before = "Content";
        private static $holder_relation_fieldname = "Parent";

        /**
         * @config
         * Strings for now, since the config system will not override booleans with false-ish values
         */
        private static $datearchive_active = 'true';
        private static $tags_active = 'true';
        private static $tags_fieldtype = ListboxField::class; //ListboxField or TagField
        private static $categories_active = 'true';
        private static $categories_fieldtype = ListboxField::class; //ListboxField or TagField

        private static $belongs_many_many = [
            "Tags"       => FilterableTag::class,
            "Categories" => FilterableCategory::class,
        ];

        // to be overridden if holder is not returned by Parent()
        public function FilteringHolder()
        {
            $holdermethod = $this->owner->config()->holder_relation_fieldname;
//		if(is_callable(array($this->owner, $holdermethod))){
            // SiteTree items will have an ID upon being created, just call holder method (usually Parent())
            if ( $this->owner->ID && $this->owner->hasMethod($holdermethod) ) {
                return $this->owner->$holdermethod();
            } else {
                // if no ID (eg DataObject in GFdetailform) try & get from session
                // for non-SiteTree parents this might need some more work, see:
                // silverstripe.org/community/forums/data-model-questions/show/21517 , and:
                // bigfork.co.uk/takeaway/silverstripe-tip-using-unsaved-relations-in-gridfield-edit-forms
                $request = Injector::inst()->get(HTTPRequest::class);
                $session = $request->getSession();
                $cmsMainSessionVal = $session->get('CMSMain');
                if ( $cmsMainSessionVal
                    && array_key_exists('currentPage', $cmsMainSessionVal) ) {
                    $parent = DataObject::get_by_id('SiteTree', (int)$cmsMainSessionVal[ 'currentPage' ]);
                    if ( $parent && $parent->exists() ) {
                        return $parent;
                    }
                }
            }

            return false;
        }

        public function FilterableAvailable($tagsOrCats)
        {
            if ( !$holder = $this->FilteringHolder() ) return false;
            if ( $tagsOrCats === 'Categories' ) {
                return FilterableCategory::get()->filter('HolderPageID', $holder->ID);
            }
            if ( $holder->hasMethod($tagsOrCats) ) {
                return $holder->$tagsOrCats();
            }

            return false;
        }

        public function updateCMSFields(FieldList $fields)
        {
            //parent::updateCMSFields($fields);

//		$availabletags = $this->owner->relField( $this->owner->config()->available_tags_method );
            $availabletags = $this->owner->FilterableAvailable('Tags');
            $availablecats = $this->owner->FilterableAvailable('Categories');

            // remove auto-scaffolded fields (on DataObjects)
            $fields->removeByName([ 'Tags', 'Categories' ]);

            $insertOnTab = $this->owner->config()->fields_insert_on_tab;
            $insertBefore = $this->owner->config()->fields_insert_before;

            if ( !$fields->fieldByName("$insertOnTab.$insertBefore") ) {
                $insertBefore = null;
            }

            // Add Categories & Tags fields
//		if(method_exists($this->owner->Parent(), 'getFilterableArchiveConfigValue')
//				&& $this->owner->Parent()->getFilterableArchiveConfigValue('categories_active')){
            if ( filter_var($this->owner->config()->categories_active, FILTER_VALIDATE_BOOLEAN) ) {
                // add field
                $cat_field_type = $this->owner->config()->categories_fieldtype;
                // Tagfield or listboxfield
                if ( $cat_field_type === TagField::class ) {
                    $categoriesField = $cat_field_type::create(
                        'Categories',
                        _t("FilterableArchive.Categories", "Categories"),
                        // prevent calling ->map() on UnsavedRelationList when creating new DataObject-item:
                        ( $availablecats ? $availablecats->map()->toArray() : [] )
                    )
//				->setShouldLazyLoad(true) // tags should be lazy loaded
                        ->setCanCreate(true);     // new tags can be created inline
                } else { // ListboxField
                    $categoriesField = $cat_field_type::create(
                        'Categories',
                        _t("FilterableArchive.Categories", "Categories"),
                        // prevent calling ->map() on UnsavedRelationList when creating new DataObject-item:
                        ( $availablecats ? $availablecats->map()->toArray() : [] )
                    );//->setMultiple(true);
                }
//			$fields->insertBefore($categoriesField, $insertBefore);
                $fields->addFieldToTab($insertOnTab, $categoriesField, $insertBefore);
            }

//		if(method_exists($this->owner->Parent(), 'getFilterableArchiveConfigValue')
//				&& $this->owner->Parent()->getFilterableArchiveConfigValue('tags_active')){
            if ( filter_var($this->owner->config()->tags_active, FILTER_VALIDATE_BOOLEAN) ) {
                // add field
                $tags_field_type = $this->owner->config()->tags_fieldtype;
                // Tagfield or listboxfield
                if ( $tags_field_type === TagField::class ) {
                    $tagsField = $tags_field_type::create(
                        'Tags',
                        _t("FilterableArchive.Tags", "Tags"),
                        // prevent calling ->map() on UnsavedRelationList when creating new DataObject-item:
                        ( $availabletags ? $availabletags->map()->toArray() : [] )
                    )
//				->setShouldLazyLoad(true) // tags should be lazy loaded
                        ->setCanCreate(true);     // new tags can be created inline
                } else { // ListboxField
                    $tagsField = $tags_field_type::create(
                        'Tags',
                        _t("FilterableArchive.Tags", "Tags"),
                        // prevent calling ->map() on UnsavedRelationList when creating new DataObject-item:
                        ( $availabletags ? $availabletags->map()->toArray() : [] )
                    )
                        ->setMultiple(true);
                }
//			$fields->insertBefore($tagsField, $insertBefore);
                $fields->addFieldToTab($insertOnTab, $tagsField, $insertBefore);
            }

        }

        public function onAfterWrite()
        {
            parent::onAfterWrite();

            // if any new cats/tags added via TagField, add them to the parent/holder
            // as well, so they will be available to the tagfield on reload/after save
            $holder = $this->FilteringHolder();
            // add holderID to cats
            $cat_field_type = $this->owner->config()->categories_fieldtype;
            if ( $cat_field_type === TagField::class ) {
                foreach ( $this->owner->Categories()->filter('HolderPageID', '') as $cat ) {
                    $cat->HolderPageID = $holder->ID;
                    $cat->write();
                }
            }
            // add holderID to tags
            $tags_field_type = $this->owner->config()->tags_fieldtype;
            if ( $tags_field_type === TagField::class ) {
                foreach ( $this->owner->Tags()->filter('HolderPageID', '') as $tag ) {
                    $tag->HolderPageID = $holder->ID;
                    $tag->write();
                }
            }

        }

        /**
         * Returns a monthly archive link for the current item.
         *
         * @param $type string day|month|year
         *
         * @return string URL
         **/
        public function getArchiveLink($archiveunit = false)
        {

            if ( !$archiveunit ) $archiveunit = $this->owner->FilteringHolder()->ArchiveUnit;
            if ( !$archiveunit ) $archiveunit = 'month'; // default
//		$datefield = $this->owner->Parent()->getFilterableArchiveConfigValue('managed_object_date_field');
            $datefield = $this->owner->FilteringHolder()->config()->managed_object_date_field;
            $date = $this->owner->dbObject($datefield);
            if ( $archiveunit === "month" ) {
                return Controller::join_links($this->owner->FilteringHolder()->Link("date"),
                        $date->format("Y"), $date->format("m")) . "/";
            }
            if ( $archiveunit === "day" ) {
                return Controller::join_links(
                        $this->owner->FilteringHolder()->Link("date"),
                        $date->format("Y"),
                        $date->format("m"),
                        $date->format("d")
                    ) . "/";
            }

            return Controller::join_links($this->owner->FilteringHolder()->Link("date"), $date->format("Y")) . "/";
        }

        /**
         * Returns a yearly archive link for the current item.
         *
         * @return string URL
         **/
        public function getYearArchiveLink()
        {

//		$datefield = $this->owner->Parent()->getFilterableArchiveConfigValue('managed_object_date_field');
            $datefield = $this->owner->FilteringHolder()->config()->managed_object_date_field;
            $date = $this->dbObject($datefield);

            return Controller::join_links($this->owner->FilteringHolder()->Link("date"), $date->format("Y"));
        }

    }
}