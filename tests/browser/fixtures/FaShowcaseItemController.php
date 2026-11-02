<?php

namespace Restruct\FaBrowser;

use SilverStripe\CMS\Controllers\ContentController;

/**
 * BROWSER-TEST FIXTURE ONLY - a news item's page for the README screenshots: its date, categories
 * and tags (FilterableProperties, LinkFilterProps=1), its text, and its related items
 * (getRelatedItems()). Same page shell as FaShowcaseHolderController; see FaShowcaseHolder for why
 * this never loads in a real install.
 */
class FaShowcaseItemController extends ContentController
{
    use FaBRenders;

    public function pageHtml(): string
    {
        $item = $this->data();
        $holder = $item->getHolderPage();

        $related = '';
        foreach ($item->getRelatedItems() as $other) {
            $related .= '<li class="list-group-item"><a class="text-decoration-none" href="' . self::esc($other->Link()) . '">'
                . self::esc($other->Title) . '</a></li>';
        }

        return $this->shell(
            ($holder ? '<p class="small mb-2"><a class="text-decoration-none" href="' . self::esc($holder->Link()) . '">&larr; ' . self::esc($holder->Title) . '</a></p>' : '')
            . '<h1 class="h2 mb-2">' . self::esc($item->Title) . '</h1>'
            . '<div class="mb-3">' . (string) $item->customise(['LinkFilterProps' => 1])->renderWith('FilterableProperties') . '</div>'
            . '<div class="mb-4">' . (string) $item->Content . '</div>'
            . ($related ? '<h2 class="h6 text-uppercase text-body-secondary">Related</h2><ul class="list-group fa-related">' . $related . '</ul>' : '')
        );
    }

    /** The same shell as FaShowcaseHolderController's. */
    protected function shell(string $body): string
    {
        return '<!doctype html><html lang="en"><head><meta charset="utf-8"><link rel="icon" href="data:,">'
            . '<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">'
            . '<title>' . self::esc($this->data()->Title) . '</title></head>'
            . '<body class="bg-body-tertiary"><main id="showcase" class="container bg-body py-4 px-4 my-4 rounded border" style="max-width: 880px" data-page-id="' . (int) $this->data()->ID . '">'
            . $body . '</main></body></html>';
    }
}
