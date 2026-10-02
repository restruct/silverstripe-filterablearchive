<?php

namespace Restruct\FaBrowser;

use SilverStripe\CMS\Controllers\ContentController;

/**
 * BROWSER-TEST FIXTURE ONLY - an item's page: its FilterableProperties (LinkFilterProps=1) and its
 * related items. See FaBHolder for why this never loads in a real install.
 */
class FaBItemController extends ContentController
{
    use FaBRenders;

    public function pageHtml(): string
    {
        $item = $this->data();
        $related = '';
        foreach ($item->getRelatedItems() as $other) {
            $related .= '<li class="fa-related-item">' . self::esc($other->Title) . '</li>';
        }

        return $this->shell(
            '<h1>' . self::esc($item->Title) . '</h1>'
            . '<div class="fa-props">' . (string) $item->customise(['LinkFilterProps' => 1])->renderWith('FilterableProperties') . '</div>'
            . '<ul class="fa-related">' . $related . '</ul>'
        );
    }
}
