<?php

namespace Restruct\SilverStripe\FilterableArchive\Extensions;

use Restruct\SilverStripe\FilterableArchive\FilterPropRelation;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Core\Extension;
use SilverStripe\Forms\DateField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Core\Config\Config;
use SilverStripe\TagField\TagField;
# ArrayList is NOT imported: it moved from SilverStripe\ORM (5) to SilverStripe\Model\List (6)
# with no alias left behind, so the class name is resolved per major - see arrayListClass().

class ItemExtension extends Extension
{
    // This same many_many may also exist on other classes
    private static $many_many = [
        "Categories" => [
            'through' => FilterPropRelation::class,
            'from'    => 'Item',
            'to'      => 'Category',
        ],
        "Tags"       => [
            'through' => FilterPropRelation::class,
            'from'    => 'Item',
            'to'      => 'Tag',
        ],
    ];

    /**
     * Silverstripe 6 scaffolds SiteTree's CMS fields, relations from extensions included.
     * updateCMSFields() below adds these two as TagFields only while the holder has the filter
     * switched on, so a scaffolded copy must not show up while it is off. Inert for SiteTree owners
     * on SS5 (SS5 SiteTree hand-builds its fields); it DOES apply on SS5 to a non-SiteTree owner.
     */
    private static $scaffold_cms_fields_settings = [
        'ignoreRelations' => [
            'Categories',
            'Tags',
        ],
    ];

    public function updateCMSFields(FieldList $fields)
    {
        $HolderPage = $this->getHolderPage();

        // Add Date field (if date archive active AND not using Created or LastUpdated)
        # getDateField() is null without a holder above this item, so do not dereference it blindly
        $dbDateField = $this->getDateField();
        $dateFieldName = $dbDateField ? $dbDateField->getName() : null;
        if ($HolderPage && $HolderPage->ArchiveActive()
            && $dateFieldName && !in_array($dateFieldName, ['Created', 'LastEdited'])
        ) {
//            $dateField = DateField::create($dateFieldName);
            # The db field's own form field: a DatetimeField for a Datetime, so saving keeps the time
            # (a plain DateField reset it to 00:00:00 on every save); DateField as the fallback
            $dateField = $dbDateField->scaffoldFormField() ?: DateField::create($dateFieldName);
            $fields->insertbefore("Content", $dateField);
        }

        // Add Categories field
        if ($HolderPage && $HolderPage->CategoriesActive()) {
            $fields->removeByName("Categories");
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
            $fields->removeByName("Tags");
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
        # Walk UP the tree: the loop used to re-read the same Parent() on every pass, so an item whose
        # parent was not a holder never returned. Parent() of a root page is an empty, unsaved record.
        /** @var SiteTree $Parent */
        $Parent = $this->owner->Parent();
        while ($Parent && $Parent->exists()) {
            if ($Parent->hasExtension(HolderExtension::class)) {
                return $Parent;
            }
            $Parent = $Parent->Parent();
        }

        return null;
    }

    public function getDateField()
    {
        if ($Holder = $this->owner->getHolderPage()) {
            $datefield = Config::inst()->get($Holder->className, 'managed_object_date_field');
            return $this->owner->dbObject($datefield);
        }

        return null;
    }

    public function getRelatedItems()
    {
        $listClass = $this->arrayListClass();
        $Related = $listClass::create();
        $HolderPage = $this->getHolderPage();
        # No holder above this item: no filters, so nothing to relate by
        if (!$HolderPage) {
            return $Related;
        }

        // First by tags (= cross connections), then by category (= same type of items)
        if ($HolderPage->TagsActive()) {
            foreach ($this->owner->Tags() as $Tag) {
                $Related->merge($Tag->Items()->exclude('ID', $this->owner->ID));
            }
        }

        if ($HolderPage->CategoriesActive()) {
            foreach ($this->owner->Categories() as $Cat) {
                $Related->merge($Cat->Items()->exclude('ID', $this->owner->ID));
            }
        }

        # An item sharing a tag AND a category (or two tags) with this one was listed once per link
        $Related->removeDuplicates('ID');

        return $Related;
    }

    /**
     * ArrayList class for the running Silverstripe major (moved to SilverStripe\Model\List in 6).
     *
     * @return string
     */
    protected function arrayListClass()
    {
        return class_exists('SilverStripe\\Model\\List\\ArrayList')
            ? 'SilverStripe\\Model\\List\\ArrayList'
            : 'SilverStripe\\ORM\\ArrayList';
    }
}
