<?php

namespace Restruct\SilverStripe\FilterableArchive\Tests;

use Restruct\SilverStripe\FilterableArchive\Extensions\HolderControllerExtension;
use Restruct\SilverStripe\FilterableArchive\Extensions\HolderExtension;
use Restruct\SilverStripe\FilterableArchive\Extensions\ItemExtension;
use Restruct\SilverStripe\FilterableArchive\FilterProp;
use Restruct\SilverStripe\FilterableArchive\Tests\Stub\FAHolder;
use Restruct\SilverStripe\FilterableArchive\Tests\Stub\FAHolderController;
use Restruct\SilverStripe\FilterableArchive\Tests\Stub\FAItem;
use SilverStripe\Control\Controller;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\Session;
use SilverStripe\Core\Config\Config;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Forms\CheckboxField;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\NumericField;
use SilverStripe\Forms\Tab;
use SilverStripe\Forms\TabSet;
use SilverStripe\Forms\TextField;
use SilverStripe\i18n\i18n;
use SilverStripe\ORM\DataObject;
use Symbiote\GridFieldExtensions\GridFieldAddNewInlineButton;
use Symbiote\GridFieldExtensions\GridFieldEditableColumns;

/**
 * Behavioural tests for HolderExtension: schema, CMS fields, config toggles, the unfiltered item
 * list and the filter dropdowns.
 *
 * Compatibility note: this suite runs under PHPUnit 9 (Silverstripe 5) and PHPUnit 11
 * (Silverstripe 6). Keep it free of doc-comment metadata (@test, @dataProvider), make any data
 * provider static, and avoid assertions removed after PHPUnit 9.
 */
