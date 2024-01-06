<?php

namespace Restruct\SilverStripe\FilterableArchive\Extensions;

use Restruct\SilverStripe\FilterableArchive\FilterPropRelation;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\CMS\Model\SiteTreeExtension;
use SilverStripe\Dev\Debug;
use SilverStripe\Forms\DateField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Core\Config\Config;
use SilverStripe\ORM\ArrayList;
use SilverStripe\TagField\TagField;
use SilverStripe\Control\Controller;

class ItemExtension
    extends SiteTreeExtension
{
//    /** @config string|null|bool what Date/DateTime field to use for filtering by date ( */
//    private static $field_for_date_filter = 'Created';

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
        $HolderPage = $this->getHolderPage();

        // Add Date field (if date archive active AND not using Created or LastUpdated)
//        $dateFieldName = Config::inst()->get($HolderPage->ClassName, 'managed_object_date_field');
        $dateFieldName = $this->getDateField()->getName();
        if (    $HolderPage && $HolderPage->ArchiveActive()
                && $dateFieldName && ! in_array($dateFieldName, ['Created', 'LastEdited'])
        ) {
            $dateField = DateField::create($dateFieldName);
            //$dateField->setConfig('dateformat', 'dd-MM-yyyy'); // global setting
//            $dateField->setConfig('showcalendar', 1); // field-specific setting
//            $fields->addFieldToTab("Root.Main", $dateField, 'FeaturedImages');
            $fields->insertbefore("Content", $dateField);
        }

        // Add Categories field
        if ($HolderPage && $HolderPage->CategoriesActive()) {
            // Use tagfield instead (allows inline creation)
            $availableCats = $this->getHolderPage()->Categories();
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
        if ($HolderPage && $HolderPage->TagsActive()) {
            // Use tagfield instead (allows inline creation)
            $availableTags = $this->getHolderPage()->Tags();
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

    public function getHolderPage()
    {
        /** @var SiteTree $Parent */
        while($Parent = $this->owner->Parent()) {
            if ($Parent->hasExtension(HolderExtension::class)) {
                return $Parent;
            }
        }

        return null;
    }

    public function getDateField()
    {
        if($Holder = $this->owner->getHolderPage()){
            $datefield = Config::inst()->get($Holder->className, 'managed_object_date_field');
            return $this->owner->dbObject($datefield);
        }
        return null;
    }

    public function getRelatedItems()
    {
        $Related = ArrayList::create();
        $HolderPage = $this->getHolderPage();

        // First by tags (= cross connections), then by category (= same type of items)
        if($HolderPage->TagsActive()) foreach ($this->owner->Tags() as $Tag){
            $Related->merge( $Tag->Items()->exclude('ID', $this->owner->ID) );
        }
        if($HolderPage->CategoriesActive()) foreach ($this->owner->Categories() as $Cat){
            $Related->merge( $Cat->Items()->exclude('ID', $this->owner->ID) );
        }

        return $Related;
    }
}
