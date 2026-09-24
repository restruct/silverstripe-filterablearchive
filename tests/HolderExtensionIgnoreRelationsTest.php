<?php

namespace Restruct\SilverStripe\FilterableArchive\Tests;

use Restruct\SilverStripe\FilterableArchive\Extensions\HolderExtension;
use Restruct\SilverStripe\FilterableArchive\Tests\Stub\FAPlainHolder;
use SilverStripe\Core\Config\Config;
use SilverStripe\Dev\SapphireTest;

/**
 * Pins the ignoreRelations half of HolderExtension's scaffold_cms_fields_settings.
 *
 * updateCMSFields() calls removeByName('Categories') inside the datearchive_active block and
 * removeByName('Tags') inside the tags_active block, and removeByName() recurses into tabs. With
 * the default config those calls strip the scaffolded Root.Categories/Root.Tags tabs by
 * themselves, which masks the ignore list. Switching the block off (a documented option) leaves
 * ignoreRelations as the only thing keeping the scaffolded relation tab out, so these cases fail
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
        # datearchive_active false skips the only removeByName('Categories'); with the categories
        # filter itself disabled, updateCMSFields() places no Categories grid either
        Config::modify()->set(FAPlainHolder::class, 'datearchive_active', false);
        $fields = $this->savedHolder()->getCMSFields();

        $this->assertNull($fields->fieldByName('Root.Categories'), 'no scaffolded Categories tab');
        $this->assertNull($fields->dataFieldByName('Categories'), 'no scaffolded Categories grid');
    }
}
