<?php

namespace Restruct\SilverStripe\FilterableArchive\Tests;

use Restruct\SilverStripe\FilterableArchive\Extensions\HolderControllerExtension;
use Restruct\SilverStripe\FilterableArchive\Extensions\HolderExtension;
use Restruct\SilverStripe\FilterableArchive\Extensions\ItemExtension;
use Restruct\SilverStripe\FilterableArchive\Tests\Stub\FAHolder;
use Restruct\SilverStripe\FilterableArchive\Tests\Stub\FAHolderController;
use Restruct\SilverStripe\FilterableArchive\Tests\Stub\FAItem;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\Session;
use SilverStripe\Dev\SapphireTest;

/**
 * Renders the module's templates the way a theme includes them: the filter form and the two
 * pagination variants in the holder's (controller) scope, FilterableProperties in an item's.
 *
 * Compatibility note: runs under PHPUnit 9 (Silverstripe 5) and PHPUnit 11 (Silverstripe 6);
 * no doc-comment metadata, no assertions removed after PHPUnit 9.
 */
class TemplatesTest extends SapphireTest
{
    use BuildsArchive;

    protected $usesDatabase = true;

    protected static $extra_dataobjects = [
        FAHolder::class,
        FAItem::class,
    ];

    protected static $required_extensions = [
        FAHolder::class => [HolderExtension::class],
        FAHolderController::class => [HolderControllerExtension::class],
        FAItem::class => [ItemExtension::class],
    ];

    /** @var FAHolderController|null */
    private $controller;

    protected function tearDown(): void
    {
        if ($this->controller) {
            $this->controller->popCurrent();
            $this->controller = null;
        }
        parent::tearDown();
    }

    /**
     * Render a template in the scope of the holder's controller, handling the given GET vars.
     * The controller is made current, as it is during a real request: the dropdowns read the
     * active filter from it.
     */
    private function renderHolder(string $template, array $getVars = []): string
    {
        $request = new HTTPRequest('GET', 'news', $getVars);
        $request->setSession(new Session([]));
        $this->controller = FAHolderController::create($this->holder);
        $this->controller->setRequest($request);
        $this->controller->pushCurrent();

        return (string) $this->controller->renderWith($template);
    }

    public function testFilterFormRendersOneDropdownPerActiveFilter()
    {
        $this->buildArchive();
        $html = $this->renderHolder('FilterableArchiveFilter');

        $this->assertStringContainsString('<form', $html);
        $this->assertMatchesRegularExpression('/<select[^>]+name="cat"/', $html);
        $this->assertMatchesRegularExpression('/<select[^>]+name="tag"/', $html);
        $this->assertMatchesRegularExpression('/<select[^>]+name="date"/', $html);
        $this->assertMatchesRegularExpression('/>\s*Press\s*</', $html);
        $this->assertMatchesRegularExpression('/>\s*2023\s*</', $html);
        # Nothing filtered yet: no reset links
        $this->assertStringNotContainsString('&times;', $html);
    }

    public function testFilterFormLeavesOutSwitchedOffFilters()
    {
        $this->buildArchive(['CategoriesFilterEnabled' => false, 'TagsFilterEnabled' => false]);
        $html = $this->renderHolder('FilterableArchiveFilter');

        $this->assertDoesNotMatchRegularExpression('/name="cat"/', $html);
        $this->assertDoesNotMatchRegularExpression('/name="tag"/', $html);
        $this->assertMatchesRegularExpression('/<select[^>]+name="date"/', $html);
    }

    /**
     * Regression, two template defects in the same form: the tag wrapper closed its class
     * attribute before the "filtering" classes (so they rendered as stray attributes), and the
     * tag and date wrappers read the active filter from the dropdown's scope instead of $Up (so
     * the classes never rendered at all). The category wrapper had it right all along.
     */
    public function testFilterFormMarksTheActiveFilters()
    {
        $this->buildArchive();
        $html = $this->renderHolder('FilterableArchiveFilter', ['cat' => 'press', 'tag' => 'blue', 'date' => '2024']);

        $this->assertMatchesRegularExpression('/class="[^"]*cat-filter cat-filtering curr-cat-press[^"]*"/', $html);
        $this->assertMatchesRegularExpression('/class="[^"]*tag-filter tag-filtering curr-tag-blue[^"]*"/', $html);
        $this->assertMatchesRegularExpression('/class="[^"]*date-filter currently-filtering curr-date-2024[^"]*"/', $html);
        # One reset link per active filter
        $this->assertSame(3, substr_count($html, '&times;'));
    }

    public function testPaginationTemplates()
    {
        $this->buildArchive(['ItemsPerPage' => 2]);

        $html = $this->renderHolder('FilterableArchivePagination');
        $this->assertStringContainsString('id="pageNumbers"', $html);
        $this->assertStringContainsString('pages current">1<', $html);
        $this->assertStringContainsString('class="pages next"', $html);
        $this->controller->popCurrent();

        $html = $this->renderHolder('FilterableArchiveBootstrapPagination');
        $this->assertStringContainsString('class="pagination"', $html);
        $this->assertStringContainsString('title="View page number 2"', $html);
    }

    public function testPaginationIsSilentOnASinglePage()
    {
        $this->buildArchive(['ItemsPerPage' => 10]);
        $this->assertSame('', trim($this->renderHolder('FilterableArchivePagination')));
    }

    public function testFilterablePropertiesOfAnItem()
    {
        $this->buildArchive();
        $html = (string) $this->items['may2024']->customise(['LinkFilterProps' => true])->renderWith('FilterableProperties');

        $this->assertStringContainsString('3 May 2024', $html);
        $this->assertStringContainsString('Press', $html);
        $this->assertStringContainsString('Blue', $html);
        $this->assertStringContainsString('Green', $html);
        $this->assertStringContainsString('href="' . $this->props['green']->getLink() . '"', $html);
    }
}