class HolderExtensionTest extends SapphireTest
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

    private function keySorted(array $source): array
    {
        ksort($source);

        return $source;
    }

    # ---------------------------------------------------------------- wiring and schema

    public function testExtensionAddsItsFieldsAndRelationsToTheHolder()
    {
        $schema = DataObject::getSchema();
        $fields = $schema->databaseFields(FAHolder::class, false);
        foreach (['CategoriesFilterEnabled', 'CategoriesTitle', 'TagsFilterEnabled', 'TagsTitle', 'DateFilterEnabled', 'DateTitle', 'ArchiveUnit', 'ItemsPerPage'] as $field) {
            $this->assertArrayHasKey($field, $fields, "$field is a database field of the holder");
        }

        $this->assertSame(FilterProp::class, $schema->hasManyComponent(FAHolder::class, 'Categories'));
        $this->assertSame(FilterProp::class, $schema->hasManyComponent(FAHolder::class, 'Tags'));
        # The has_many is keyed on the holder-specific has_one, so categories and tags stay apart
        $this->assertSame('CatHolderPageID', $schema->getRemoteJoinField(FAHolder::class, 'Categories', 'has_many'));
        $this->assertSame('TagHolderPageID', $schema->getRemoteJoinField(FAHolder::class, 'Tags', 'has_many'));
    }

    public function testCategoriesAndTagsOfAHolderAreSeparateLists()
    {
        $holder = $this->buildArchive();
        $this->assertEqualsCanonicalizing(['events', 'press'], $holder->Categories()->column('URLSegment'));
        $this->assertEqualsCanonicalizing(['blue', 'green'], $holder->Tags()->column('URLSegment'));
    }

    # ---------------------------------------------------------------- toggles

    public function testFiltersAreActiveOnlyWhenConfiguredAndEnabledOnThePage()
    {
        $holder = FAHolder::create();
        $this->assertFalse((bool) $holder->ArchiveActive(), 'Date archive is off until enabled on the page');
        $this->assertFalse((bool) $holder->CategoriesActive());
        $this->assertFalse((bool) $holder->TagsActive());

        $holder->DateFilterEnabled = true;
        $holder->CategoriesFilterEnabled = true;
        $holder->TagsFilterEnabled = true;
        $this->assertTrue((bool) $holder->ArchiveActive());
        $this->assertTrue((bool) $holder->CategoriesActive());
        $this->assertTrue((bool) $holder->TagsActive());

        # A falsy config value switches the filter off for the class, whatever the page says
        Config::modify()->set(FAHolder::class, 'datearchive_active', false);
        Config::modify()->set(FAHolder::class, 'categories_active', false);
        Config::modify()->set(FAHolder::class, 'tags_active', false);
        $this->assertFalse((bool) $holder->ArchiveActive());
        $this->assertFalse((bool) $holder->CategoriesActive());
        $this->assertFalse((bool) $holder->TagsActive());
    }

    # ---------------------------------------------------------------- CMS fields

    /**
     * Also the regression test for Silverstripe 6's SiteTree scaffolding: without
     * scaffold_cms_fields_settings the detail fields showed up regardless of the toggles.
     */
    public function testCmsFieldsBeforeTheFiltersAreEnabled()
    {
        $fields = FAHolder::create()->getCMSFields();

        $this->assertInstanceOf(NumericField::class, $fields->dataFieldByName('ItemsPerPage'));
        $this->assertInstanceOf(CheckboxField::class, $fields->dataFieldByName('DateFilterEnabled'));
        $this->assertInstanceOf(CheckboxField::class, $fields->dataFieldByName('CategoriesFilterEnabled'));
        $this->assertInstanceOf(CheckboxField::class, $fields->dataFieldByName('TagsFilterEnabled'));

        # The detail fields only appear once a filter is switched on
        $this->assertNull($fields->dataFieldByName('DateTitle'));
        $this->assertNull($fields->dataFieldByName('ArchiveUnit'));
        $this->assertNull($fields->dataFieldByName('CategoriesTitle'));
        $this->assertNull($fields->dataFieldByName('TagsTitle'));
        $this->assertNull($fields->dataFieldByName('Categories'));
        $this->assertNull($fields->dataFieldByName('Tags'));

        # Placed on the configured tab (default Root.Main)
        $this->assertNotNull($fields->fieldByName('Root.Main.DateFilterEnabled'));
    }

    public function testCmsFieldsOnceTheFiltersAreEnabled()
    {
        $holder = $this->buildArchive();
        $fields = $holder->getCMSFields();

        $this->assertInstanceOf(TextField::class, $fields->dataFieldByName('DateTitle'));
        $unit = $fields->dataFieldByName('ArchiveUnit');
        $this->assertInstanceOf(DropdownField::class, $unit);
        $this->assertSame(['year', 'month', 'day'], array_keys($unit->getSource()));

        foreach (['Categories', 'Tags'] as $relation) {
            $grid = $fields->dataFieldByName($relation);
            $this->assertInstanceOf(GridField::class, $grid, "$relation is managed in a GridField");
            $this->assertNotNull($grid->getConfig()->getComponentByType(GridFieldEditableColumns::class));
            $this->assertNotNull($grid->getConfig()->getComponentByType(GridFieldAddNewInlineButton::class));
            $this->assertSame(2, $grid->getList()->count());
            # Exactly one field of that name: the scaffolded has_many tab must be gone
            $this->assertNull($fields->fieldByName("Root.$relation"), "the scaffolded Root.$relation tab is removed");
        }
    }

    /**
     * Regression: lang/en.yml was keyed nl:, so an English CMS showed the raw _t() defaults
     * ("DateTitle") instead of the English strings ("Dates label").
     */
    public function testEnglishLabelsLoad()
    {
        i18n::set_locale('en_US');
        $fields = $this->buildArchive()->getCMSFields();
        $this->assertSame('Dates label', $fields->dataFieldByName('DateTitle')->Title());
        $this->assertSame('Categories label', $fields->dataFieldByName('CategoriesTitle')->Title());
    }

    public function testCmsFieldsGoOnTheConfiguredTab()
    {
        Config::modify()->set(FAHolder::class, 'pagination_control_tab', 'Root.Filtering');
        $fields = FAHolder::create()->getCMSFields();
        $this->assertNotNull($fields->fieldByName('Root.Filtering.ItemsPerPage'));
        $this->assertNotNull($fields->fieldByName('Root.Filtering.DateFilterEnabled'));
        $this->assertNull($fields->fieldByName('Root.Main.ItemsPerPage'));
    }

    public function testPaginationFieldCanBeSwitchedOff()
    {
        Config::modify()->set(FAHolder::class, 'pagination_active', false);
        $this->assertNull(FAHolder::create()->getCMSFields()->dataFieldByName('ItemsPerPage'));
    }

    public function testCmsFieldsAreInsertedBeforeTheConfiguredField()
    {
        Config::modify()->set(FAHolder::class, 'pagination_insert_before', 'Content');
        $names = array_keys(FAHolder::create()->getCMSFields()->findTab('Root.Main')->Fields()->dataFields());
        $this->assertLessThan(
            array_search('Content', $names),
            array_search('ItemsPerPage', $names),
            'ItemsPerPage is placed before Content'
        );
    }

    /**
     * A minimal CMS field list holding one field named Categories on its own tab (standing in for a
     * holder that defines a Categories field of its own), run once through updateCMSFields().
     */
    private function cmsFieldsWithOwnCategoriesField(FAHolder $holder)
    {
        $fields = FieldList::create(TabSet::create(
            'Root',
            Tab::create('Main'),
            Tab::create('Own', TextField::create('Categories', 'Own categories field'))
        ));
        $holder->extend('updateCMSFields', $fields);

        return $fields;
    }

    /**
     * Issue #5: removeByName('Categories') sat inside the date-archive block, so whether a field
     * named Categories survived depended on the date archive setting. With the categories filter
     * switched off the module places no Categories grid, so it has no reason to remove one.
     */
    public function testTheDateArchiveDoesNotRemoveAnOwnCategoriesField()
    {
        Config::modify()->set(FAHolder::class, 'categories_active', false);
        $fields = $this->cmsFieldsWithOwnCategoriesField($this->buildArchive());

        $this->assertInstanceOf(TextField::class, $fields->fieldByName('Root.Own.Categories'), 'the own Categories field stays');
    }

    /**
     * Issue #5, the other half: with the categories filter on, a leftover Root.Categories tab (a
     * scaffolded has_many tab, eg where ignoreRelations does not apply) goes whatever the date
     * archive setting, as Root.Tags already did. Placing the module's grid only displaces a
     * same-named DATA field (FieldList::onBeforeInsert), never a tab, so this needs the removal.
     */
    public function testALeftoverCategoriesTabGoesWithTheDateArchiveSwitchedOff()
    {
        Config::modify()->set(FAHolder::class, 'datearchive_active', false);
        $holder = $this->buildArchive();
        $fields = FieldList::create(TabSet::create('Root', Tab::create('Main'), Tab::create('Categories')));
        $holder->extend('updateCMSFields', $fields);

        $this->assertNull($fields->fieldByName('Root.Categories'), 'the leftover Categories tab is removed');
        $this->assertInstanceOf(GridField::class, $fields->dataFieldByName('Categories'), 'the module grid is placed');
    }

    # ---------------------------------------------------------------- unfiltered items

    public function testGetItemsReturnsTheHoldersChildrenNewestFirst()
    {
        $holder = $this->buildArchive();

        # An item of the same class elsewhere in the tree is not one of this holder's items
        $stray = FAItem::create(['Title' => 'stray', 'Date' => '2025-01-01']);
        $stray->write();

        $this->assertSame(['may2024b', 'may2024', 'jan2023'], $holder->getItems()->column('Title'));
    }

    # ---------------------------------------------------------------- dropdowns

    public function testArchiveDropdownIsAbsentWhileTheArchiveIsOff()
    {
        $holder = $this->buildArchive(['DateFilterEnabled' => false]);
        $this->assertNull($holder->ArchiveFilterDropdown());
    }

    public function testArchiveDropdownListsOneOptionPerYearByDefault()
    {
        $holder = $this->buildArchive();
        $dropdown = $holder->ArchiveFilterDropdown();

        $this->assertInstanceOf(DropdownField::class, $dropdown);
        $this->assertSame('date', $dropdown->getName());
        $this->assertSame(['2024' => '2024', '2023' => '2023'], $dropdown->getSource());
    }

    public function testArchiveDropdownFollowsTheArchiveUnit()
    {
        $holder = $this->buildArchive(['ArchiveUnit' => 'month']);
        $this->assertSame(['2024-05', '2023-01'], array_keys($holder->ArchiveFilterDropdown()->getSource()));

        $holder->ArchiveUnit = 'day';
        $this->assertSame(['2024-05-20', '2024-05-03', '2023-01-15'], array_keys($holder->ArchiveFilterDropdown()->getSource()));
    }

    public function testArchiveDropdownLabelPrecedence()
    {
        $holder = $this->buildArchive();
        # Nothing set on the page or passed in: the config value is the label
        $this->assertSame('Date', $holder->ArchiveFilterDropdown()->getEmptyString());
        # An argument beats config
        $this->assertSame('Pick a year', $holder->ArchiveFilterDropdown('Pick a year')->getEmptyString());
        # The page's own title beats both
        $holder->DateTitle = 'Archive';
        $this->assertSame('Archive', $holder->ArchiveFilterDropdown('Pick a year')->getEmptyString());
    }

    /**
     * Regression: the label fallbacks read the EXTENSION's config (self::config()), so a
     * label configured on the holder class, as the README documents, was ignored.
     */
    public function testDropdownLabelsFollowConfigSetOnTheHolderClass()
    {
        Config::modify()->set(FAHolder::class, 'datearchive_active', 'When');
        Config::modify()->set(FAHolder::class, 'categories_active', 'Topic');
        Config::modify()->set(FAHolder::class, 'tags_active', 'Keyword');
        $holder = $this->buildArchive();

        $this->assertSame('When', $holder->ArchiveFilterDropdown()->getEmptyString());
        $this->assertSame('Topic', $holder->FilterDropdown('cat')->getEmptyString());
        $this->assertSame('Keyword', $holder->FilterDropdown('tag')->getEmptyString());
    }

    public function testFilterDropdownsListTheHoldersCategoriesAndTags()
    {
        $holder = $this->buildArchive();

        $cat = $holder->FilterDropdown('cat');
        $this->assertSame('cat', $cat->getName());
        # assertSame on a key-sorted copy: assertEqualsCanonicalizing would ignore the keys, and the
        # keys (URL segments) are what the filter submits
        $this->assertSame(['events' => 'Events', 'press' => 'Press'], $this->keySorted($cat->getSource()));
        $this->assertSame('Categories', $cat->getEmptyString());

        $tag = $holder->FilterDropdown('tag');
        $this->assertSame('tag', $tag->getName());
        $this->assertSame(['blue' => 'Blue', 'green' => 'Green'], $this->keySorted($tag->getSource()));

        $holder->TagsTitle = 'Labels';
        $this->assertSame('Labels', $holder->FilterDropdown('tag', 'ignored')->getEmptyString());
    }

    public function testFilterDropdownsAreAbsentWhileTheirFilterIsOff()
    {
        $holder = $this->buildArchive(['CategoriesFilterEnabled' => false, 'TagsFilterEnabled' => false]);
        $this->assertNull($holder->FilterDropdown('cat'));
        $this->assertNull($holder->FilterDropdown('tag'));
    }

    public function testDropdownsPreselectTheCurrentFilter()
    {
        $holder = $this->buildArchive();
        $request = new HTTPRequest('GET', 'news', ['date' => '2023', 'cat' => 'press', 'tag' => 'green']);
        $request->setSession(new Session([]));
        $controller = FAHolderController::create($holder);
        $controller->setRequest($request);
        $controller->pushCurrent();
        try {
            # dataValue(): FormField::Value() is gone in 6, and getValue() does not exist in 5
            $this->assertSame('2023', $holder->ArchiveFilterDropdown()->dataValue());
            $this->assertSame('press', $holder->FilterDropdown('cat')->dataValue());
            $this->assertSame('green', $holder->FilterDropdown('tag')->dataValue());
        } finally {
            $controller->popCurrent();
        }
    }

    /**
     * Regression: the dropdowns called a static method on Controller::curr() unguarded, so
     * building one with no current controller (a CLI task, a queued job) was a fatal error.
     */
    public function testDropdownsBuildWithoutACurrentController()
    {
        $holder = $this->buildArchive();

        # Empty the controller stack for the duration of this test and put it back afterwards:
        # SapphireTest pushes a dummy controller once per process, which later tests rely on.
        # Reflection because Controller::has_curr() is deprecated in 5.4 and gone in 6 (no
        # setAccessible(): not needed since PHP 8.1, deprecated in 8.5).
        $stack = new \ReflectionProperty(Controller::class, 'controller_stack');
        $saved = $stack->getValue();
        $stack->setValue(null, []);
        try {
            $this->assertInstanceOf(DropdownField::class, $holder->ArchiveFilterDropdown());
            $this->assertInstanceOf(DropdownField::class, $holder->FilterDropdown('cat'));
            $this->assertInstanceOf(DropdownField::class, $holder->FilterDropdown('tag'));
        } finally {
            $stack->setValue(null, $saved);
        }
    }
}
