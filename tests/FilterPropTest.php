<?php

namespace Restruct\SilverStripe\FilterableArchive\Tests;

use Restruct\SilverStripe\FilterableArchive\Extensions\HolderControllerExtension;
use Restruct\SilverStripe\FilterableArchive\Extensions\HolderExtension;
use Restruct\SilverStripe\FilterableArchive\Extensions\ItemExtension;
use Restruct\SilverStripe\FilterableArchive\FilterProp;
use Restruct\SilverStripe\FilterableArchive\Tests\Stub\FAHolder;
use Restruct\SilverStripe\FilterableArchive\Tests\Stub\FAHolderController;
use Restruct\SilverStripe\FilterableArchive\Tests\Stub\FAItem;
use SilverStripe\Dev\SapphireTest;

/**
 * Behavioural tests for FilterProp, the record behind both categories and tags.
 *
 * Compatibility note: runs under PHPUnit 9 (Silverstripe 5) and PHPUnit 11 (Silverstripe 6);
 * no doc-comment metadata, no assertions removed after PHPUnit 9.
 */
class FilterPropTest extends SapphireTest
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

    public function testUrlSegmentIsDerivedFromTheTitleOnWrite()
    {
        $prop = FilterProp::create(['Title' => 'Press Releases & News']);
        $prop->write();
        $this->assertSame('press-releases-and-news', $prop->URLSegment);

        $prop->Title = 'Renamed';
        $prop->write();
        $this->assertSame('renamed', $prop->URLSegment, 'the segment follows a renamed title');
    }

    public function testLinkPointsAtTheHolderWithTheRightParameter()
    {
        $this->buildArchive();
        $holderLink = $this->holder->Link();

        $this->assertSame($holderLink . '?cat=events', $this->props['events']->getLink());
        $this->assertSame($holderLink . '?tag=green', $this->props['green']->getLink());
        # Also reachable as $Link in templates
        $this->assertSame($holderLink . '?tag=green', $this->props['green']->Link);
    }

    /**
     * The holder's category and tag lists (its CMS grids, the filter dropdowns) are in creation
     * order by definition, not by the database's habit: FilterProp has a default_sort on ID. The
     * SQL is checked because MySQL/MariaDB return ID order without an ORDER BY as well, so a
     * comparison of rows would pass without it.
     */
    public function testCategoriesAndTagsAreListedInCreationOrder()
    {
        $this->buildArchive();

        foreach (['Categories', 'Tags'] as $relation) {
            $list = $this->holder->$relation();
            $this->assertMatchesRegularExpression('/ORDER BY "FilterProp"\."ID" ASC\s*$/', $list->sql(), "$relation are sorted on ID");
            $ids = $list->column('ID');
            $sorted = $ids;
            sort($sorted);
            $this->assertSame($sorted, $ids);
        }
    }

    public function testLinkIsNullWithoutAHolder()
    {
        $prop = FilterProp::create(['Title' => 'Loose']);
        $prop->write();
        $this->assertNull($prop->getLink());
    }
}
