<?php

namespace Restruct\FaBrowser;

use Restruct\SilverStripe\FilterableArchive\Extensions\HolderControllerExtension;
use SilverStripe\CMS\Controllers\ContentController;

/**
 * BROWSER-TEST FIXTURE ONLY - the front end of the README screenshots: the News holder as a theme
 * would render it, with the module's templates where the README's Templates section puts them
 * (the filter form, the paginated items with FilterableProperties, the Bootstrap pagination).
 * See FaShowcaseHolder for why this never loads in a real install.
 *
 * The scratch host has no theme and fixtures cannot ship templates (they are copied into app/src/),
 * so the page is built here, around the module's own templates. The module's templates carry
 * Bootstrap 5 classes, so the page loads Bootstrap's stylesheet, as a Bootstrap theme would.
 */
class FaShowcaseHolderController extends ContentController
{
    use FaBRenders;

    private static $extensions = [
        HolderControllerExtension::class,
    ];

    public function pageHtml(): string
    {
        $items = '';
        foreach ($this->PaginatedItems() as $item) {
            $items .= '<article class="py-3 border-bottom fa-item" data-page-id="' . (int) $item->ID . '">'
                . '<h2 class="h5 mb-1"><a class="text-decoration-none" href="' . self::esc($item->Link()) . '">' . self::esc($item->Title) . '</a></h2>'
                . '<div class="mb-1">' . (string) $item->customise(['LinkFilterProps' => 1])->renderWith('FilterableProperties') . '</div>'
                . '<p class="mb-0 text-body-secondary">' . self::esc(strip_tags((string) $item->Content)) . '</p>'
                . '</article>';
        }
        if ($items === '') {
            $items = '<p class="py-3 text-body-secondary">No items match this filter.</p>';
        }

        return $this->shell(
            '<h1 class="mb-1">' . self::esc($this->data()->Title) . '</h1>'
            . '<p class="lead text-body-secondary mb-4">' . self::esc(strip_tags((string) $this->data()->Content)) . '</p>'
            . (string) $this->renderWith('FilterableArchiveFilter')
            . '<div class="fa-items mt-2 mb-4">' . $items . '</div>'
            . (string) $this->renderWith('FilterableArchiveBootstrapPagination')
        );
    }

    /** The FaBRenders shell, with Bootstrap 5's stylesheet and a centred column. */
    protected function shell(string $body): string
    {
        return '<!doctype html><html lang="en"><head><meta charset="utf-8"><link rel="icon" href="data:,">'
            . '<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">'
            . '<title>' . self::esc($this->data()->Title) . '</title></head>'
            . '<body class="bg-body-tertiary"><main id="showcase" class="container bg-body py-4 px-4 my-4 rounded border" style="max-width: 880px" data-page-id="' . (int) $this->data()->ID . '">'
            . $body . '</main></body></html>';
    }
}
