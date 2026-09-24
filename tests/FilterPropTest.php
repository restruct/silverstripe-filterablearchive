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

    public function testLinkIsNullWithoutAHolder()
    {
        $prop = FilterProp::create(['Title' => 'Loose']);
        $prop->write();
        $this->assertNull($prop->getLink());
    }
}
