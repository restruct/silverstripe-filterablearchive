<?php

namespace Restruct\FaBrowser;

use Restruct\SilverStripe\FilterableArchive\Extensions\HolderExtension;
use SilverStripe\CMS\Model\SiteTree;

/**
 * BROWSER-TEST FIXTURE ONLY - a holder page set up as the README's Setup section says: the
 * HolderExtension, its items' class and their date field. Rendered by FaBHolderController.
 *
 * Never loaded by a real install: it lives under tests/browser/, which carries a _manifest_exclude
 * marker, and the browser-test runner copies it into a scratch host's app/ before dev/build.
 * The extensions are declared on the classes themselves: the runner copies fixtures into
 * app/src/, where no _config YAML is read. Written for Silverstripe 5 and 6 alike.
 */
class FaBHolder extends SiteTree
{
    # Short table names: no namespaced defaults, MySQL caps table names at 64 characters.
    private static $table_name = 'FaBHolder';

    private static $singular_name = 'Browser holder';

    private static $extensions = [
        HolderExtension::class,
    ];

    private static $managed_object_class = FaBItem::class;

    private static $managed_object_date_field = 'Date';

    private static $allowed_children = [FaBItem::class];

    /**
     * Without the Content editor. The admin's TinyMCE integration throws uncaught errors of its own
     * in a scripted browser: on Silverstripe 5 its scroll handler calls a global $ that is not jQuery
     * ("$ is not a function", when the edit panel scrolls), on Silverstripe 6 its textarea's change
     * tracker runs after the editor is gone ("reading 'prepValueForChangeTracker'"). Neither involves
     * this module, and the specs fail on any uncaught error, so the editor is left out. The module's
     * fields are placed before Content while it is still there (updateCMSFields runs inside
     * parent::getCMSFields()), so their position is unchanged.
     */
    public function getCMSFields()
    {
        $fields = parent::getCMSFields();
        $fields->removeByName('Content');

        return $fields;
    }
}
