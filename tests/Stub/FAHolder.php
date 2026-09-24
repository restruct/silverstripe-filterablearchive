<?php

namespace Restruct\SilverStripe\FilterableArchive\Tests\Stub;

use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Dev\TestOnly;

/**
 * Test-only holder page. The extensions are applied to this stub (through each test's
 * $required_extensions) rather than to Page, so the suite does not depend on how a host
 * project configures its own page types.
 */
class FAHolder extends SiteTree implements TestOnly
{
    # Short table name: keep generated table names well under MySQL's 64-character limit
    private static $table_name = 'FATestHolder';

    # The two config settings every real holder sets (see README)
    private static $managed_object_class = FAItem::class;

    private static $managed_object_date_field = 'Date';
}
