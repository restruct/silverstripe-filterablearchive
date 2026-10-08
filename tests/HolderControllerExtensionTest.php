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
use SilverStripe\Core\Config\Config;
use SilverStripe\Dev\SapphireTest;

/**
 * Behavioural tests for HolderControllerExtension: reading the active filter from the request,
 * filtering the holder's items by date, category and tag, and paginating the result.
 *
 * Compatibility note: runs under PHPUnit 9 (Silverstripe 5) and PHPUnit 11 (Silverstripe 6);
 * no doc-comment metadata, no assertions removed after PHPUnit 9.
 */
class HolderControllerExtensionTest extends SapphireTest
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

    /**
     * A controller for the archive's holder, handling a request with the given GET vars and
     * URL route params (what the url_handlers would have extracted).
     */
    private function controllerFor(array $getVars = [], array $routeParams = []): FAHolderController
    {
        $request = new HTTPRequest('GET', 'news', $getVars);
        $request->setSession(new Session([]));
        $request->setRouteParams($routeParams);
        $controller = FAHolderController::create($this->holder);
        $controller->setRequest($request);

        return $controller;
    }

    private function titles($list): array
    {
        return $list->column('Title');
    }

    # ---------------------------------------------------------------- routing config

    public function testActionsAndUrlHandlersAreDeclared()
    {
        $actions = Config::inst()->get(FAHolderController::class, 'allowed_actions');
        foreach (['date', 'tag', 'cat', 'archive'] as $action) {
            $this->assertContains($action, $actions);
        }

        $handlers = Config::inst()->get(FAHolderController::class, 'url_handlers');
        $this->assertSame('date', $handlers['date/$Date!']);
        $this->assertSame('tag', $handlers['tag/$Tag!']);
        $this->assertSame('cat', $handlers['cat/$Category!']);
        $this->assertSame('date', $handlers['archive/$Year!/$Month/$Day']);
    }

    # ---------------------------------------------------------------- reading the filter

    public function testTheFilterIsReadFromGetVarsOrRouteParams()
    {
        $this->buildArchive();

        $controller = $this->controllerFor(['date' => '2024', 'cat' => 'press', 'tag' => 'blue']);
        $this->assertSame('2024', $controller->getFilteredDate());
        $this->assertSame('press', $controller->getFilteredCatSegment());
        $this->assertSame('blue', $controller->getFilteredTagSegment());

        $controller = $this->controllerFor([], ['Date' => '2023-01', 'Category' => 'events', 'Tag' => 'green']);
        $this->assertSame('2023-01', $controller->getFilteredDate());
        $this->assertSame('events', $controller->getFilteredCatSegment());
        $this->assertSame('green', $controller->getFilteredTagSegment());
    }

    /**
     * Regression: the legacy archive/$Year!/$Month/$Day route still maps to date(), but only
     * $Date was ever read, so an old archive/2024/05 link silently showed every item.
     */
    public function testTheLegacyArchiveRouteStillFilters()
    {
        $this->buildArchive();

        $request = new HTTPRequest('GET', 'archive/2024/05');
        $params = $request->match('archive/$Year!/$Month/$Day', true);
        $this->assertSame('2024', $params['Year'], 'the route extracts the year');

        $controller = $this->controllerFor([], $params);
        $this->assertSame('2024-05', $controller->getFilteredDate());
        $this->assertEqualsCanonicalizing(['may2024', 'may2024b'], $this->titles($controller->getFilteredArchiveItems()));
    }

    # ---------------------------------------------------------------- filtering

    public function testNoFilterReturnsEveryItem()
    {
        $this->buildArchive();
        $this->assertSame(['may2024b', 'may2024', 'jan2023'], $this->titles($this->controllerFor()->getFilteredArchiveItems()));
    }

    public function testFilterByYearMonthAndDay()
    {
        $this->buildArchive();

        $this->assertEqualsCanonicalizing(['may2024', 'may2024b'], $this->titles($this->controllerFor(['date' => '2024'])->getFilteredArchiveItems()));
        $this->assertSame(['jan2023'], $this->titles($this->controllerFor(['date' => '2023-01'])->getFilteredArchiveItems()));
        $this->assertSame([], $this->titles($this->controllerFor(['date' => '2023-02'])->getFilteredArchiveItems()));
        $this->assertSame(['may2024'], $this->titles($this->controllerFor(['date' => '2024-05-03'])->getFilteredArchiveItems()));
    }

    /**
     * Issue #4: the period is matched as a range on the stored value, start inclusive and the next
     * period's start exclusive, so the last second of a period stays in and the first second of
     * the next one stays out.
     */
    public function testTheDateFilterIncludesThePeriodBoundaries()
    {
        $this->buildArchive();
        foreach (['newyear' => '2023-12-31 23:59:59', 'newyear2' => '2024-01-01 00:00:00', 'endmay' => '2024-05-31 23:59:59'] as $key => $date) {
            FAItem::create(['Title' => $key, 'URLSegment' => $key, 'ParentID' => $this->holder->ID, 'Date' => $date])->write();
        }

        $this->assertEqualsCanonicalizing(['jan2023', 'newyear'], $this->titles($this->controllerFor(['date' => '2023'])->getFilteredArchiveItems()));
        $this->assertSame(['newyear'], $this->titles($this->controllerFor(['date' => '2023-12'])->getFilteredArchiveItems()));
        $this->assertSame(['newyear2'], $this->titles($this->controllerFor(['date' => '2024-01'])->getFilteredArchiveItems()));
        $this->assertEqualsCanonicalizing(['may2024', 'may2024b', 'endmay'], $this->titles($this->controllerFor(['date' => '2024-05'])->getFilteredArchiveItems()));
        $this->assertSame(['endmay'], $this->titles($this->controllerFor(['date' => '2024-05-31'])->getFilteredArchiveItems()));
        $this->assertSame(['newyear'], $this->titles($this->controllerFor(['date' => '2023-12-31'])->getFilteredArchiveItems()));
        # Single-digit month and day, as YEAR()/MONTH()/DAY() matched them
        $this->assertSame(['may2024'], $this->titles($this->controllerFor(['date' => '2024-5-3'])->getFilteredArchiveItems()));
    }

    /**
     * Issue #4: a date-only managed_object_date_field (Date, not Datetime) filters the same way.
     */
    public function testTheDateFilterWorksOnADateOnlyField()
    {
        Config::modify()->set(FAHolder::class, 'managed_object_date_field', 'PublishDate');
        $this->buildArchive();
        $this->items['jan2023']->PublishDate = '2022-12-31';
        $this->items['jan2023']->write();
        $this->items['may2024']->PublishDate = '2023-01-01';
        $this->items['may2024']->write();

        $this->assertSame(['jan2023'], $this->titles($this->controllerFor(['date' => '2022'])->getFilteredArchiveItems()));
        $this->assertSame(['may2024'], $this->titles($this->controllerFor(['date' => '2023-01'])->getFilteredArchiveItems()));
        $this->assertSame(['may2024'], $this->titles($this->controllerFor(['date' => '2023-01-01'])->getFilteredArchiveItems()));
        $this->assertSame([], $this->titles($this->controllerFor(['date' => '2024'])->getFilteredArchiveItems()));
    }

    /**
     * Issue #4: a date that names no real period matches nothing, as YEAR()/MONTH()/DAY() = x did
     * on MySQL, rather than erroring or falling back to every item.
     */
    public function testADateThatNamesNoRealPeriodMatchesNothing()
    {
        $this->buildArchive();
        foreach (['2024-13', '2024-00', '2024-02-30', '2024-05-32', 'abcd', '2024-ab'] as $date) {
            $this->assertSame([], $this->titles($this->controllerFor(['date' => $date])->getFilteredArchiveItems()), "date=$date");
        }
    }

    /**
     * Issue #4 regression guard: the range bounds go through DBDate when the ORM formats the filter
     * value, and DBDate cannot parse a year below 1000 (or the 10000-01-01 end bound of year 9999)
     * and throws. Such a date, like the other malformed forms here, must match nothing rather than
     * turn a public date/ or archive/ URL into a server error (3.1.0 returned no items for these).
     */
    public function testAnOutOfRangeOrMalformedDateMatchesNothingWithoutThrowing()
    {
        $this->buildArchive();
        foreach (['0050', '0001', '0999', '9999', '0000', '10000', '2024--05', '2024-05-03x'] as $date) {
            $this->assertSame([], $this->titles($this->controllerFor(['date' => $date])->getFilteredArchiveItems()), "date=$date");
        }
    }

    /**
     * Issue #4 pin for CI, which only runs MySQL/MariaDB: YEAR(), MONTH() and DAY() do not exist on
     * PostgreSQL or SQLite, so the date filter must not reach the database through them.
     */
    public function testTheDateFilterUsesNoMysqlOnlyDateFunctions()
    {
        $this->buildArchive();
        $sql = $this->controllerFor(['date' => '2024-05-03'])->getFilteredArchiveItems()->sql();
        foreach (['YEAR(', 'MONTH(', 'DAY('] as $function) {
            $this->assertStringNotContainsString($function, strtoupper($sql));
        }
    }

    public function testDateFilterIsIgnoredWhileTheArchiveIsOff()
    {
        $this->buildArchive(['DateFilterEnabled' => false]);
        $this->assertCount(3, $this->titles($this->controllerFor(['date' => '2023'])->getFilteredArchiveItems()));
    }

    public function testFilterByCategoryAndTag()
    {
        $this->buildArchive();

        $this->assertSame(['jan2023'], $this->titles($this->controllerFor(['cat' => 'events'])->getFilteredArchiveItems()));
        $this->assertEqualsCanonicalizing(['may2024', 'jan2023'], $this->titles($this->controllerFor(['tag' => 'blue'])->getFilteredArchiveItems()));
        $this->assertSame(['may2024'], $this->titles($this->controllerFor(['tag' => 'green'])->getFilteredArchiveItems()));
    }

    public function testFiltersCombine()
    {
        $this->buildArchive();
        $this->assertSame(['may2024'], $this->titles($this->controllerFor(['tag' => 'blue', 'date' => '2024'])->getFilteredArchiveItems()));
        $this->assertSame([], $this->titles($this->controllerFor(['cat' => 'events', 'date' => '2024'])->getFilteredArchiveItems()));
    }

    public function testACategoryWithNoItemsFiltersEverythingOut()
    {
        $this->buildArchive();
        $empty = \Restruct\SilverStripe\FilterableArchive\FilterProp::create(['Title' => 'Empty', 'CatHolderPageID' => $this->holder->ID]);
        $empty->write();

        $this->assertSame([], $this->titles($this->controllerFor(['cat' => 'empty'])->getFilteredArchiveItems()));
    }

    public function testAnUnknownCategoryOrTagDoesNotFilter()
    {
        $this->buildArchive();
        # Documented behaviour: a segment that matches none of this holder's categories/tags is ignored
        $this->assertCount(3, $this->titles($this->controllerFor(['cat' => 'nope', 'tag' => 'nope'])->getFilteredArchiveItems()));
    }

    # ---------------------------------------------------------------- pagination

    public function testPaginatedItemsUseItemsPerPage()
    {
        $this->buildArchive(['ItemsPerPage' => 2]);
        $paginated = $this->controllerFor()->PaginatedItems();

        $this->assertSame(2, $paginated->getPageLength());
        $this->assertSame(3, (int) $paginated->getTotalItems());
        $this->assertSame(2, (int) $paginated->TotalPages());
        $this->assertCount(2, $paginated->toArray());
    }

    public function testZeroItemsPerPageMeansOnePage()
    {
        $this->buildArchive(['ItemsPerPage' => 0]);
        $paginated = $this->controllerFor()->PaginatedItems();
        $this->assertSame(1, (int) $paginated->TotalPages());
        $this->assertCount(3, $paginated->toArray());

        # And an empty result must not divide by zero
        $paginated = $this->controllerFor(['date' => '1999'])->PaginatedItems();
        $this->assertCount(0, $paginated->toArray());
    }

    public function testPaginatedItemsHonourTheFilter()
    {
        $this->buildArchive(['ItemsPerPage' => 10]);
        $this->assertSame(['jan2023'], $this->titles($this->controllerFor(['date' => '2023'])->PaginatedItems()));
    }
}
