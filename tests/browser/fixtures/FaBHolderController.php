<?php

namespace Restruct\FaBrowser;

use Restruct\SilverStripe\FilterableArchive\Extensions\HolderControllerExtension;
use SilverStripe\CMS\Controllers\ContentController;

/**
 * BROWSER-TEST FIXTURE ONLY - the holder's controller, with the HolderControllerExtension. Its page
 * is the README's Templates section: the filter form, the (paginated) items with their
 * FilterableProperties (LinkFilterProps=1), and both pagination templates.
 * See FaBHolder for why this never loads in a real install.
 */
class FaBHolderController extends ContentController
{
    use FaBRenders;

    private static $extensions = [
        HolderControllerExtension::class,
    ];

    public function pageHtml(): string
    {
        $items = '';
        foreach ($this->PaginatedItems() as $item) {
            $items .= '<li class="fa-item" data-title="' . self::esc($item->Title) . '">'
                . '<a class="fa-item-link" href="' . self::esc($item->Link()) . '">' . self::esc($item->Title) . '</a> '
                . (string) $item->customise(['LinkFilterProps' => 1])->renderWith('FilterableProperties')
                . '</li>';
        }

        return $this->shell(
            '<h1>' . self::esc($this->data()->Title) . '</h1>'
            . (string) $this->renderWith('FilterableArchiveFilter')
            . '<ol class="fa-items">' . $items . '</ol>'
            . '<div class="fa-pagination-plain">' . (string) $this->renderWith('FilterableArchivePagination') . '</div>'
            . '<div class="fa-pagination-bootstrap">' . (string) $this->renderWith('FilterableArchiveBootstrapPagination') . '</div>'
        );
    }
}
