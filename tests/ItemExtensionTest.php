<?php

namespace Restruct\SilverStripe\FilterableArchive\Tests;

use Restruct\SilverStripe\FilterableArchive\Extensions\HolderControllerExtension;
use Restruct\SilverStripe\FilterableArchive\Extensions\HolderExtension;
use Restruct\SilverStripe\FilterableArchive\Extensions\ItemExtension;
use Restruct\SilverStripe\FilterableArchive\FilterProp;
use Restruct\SilverStripe\FilterableArchive\FilterPropRelation;
use Restruct\SilverStripe\FilterableArchive\Tests\Stub\FAHolder;
use Restruct\SilverStripe\FilterableArchive\Tests\Stub\FAHolderController;
use Restruct\SilverStripe\FilterableArchive\Tests\Stub\FAItem;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Core\Config\Config;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Forms\DateField;
use SilverStripe\Forms\DatetimeField;
use SilverStripe\TagField\TagField;

/**
 * Behavioural tests for ItemExtension: the categories/tags relation, finding the holder,
 * the CMS fields it contributes and the related-items list.
 *
 * Compatibility note: runs under PHPUnit 9 (Silverstripe 5) and PHPUnit 11 (Silverstripe 6);
 * no doc-comment metadata, no assertions removed after PHPUnit 9.
 */
class ItemExtensionTest extends SapphireTest
{
    use BuildsArchive;

    protected $usesDatabase = true;

    protected static $extra_dataobjects = [
        FAHolder::class,
        FAItem::class,
    ];

    protected static $required_extensions = [
        FAHolder::class => [HolderExtension::class],
        FAHolderController::class => [HolderControllerExtension::class],
        FAItem::class => [ItemExtension::class],
    ];

    # ---------------------------------------------------------------- relation

    public function testCategoriesAndTagsAreStoredThroughFilterPropRelation()
    {
        $this->buildArchive();
        $item = $this->items['may2024'];

        $this->assertSame(['press'], $item->Categories()->column('URLSegment'));
        $this->assertEqualsCanonicalizing(['blue', 'green'], $item->Tags()->column('URLSegment'));

        # One join row per link, with the item recorded polymorphically
        $rows = FilterPropRelation::get()->filter('ItemID', $item->ID);
        $this->assertSame(3, $rows->count());
        $this->assertSame([FAItem::class], array_unique($rows->column('ItemClass')));

        $item->Tags()->remove($this->props['green']);
        $this->assertSame(['blue'], $item->Tags()->column('URLSegment'));
    }

    public function testFilterPropItemsListsTheLinkedPages()
    {
        $this->buildArchive();
        $this->assertEqualsCanonicalizing(['jan2023', 'may2024'], $this->props['blue']->Items()->column('Title'));
        $this->assertSame(['may2024'], $this->props['press']->Items()->column('Title'));

        $unused = FilterProp::create(['Title' => 'Unused', 'TagHolderPageID' => $this->holder->ID]);
        $unused->write();
        $this->assertSame(0, $unused->Items()->count());
    }

    # ---------------------------------------------------------------- holder lookup

    public function testHolderPageIsTheParentHolder()
    {
        $this->buildArchive();
        $this->assertSame($this->holder->ID, $this->items['jan2023']->getHolderPage()->ID);
    }

    public function testHolderPageIsFoundFurtherUpTheTree()
    {
        $this->buildArchive();
        $folder = SiteTree::create(['Title' => 'Folder', 'ParentID' => $this->holder->ID]);
        $folder->write();
        $nested = FAItem::create(['Title' => 'nested', 'ParentID' => $folder->ID]);
        $nested->write();

        $this->assertSame($this->holder->ID, $nested->getHolderPage()->ID);
    }

    /**
     * Regression: getHolderPage() re-read the SAME parent on every pass of its loop, so an
     * item whose parent was not a holder (or which had no parent) never returned: the request,
     * and with it the CMS edit form, hung until PHP's time limit.
     */
    public function testHolderPageIsNullWithoutAHolderAboveIt()
    {
        $plainParent = SiteTree::create(['Title' => 'Plain']);
        $plainParent->write();
        $orphan = FAItem::create(['Title' => 'orphan', 'ParentID' => $plainParent->ID]);
        $orphan->write();
        $root = FAItem::create(['Title' => 'root']);
        $root->write();

        $this->assertNull($orphan->getHolderPage());
        $this->assertNull($root->getHolderPage());
    }

    public function testDateFieldIsTheHoldersConfiguredField()
    {
        $this->buildArchive();
        $dateField = $this->items['jan2023']->getDateField();
        $this->assertSame('Date', $dateField->getName());
        $this->assertSame('2023-01-15 10:00:00', $dateField->getValue());

        Config::modify()->set(FAHolder::class, 'managed_object_date_field', 'Created');
        $this->assertSame('Created', $this->items['jan2023']->getDateField()->getName());

        $root = FAItem::create(['Title' => 'root']);
        $root->write();
        $this->assertNull($root->getDateField());
    }

    # ---------------------------------------------------------------- CMS fields

