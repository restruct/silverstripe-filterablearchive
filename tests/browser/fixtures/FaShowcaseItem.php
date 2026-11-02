<?php

namespace Restruct\FaBrowser;

use Restruct\SilverStripe\FilterableArchive\Extensions\ItemExtension;
use SilverStripe\CMS\Model\SiteTree;

/**
 * BROWSER-TEST FIXTURE ONLY - a news item below FaShowcaseHolder, for the README screenshots, with
 * a plain Date the holder filters on. Seeded by FaShowcaseHolder::requireDefaultRecords(); see
 * FaShowcaseHolder for why this never loads in a real install.
 *
 * @property string $Date
 */
class FaShowcaseItem extends SiteTree
{
    private static $table_name = 'FaShowcaseItem';

    private static $singular_name = 'News item';

    private static $db = [
        'Date' => 'Date',
    ];

    private static $extensions = [
        ItemExtension::class,
    ];

    private static $can_be_root = false;
}
