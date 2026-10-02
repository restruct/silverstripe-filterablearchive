<?php

namespace Restruct\FaBrowser;

/**
 * BROWSER-TEST FIXTURE ONLY - the page shell for the fixture controllers. The scratch host has no
 * theme and the fixtures cannot ship templates (they are copied into app/src/, where no templates
 * dir is scanned), so the controllers build a minimal HTML page around the module's own templates,
 * rendered the way a theme includes them. See FaBHolder for why this never loads in a real install.
 */
trait FaBRenders
{
    /**
     * prepareResponse() renders a controller returned by an action (as the module's date/cat/tag
     * actions do) with getViewer($action)->process($controller): answer with the same page.
     */
    public function getViewer($action)
    {
        $controller = $this;
        return new class ($controller) {
            private $controller;

            public function __construct($controller)
            {
                $this->controller = $controller;
            }

            public function process($item)
            {
                return $this->controller->pageHtml();
            }
        };
    }

    public function index()
    {
        return $this->pageHtml();
    }

    protected static function esc($value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES);
    }

    /** A minimal HTML document; the empty favicon keeps the browser from requesting one. */
    protected function shell(string $body): string
    {
        return '<!doctype html><html lang="en"><head><meta charset="utf-8"><link rel="icon" href="data:,">'
            . '<title>' . self::esc($this->data()->Title) . '</title></head><body>' . $body . '</body></html>';
    }
}