    public function testCmsFieldsOfAnItemUnderAnActiveHolder()
    {
        $this->buildArchive();
        $fields = $this->items['may2024']->getCMSFields();

        # Date is a Datetime on the stub, so it is edited with a DatetimeField (see the regression test below)
        $this->assertInstanceOf(DatetimeField::class, $fields->dataFieldByName('Date'));

        foreach (['Categories' => ['press'], 'Tags' => ['blue', 'green']] as $name => $selected) {
            $field = $fields->dataFieldByName($name);
            $this->assertInstanceOf(TagField::class, $field, "$name is edited with a TagField");
            $this->assertTrue($field->getCanCreate(), "$name can be created inline");
            # Offers only this holder's own categories/tags
            $this->assertSame(2, $field->getSourceList()->count());
            $this->assertSame($this->holder->ID, (int) $field->getSourceList()->first()->{$name === 'Categories' ? 'CatHolderPageID' : 'TagHolderPageID'});
        }

        # Placed before Content, in that order: Date, Categories, Tags, Content
        $names = array_keys($fields->findTab('Root.Main')->Fields()->dataFields());
        $content = array_search('Content', $names);
        $this->assertLessThan($content, array_search('Date', $names));
        $this->assertLessThan($content, array_search('Categories', $names));
        $this->assertLessThan($content, array_search('Tags', $names));
    }

    public function testCmsFieldsLeaveOutWhatTheHolderHasSwitchedOff()
    {
        $this->buildArchive(['DateFilterEnabled' => false, 'CategoriesFilterEnabled' => false, 'TagsFilterEnabled' => false]);
        $fields = $this->items['may2024']->getCMSFields();

        # The module adds no date field. (On SS6 the stub's own Date is scaffolded as a DatetimeField;
        # on SS5 it is absent. Neither is a DateField, which is what the module would add.)
        $this->assertNotInstanceOf(DateField::class, $fields->dataFieldByName('Date'));
        # Nor categories or tags, in any form (SS6 would otherwise scaffold the many_many relations)
        $this->assertNull($fields->dataFieldByName('Categories'));
        $this->assertNull($fields->dataFieldByName('Tags'));
    }

    /**
     * Regression: the date field was always a plain DateField, so for a Datetime field every save
     * from the CMS reset the time to 00:00:00. It is now the db field's own form field.
     */
    public function testDateFieldKeepsTheTimeOfADatetime()
    {
        $this->buildArchive();
        $item = $this->items['may2024'];
        $field = $item->getCMSFields()->dataFieldByName('Date');
        $this->assertInstanceOf(DatetimeField::class, $field);

        $field->setValue($item->Date);
        $field->saveInto($item);
        $this->assertSame('2024-05-03 09:30:00', $item->Date);
    }

    public function testDateOnlyFieldIsEditedWithADateField()
    {
        Config::modify()->set(FAHolder::class, 'managed_object_date_field', 'PublishDate');
        $this->buildArchive();
        $field = $this->items['may2024']->getCMSFields()->dataFieldByName('PublishDate');
        $this->assertInstanceOf(DateField::class, $field);
        $this->assertNotInstanceOf(DatetimeField::class, $field);
    }

    public function testNoDateFieldIsAddedForCreatedOrLastEdited()
    {
        Config::modify()->set(FAHolder::class, 'managed_object_date_field', 'Created');
        $this->buildArchive();
        $this->assertNull($this->items['may2024']->getCMSFields()->dataFieldByName('Created'));
    }

    /**
     * Regression: updateCMSFields() called ->getName() on getDateField() unconditionally, and
     * getDateField() is null without a holder, so editing such an item was a fatal error.
     */
    public function testCmsFieldsOfAnItemWithoutAHolder()
    {
        $root = FAItem::create(['Title' => 'root']);
        $root->write();

        $fields = $root->getCMSFields();
        $this->assertNotNull($fields->dataFieldByName('Title'));
        $this->assertNotInstanceOf(TagField::class, $fields->dataFieldByName('Tags'));
    }

    # ---------------------------------------------------------------- related items

    public function testRelatedItemsShareATagOrCategory()
    {
        $this->buildArchive();
        $this->items['may2024b']->Categories()->add($this->props['press']);

        # may2024 shares Blue with jan2023 and Press with may2024b; never itself
        $this->assertEqualsCanonicalizing(['jan2023', 'may2024b'], $this->items['may2024']->getRelatedItems()->column('Title'));
        # may2024b only shares Press, with may2024
        $this->assertSame(['may2024'], $this->items['may2024b']->getRelatedItems()->column('Title'));
    }

    /**
     * Regression: an item sharing both a tag and a category with this one was listed twice.
     */
    public function testRelatedItemsListEachItemOnce()
    {
        $this->buildArchive();
        # jan2023 now shares Blue (tag) AND Press (category) with may2024
        $this->items['jan2023']->Categories()->add($this->props['press']);

        $this->assertSame(['jan2023'], $this->items['may2024']->getRelatedItems()->column('Title'));
    }

    public function testRelatedItemsFollowTheHoldersToggles()
    {
        $this->buildArchive(['TagsFilterEnabled' => false]);
        # jan2023 is only linked to may2024 by the Blue tag; with tags off that link is gone
        $this->assertSame(0, $this->items['jan2023']->getRelatedItems()->count());
    }

    /**
     * Regression: getRelatedItems() called TagsActive() on getHolderPage() unguarded, which is
     * null for an item without a holder above it.
     */
    public function testRelatedItemsWithoutAHolderIsAnEmptyList()
    {
        $root = FAItem::create(['Title' => 'root']);
        $root->write();
        $this->assertSame(0, $root->getRelatedItems()->count());
    }
}
