<?php

namespace Restruct\SilverStripe\FilterableArchive\Tests\Stub;

use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;

/**
 * Test-only holder that is NOT a page. On Silverstripe 5 SiteTree hand-builds its CMS fields, so
 * the extension's scaffold_cms_fields_settings only take effect there for an owner like this one,
 * which scaffolds through DataObject::getCMSFields() on both majors.
 */
class FAPlainHolder extends DataObject implements TestOnly
{
    # Short table name, as for the other stubs
    private static $table_name = 'FATestPlainHolder';

    private static $db = [
        'Title' => 'Varchar',
    ];
}
