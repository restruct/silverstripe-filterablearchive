<?php

namespace Restruct\SilverStripe\FilterableArchive {

    use SilverStripe\CMS\Model\SiteTree;
    use SilverStripe\Control\Controller;
    use SilverStripe\Core\Config\Config;
    use SilverStripe\Forms\FieldList;
    use SilverStripe\Forms\TextField;
    use SilverStripe\ORM\DataObject;
    use SilverStripe\Security\Member;
    use SilverStripe\View\Parsers\URLSegmentFilter;

    class FilterableTag extends DataObject
    {
        /**
         * @var string
         */
        private static $table_name = 'Filterable_Tag';

        /**
         * @var string[]
         */
        private static $db = [
            "Title"      => "Varchar(255)",
            'URLSegment' => 'Varchar(255)',
            'Sort'       => 'Int',
        ];

        private static $has_one = [
            "HolderPage" => SiteTree::class,
        ];

        private static $many_many = [
            //"Items" => DataObject::class,
        ];

        private static $default_sort = "Title";

        public function getCMSFields()
        {
            $fields = new FieldList(
                TextField::create("Title", _t("FilterableArchive.CategoryTitle", "Category"))
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

            // merge duplicates
            $this->mergeDuplicates();

        }

        // override many_many to give back correct Class columns (instead of 'DataObject')
        public function many_many($component = null)
        {
            if ( $component === "Items" ) {
                // many_many gets called multiple times, sometimes $this has no ID/HolderPageID
                $itemsclass = 'SiteTree'; // fallback table which is certain to exist
                if ( $this->HolderPageID ) {
                    $itemsclass = Config::inst()->get(get_class($this->HolderPage()), 'managed_object_class');
                }
                // array($class, $candidate, $parentField, $childField, "{$class}_$component")
                // "Filterable_Category, DataObject, Filterable_CategoryID, DataObjectID, Filterable_Category_Items"
                return [ "Filterable_Tag", "$itemsclass", "Filterable_TagID", "DataObjectID", "Filterable_Tag_Items" ];
            } // else handoff to parent...

            return parent::many_many($component);
        }

        // we cannot query a relation to DataObject (no db column), so we need to query the managed classname
        public function Items()
        {
            $itemsclass = Config::inst()->get(get_class($this->HolderPage()), 'managed_object_class');

            return $itemsclass::get()->filter('Tags.ID', $this->ID);
//		return ManyManyList::create($class);
        }

        public function getTitleAndHolder()
        {
            if ( $this->relField('HolderPage.BreadCrumbPath') ) {
                return "{$this->Title} ({$this->relField('HolderPage.BreadCrumbPath')})";
            } else {
                return "{$this->Title} ({$this->relField('HolderPage.MenuTitle')})";
            }
        }

        // Some cleaning up
        public function mergeDuplicates()
        {
            // if not grouped by a holderpage, don't enforce uniqueness
            if ( !$this->HolderPageID ) return;

            $this->Title = trim($this->Title); // strip spaces
            // if multiple with same title & same parentID, combine in this one & remove duplicates;
            $sameTitleTag = self::get()->filter([
                    'Title'        => $this->Title,
                    'HolderPageID' => $this->HolderPageID ]
            )->exclude('ID', $this->ID);
            if ( $sameTitleTag->count() ) { //only if editing existing (ID is set)
                foreach ( $sameTitleTag as $duplicate ) {
//				debug::dump($duplicate->Items());
                    foreach ( $duplicate->Items() as $item ) {
                        //add each item to this cat/tag
                        $this->Items()->add($item);
                    }
                    $duplicate->delete();
                }
            }
        }

        // Method to instantiate one without creating duplicates
        static function findOrCreate($title, $holderID = null)
        {
            if ( $found = self::get()->filter([
                'Title'        => trim($title),
                'HolderPageID' => $holderID,
            ])->first() ) {
                return $found;
            } else {
                $new = self::create();
                $new->Title = $title;
                $new->write();

                return $new;
            }
        }

        /**
         * Returns a relative URL for the tag link
         *
         * @return string URL
         **/
        public function getLink()
        {
            return Controller::join_links($this->HolderPage()->Link(), "tag", $this->URLSegment);
        }

        /**
         * Can be overwritten using a DataExtension
         *
         * @param $member Member
         *
         * @return boolean
         */
        public function canView($member = null)
        {
            $extended = $this->extendedCan(__FUNCTION__, $member);
            if ( $extended !== null ) {
                return $extended;
            }

            return true;
        }

        /**
         * @param null  $member
         * @param array $context
         *
         * @return bool
         */
        public function canCreate($member = null, $context = [])
        {
            $extended = $this->extendedCan(__FUNCTION__, $member);
            if ( $extended !== null ) {
                return $extended;
            }

            return true;
        }

        /**
         * Can be overwritten using a DataExtension
         *
         * @param $member Member
         *
         * @return boolean
         */
        public function canDelete($member = null)
        {
            $extended = $this->extendedCan(__FUNCTION__, $member);
            if ( $extended !== null ) {
                return $extended;
            }

            return true;
        }

        /**
         * Can be overwritten using a DataExtension
         *
         * @param null  $member
         * @param array $context
         *
         * @return bool
         */
        public function canEdit($member = null, $context = [])
        {
            $extended = $this->extendedCan(__FUNCTION__, $member);
            if ( $extended !== null ) {
                return $extended;
            }

            return true;
        }

    }
}