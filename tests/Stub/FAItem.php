<?php

namespace Restruct\SilverStripe\FilterableArchive\Tests\Stub;

use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Dev\TestOnly;

/**
 * Test-only item page, filed below an FAHolder. Carries its own Date field (a Datetime), which the
 * holder names as its managed_object_date_field, and a date-only PublishDate.
 */
class FAItem extends SiteTree implements TestOnly
{
    private static $table_name = 'FATestItem';

    private static $db = [
        'Date' => 'Datetime',
        # A date-only alternative, for holders configured to use it
        'PublishDate' => 'Date',
    ];
}
