<?php

namespace Restruct\SilverStripe\FilterableArchive\Tests;

use Restruct\SilverStripe\FilterableArchive\Extensions\HolderExtension;
use Restruct\SilverStripe\FilterableArchive\Tests\Stub\FAPlainHolder;
use SilverStripe\Core\Config\Config;
use SilverStripe\Dev\SapphireTest;

/**
 * Pins the ignoreRelations half of HolderExtension's scaffold_cms_fields_settings.
 *
 * updateCMSFields() calls removeByName('Tags') inside the tags_active block and, since #5,
 * removeByName('Categories') inside the categories_active block, and removeByName() recurses into
 * tabs. With the default config those calls strip the scaffolded Root.Categories/Root.Tags tabs by
 * themselves, which masks the ignore list. Switching THAT block off (a documented option) leaves
 * ignoreRelations as the only thing keeping the scaffolded relation tab out, so those cases fail
 * when an entry is dropped from it.
 *
 * Uses the non-page owner so the scaffolding settings are live on SS5 as well as SS6 (see
 * HolderExtensionNonPageOwnerTest). Compatibility note: runs under PHPUnit 9 (SS5) and 11 (SS6).
 */
class HolderExtensionIgnoreRelationsTest extends SapphireTest
{
    protected $usesDatabase = true;

    protected static $extra_dataobjects = [
        FAPlainHolder::class,
    ];

    protected static $required_extensions = [
        FAPlainHolder::class => [HolderExtension::class],
    ];

    /**
     * Relation tabs are only scaffolded for a record that exists, so the holder is written first;
     * on an unsaved record these assertions would pass whatever the ignore list says.
     */
    private function savedHolder(): FAPlainHolder
    {
        $holder = FAPlainHolder::create(['Title' => 'Holder']);
        $holder->write();
        return $holder;
    }

    public function testNoScaffoldedTagsTabWhileTagsAreSwitchedOff()
    {
        # tags_active false: nothing places a Tags grid and nothing calls removeByName('Tags')
        Config::modify()->set(FAPlainHolder::class, 'tags_active', false);
        $fields = $this->savedHolder()->getCMSFields();

        $this->assertNull($fields->fieldByName('Root.Tags'), 'no scaffolded Tags tab');
        $this->assertNull($fields->dataFieldByName('Tags'), 'no scaffolded Tags grid');
    }

    public function testNoScaffoldedCategoriesTabWhileTheDateArchiveIsSwitchedOff()
    {
        # Belt-and-braces since #5: removeByName('Categories') no longer sits in the datearchive_active
        # block, so with the default categories_active it strips the tab itself and this case no
        # longer isolates ignoreRelations (testNoScaffoldedCategoriesTabWhileCategoriesAreSwitchedOff
        # does). Kept so the date archive switch stays covered for the scaffolded Categories tab.
        Config::modify()->set(FAPlainHolder::class, 'datearchive_active', false);
        $fields = $this->savedHolder()->getCMSFields();

        $this->assertNull($fields->fieldByName('Root.Categories'), 'no scaffolded Categories tab');
        $this->assertNull($fields->dataFieldByName('Categories'), 'no scaffolded Categories grid');
    }

    public function testNoScaffoldedCategoriesTabWhileCategoriesAreSwitchedOff()
    {
        # Since #5, removeByName('Categories') sits inside the categories_active block (as the Tags
        # one does), so with the default categories_active it also strips the scaffolded tab and
        # masks the ignore list. categories_active false is the case where only ignoreRelations
        # keeps Root.Categories out.
        Config::modify()->set(FAPlainHolder::class, 'categories_active', false);
        $fields = $this->savedHolder()->getCMSFields();

        $this->assertNull($fields->fieldByName('Root.Categories'), 'no scaffolded Categories tab');
        $this->assertNull($fields->dataFieldByName('Categories'), 'no scaffolded Categories grid');
    }
}
