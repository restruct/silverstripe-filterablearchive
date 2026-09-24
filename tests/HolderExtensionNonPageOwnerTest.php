<?php

namespace Restruct\SilverStripe\FilterableArchive\Tests;

use Restruct\SilverStripe\FilterableArchive\Extensions\HolderExtension;
use Restruct\SilverStripe\FilterableArchive\Tests\Stub\FAPlainHolder;
use SilverStripe\Core\Config\Config;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Forms\CheckboxField;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\NumericField;

/**
 * HolderExtension on an owner that is not a page. Such an owner scaffolds its CMS fields through
 * DataObject on Silverstripe 5 as well as 6, so the extension's scaffold_cms_fields_settings are
 * live on BOTH majors here (on SS5 they are inert only for SiteTree owners). These tests pin that
 * the ignore lists keep the scaffolded copies out, so only the fields updateCMSFields() places
 * itself show up, on either major.
 *
 * Compatibility note: runs under PHPUnit 9 (SS5) and 11 (SS6); see HolderExtensionTest.
 */
class HolderExtensionNonPageOwnerTest extends SapphireTest
{
    protected $usesDatabase = true;

    protected static $extra_dataobjects = [
        FAPlainHolder::class,
    ];

    protected static $required_extensions = [
        FAPlainHolder::class => [HolderExtension::class],
    ];

    protected function setUp(): void
    {
        parent::setUp();
        # The scenario under test: pagination switched off, so ItemsPerPage must come from nowhere
        Config::modify()->set(FAPlainHolder::class, 'pagination_active', false);
    }

    /**
     * With every filter off and pagination_active false, none of the detail fields may appear:
     * without the ignore lists DataObject scaffolding would add ItemsPerPage (NumericField),
     * ArchiveUnit (enum dropdown), the title fields and Categories/Tags relation tabs regardless.
     */
    public function testIgnoreListsKeepScaffoldedCopiesOutWhileTheFiltersAreOff()
    {
        $fields = FAPlainHolder::create()->getCMSFields();

        # The toggles themselves are placed by updateCMSFields()
        $this->assertInstanceOf(CheckboxField::class, $fields->dataFieldByName('DateFilterEnabled'));
        $this->assertInstanceOf(CheckboxField::class, $fields->dataFieldByName('CategoriesFilterEnabled'));
        $this->assertInstanceOf(CheckboxField::class, $fields->dataFieldByName('TagsFilterEnabled'));

        foreach (['ItemsPerPage', 'ArchiveUnit', 'DateTitle', 'CategoriesTitle', 'TagsTitle', 'Categories', 'Tags'] as $name) {
            $this->assertNull($fields->dataFieldByName($name), "$name is not scaffolded on a non-page owner");
        }
        $this->assertNull($fields->fieldByName('Root.Categories'), 'no scaffolded Categories tab');
        $this->assertNull($fields->fieldByName('Root.Tags'), 'no scaffolded Tags tab');
    }

    /**
     * With the filters on, ArchiveUnit and the Categories/Tags grids are present, each once and on
     * the configured tab; ItemsPerPage stays out because pagination_active is false.
     */
    public function testFieldsAppearOnceWhenTheFiltersAreOnButPaginationIsOff()
    {
        $holder = FAPlainHolder::create([
            'DateFilterEnabled' => true,
            'CategoriesFilterEnabled' => true,
            'TagsFilterEnabled' => true,
        ]);
        $holder->write();
        $fields = $holder->getCMSFields();

        $this->assertNull($fields->dataFieldByName('ItemsPerPage'), 'pagination_active: false keeps ItemsPerPage out');
        $this->assertInstanceOf(DropdownField::class, $fields->fieldByName('Root.Main.ArchiveUnit'));
        foreach (['Categories', 'Tags'] as $relation) {
            $this->assertInstanceOf(GridField::class, $fields->fieldByName("Root.Main.$relation"), "$relation grid on Root.Main");
            $this->assertNull($fields->fieldByName("Root.$relation"), "no scaffolded Root.$relation tab");
        }
    }

    public function testItemsPerPageIsPlacedWhenPaginationIsActive()
    {
        Config::modify()->set(FAPlainHolder::class, 'pagination_active', true);
        $fields = FAPlainHolder::create()->getCMSFields();
        $this->assertInstanceOf(NumericField::class, $fields->fieldByName('Root.Main.ItemsPerPage'));
    }
}
