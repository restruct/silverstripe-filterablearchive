<?php

namespace Restruct\FaBrowser;

use Restruct\SilverStripe\FilterableArchive\Extensions\ItemExtension;
use SilverStripe\CMS\Model\SiteTree;

/**
 * BROWSER-TEST FIXTURE ONLY - an item page below FaBHolder, with a Datetime the holder filters on.
 * See FaBHolder for why this never loads in a real install.
 *
 * @property string $Date
 */
class FaBItem extends SiteTree
{
    private static $table_name = 'FaBItem';

    private static $singular_name = 'Browser item';

    private static $db = [
        'Date' => 'Datetime',
    ];

    private static $extensions = [
        ItemExtension::class,
    ];

    private static $can_be_root = false;

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
