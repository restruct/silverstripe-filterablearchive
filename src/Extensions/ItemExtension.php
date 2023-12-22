<?php

namespace Restruct\SilverStripe\FilterableArchive\Extensions;

use Restruct\SilverStripe\FilterableArchive\FilterPropRelation;
use SilverStripe\CMS\Model\SiteTreeExtension;
use SilverStripe\Dev\Debug;
use SilverStripe\Forms\DateField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Core\Config\Config;
use SilverStripe\TagField\TagField;
use SilverStripe\Control\Controller;

class ItemExtension extends SiteTreeExtension
{
    /** @config string|null|bool what Date/DateTime field to use for filtering by date ( */
    private static $field_for_date_filter = 'Created';

    // This same many_many may also exist on other classes
    private static $many_many = [
        "Categories" => [
            'through' => FilterPropRelation::class,
            'from' => 'Item',
            'to' => 'Category',
        ],
        "Tags" => [
            'through' => FilterPropRelation::class,
            'from' => 'Item',
            'to' => 'Tag',
        ],
    ];

    public function updateCMSFields(FieldList $fields)
    {
        parent::updateCMSFields($fields);

        // Add Date field (if date archive active AND not using Created or LastUpdated)
        $dateFieldName = Config::inst()->get($this->owner->Parent()->className, 'field_for_date_filter');
        if (Config::inst()->get($this->owner->Parent()->className, 'datearchive_active')
                && $dateFieldName && ! in_array($dateFieldName, ['Created', 'LastEdited'])
        ) {
            $dateField = DateField::create($dateFieldName);
            //$dateField->setConfig('dateformat', 'dd-MM-yyyy'); // global setting
//            $dateField->setConfig('showcalendar', 1); // field-specific setting
            $fields->addFieldToTab("Root.Main", $dateField, 'FeaturedImages');
            $fields->insertbefore("Content", $dateField);
        }

        // Add Categories field
        if (Config::inst()->get($this->owner->Parent()->className, 'categories_active')) {
            // Use tagfield instead (allows inline creation)
            $availableCats = $this->owner->Parent()->Categories();
            $categoriesField = new TagField(
                'Categories',
                _t("FilterableArchive.Categories", "Categories"),
                $availableCats,
                $this->owner->Categories()
            );
            //$categoriesField->setShouldLazyLoad(true); // tags should be lazy loaded (nope, gets all instead of just the parent's cats/tags)
            $categoriesField->setCanCreate(true); // new tag DataObjects can be created (@TODO check privileges)
            $fields->insertbefore("Content", $categoriesField);
        }

        // Add Categories field
        if (Config::inst()->get($this->owner->Parent()->className, 'tags_active')) {
            // Use tagfield instead (allows inline creation)
            $availableTags = $this->owner->Parent()->Tags();
            $tagsField = new TagField(
                'Tags',
                _t("FilterableArchive.Tags", "Tags"),
                $availableTags,
                $this->owner->Tags()
            );
            //$tagsField->setShouldLazyLoad(true); // tags should be lazy loaded (nope, gets all instead of just the parent's cats/tags)
            $tagsField->setCanCreate(true); // new tag DataObjects can be created (@TODO check privileges)
            $fields->insertbefore("Content", $tagsField);
        }
    }

//    /**
//     * Returns a monthly archive link for the current item.
//     *
//     * @param $type string day|month|year
//     *
//     * @return string URL
//    **/
//    public function getArchiveLink($archiveunit = false)
//    {
//        if (!$archiveunit) {
//            $archiveunit = $this->owner->Parent()->ArchiveUnit;
//        }
//        if (!$archiveunit) {
//            $archiveunit = 'month';
//        } // default
//        //$datefield = $this->owner->Parent()->getFilterableArchiveConfigValue('managed_object_date_field');
//        $datefield = Config::inst()->get($this->owner->Parent()->className, 'managed_object_date_field');
//        $date = $this->owner->dbObject($datefield);
//        if ($archiveunit == "month") {
//            return Controller::join_links($this->owner->Parent()->Link("date"),
//                $date->format("Y").'-'.$date->format("m"))."/";
//        }
//        if ($archiveunit == "day") {
//            return Controller::join_links(
//                $this->owner->Parent()->Link("date"),
//                $date->format("Y").'-'.$date->format("m").'-'.$date->format("d")
//            )."/";
//        }
//        return Controller::join_links($this->owner->Parent()->Link("date"), $date->format("Y"))."/";
//    }

//    /**
//     * Returns a yearly archive link for the current item.
//     *
//     * @return string URL
//    **/
//    public function getYearArchiveLink()
//    {
//        //$datefield = $this->owner->Parent()->getFilterableArchiveConfigValue('managed_object_date_field');
//        $datefield = Config::inst()->get($this->owner->Parent()->className, 'managed_object_date_field');
//        $date = $this->dbObject($datefield);
//        return Controller::join_links($this->owner->Parent()->Link("date"), $date->format("Y"));
//    }
}
