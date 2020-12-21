<?php

namespace Restruct\Silverstripe\FilterableArchive;

use SilverStripe\Core\Config\Config;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\Core\Convert;
use SilverStripe\Core\Extension;
use SilverStripe\ORM\DataList;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\PaginatedList;
use SilverStripe\ORM\FieldType\DBField;

/**
 * Class FilterableArchiveHolderControllerExtension
 *
 * @TODO: make filtering by Date and Tag and Cat work simultaneously (switch to form.submit + GET vars in URL instead of URL params)
 *
 * @package Restruct\Silverstripe\FilterableArchive
 */
class FilterableArchiveHolderControllerExtension extends Extension
{
    private static $allowed_actions = array(
        'archive', # renamed to 'date'
        'date',
        'tag',
        'cat'
    );

    private static $url_handlers = array(
        'archive/$Year!/$Month/$Day' => 'date', # renamed to 'date'
        'date/$Year!/$Month/$Day' => 'date',
        'tag/$Tag!' => 'tag',
        'cat/$Category!' => 'cat',
    );
    
    /**
     * Renders an archive for a specificed date. This can be by year or year/month
     *
     * @return HTTPResponse
    **/
    public function date()
    {
        $year = $this->owner->getArchiveYear();
        $month = $this->owner->getArchiveMonth();
        $day = $this->owner->getArchiveDay();

        // If an invalid month has been passed, we can return a 404.
        if ($this->owner->request->param("Month") && !$month) {
            return $this->owner->httpError(404, "Not Found");
        }

        // Check for valid day
        if ($month && $this->owner->request->param("Day") && !$day) {
            return $this->owner->httpError(404, "Not Found");
        }

        if ($year) {
            $this->owner->Items = $this->owner->getDateFilteredArchiveItems($year, $month, $day);
            return $this->owner->render();
        } else {
            return $this->owner->redirect($this->owner->AbsoluteLink(), 303); //301: movedperm, 302: movedtemp, 303: see other
        }
    }
    
    /**
     * Renders the blog posts for a given tag.
     *
     * @return HTTPResponse
    **/
    public function tag()
    {
        $tag = $this->owner->getCurrentTag();
        if ($tag) {
            $this->owner->Items = $tag->Pages();
            return $this->owner->render();
        }
        return $this->owner->httpError(404, "Not Found");
    }
    /**
     * Renders the blog posts for a given category
     *
     * @return HTTPResponse
    **/
    public function cat()
    {
        $category = $this->owner->getCurrentCategory();
        if ($category) {
            $this->owner->Items = $category->Pages();
            return $this->owner->render();
        }
        return $this->owner->httpError(404, "Not Found");
    }

    /**
     * Returns items for a given date period.
     *
     * @param $year int
     * @param $month int
     * @param $dat int
     *
     * @return DataList
     **/
    public function getFilteredArchiveItemsNew()
    {
        /** @var DataList $items */
        $items = $this->owner->getItems();
//        $class = Config::inst()->get($this->owner->className, 'managed_object_class');

        // get items filtered by date and then filter by cat
        $filteredDate = $this->owner->request->requestVar('date');
        if($this->owner->ArchiveActive() && $filteredDate){
            list($year, $month, $day) = array_pad(explode('-', $filteredDate), 3, null);
            $dateField = Config::inst()->get($this->owner->className, 'managed_object_date_field');

            $dateFilter = [];
            if($year) $dateFilter["YEAR(\"{$dateField}\")"] = $year;
            if($month) $dateFilter["MONTH(\"{$dateField}\")"] = $month;
            if($day) $dateFilter["DAY(\"{$dateField}\")"] = $day;
            if(count($dateFilter)){
                $items = $items->where($dateFilter);
            }
        }
        // filter by Cat
        $category = $this->owner->request->requestVar('cat');
        if($category && $catObj = $this->owner->Categories()->filter("URLSegment", $category)->first()) {
            $IDs = $catObj->MappedPages()->column('ItemID');
            $items = $items->filter('ID', count($IDs) ? $IDs : -1);
        }
        // filter by Tag
        $tag = $this->owner->request->requestVar('tag');
        if($tag && $tagObj = $this->owner->Tags()->filter("URLSegment", $tag)->first()) {
            $IDs = $tagObj->MappedPages()->column('ItemID');
            $items = $items->filter('ID', count($IDs) ? $IDs : -1);
        }

//        $items = new PaginatedList($this->Items, $this->request);
//        // If pagination is set to '0' then no pagination will be shown.
//        if($this->ItemsPerPage > 0) $items->setPageLength($this->ItemsPerPage);
//        else $items->setPageLength( max(array($this->owner->getItems()->count(), 10)) );
//
//        $this->PaginatedItems = $items;

        return $items;
    }

//    /**
//     * Returns items for a given date period.
//     *
//     * @param $year int
//     * @param $month int
//     * @param $dat int
//     *
//     * @return DataList
//     **/
//    public function getFilteredArchiveItems($year, $month = null, $day = null, $items = null) {
//        $class = $this->owner->config()->managed_object_class;
//        $dateField = $this->owner->config()->managed_object_date_field;
//        if(!$items) $items = $this->owner->getItems();
//
//        // if Items = ArrayList (not DataList), filterby callback (cause ->where() doesn't exist on ArrayList)
//        if(get_class($items)=="DataList"){
//
//            if($month) {
//                if($day) {
//                    return $items
//                        ->where("DAY({$dateField}) = '" . Convert::raw2sql($day) . "'
//								AND MONTH({$dateField}) = '" . Convert::raw2sql($month) . "'
//								AND YEAR({$dateField}) = '" . Convert::raw2sql($year) . "'");
//                }
//                return $items
//                    ->where("MONTH({$dateField}) = '" . Convert::raw2sql($month) . "'
//							AND YEAR({$dateField}) = '" . Convert::raw2sql($year) . "'");
//            } else {
//                return $items->where("YEAR({$dateField}) = '" . Convert::raw2sql($year) . "'");
//            }
//
//            // ArrayList, filter by callback
//        } else {
//
//            if($month) {
//                if($day) {
//                    return $items->filterByCallback(
//                        function($item, $list) use ($dateField, $year, $month, $day) {
//                            $date = new DateTime($item->{$dateField});
//                            return ($date->format('d')==$day
//                                && $date->format('m')==$month && $date->format('Y')==$year);
//                        }
//                    );
//                }
//                return $this->getItems()->filterByCallback(
//                    function($item, $list) use ($dateField, $year, $month, $day) {
//                        $date = new DateTime($item->{$dateField});
//                        return ($date->format('m')==$month && $date->format('Y')==$year);
//                    }
//                );
//            } else {
//                return $this->getItems()->filterByCallback(
//                    function($item, $list) use ($dateField, $year, $month, $day) {
//                        $date = new DateTime($item->{$dateField});
//                        return ($date->format('Y')=="$year");
//                    }
//                );
//            }
//
//        }
//    }

//    public function getItemsFilteredByCatOrTag($catOrTagObj, $items = null) {
//        $class = $this->owner->config()->managed_object_class;
//        if(!$items) $items = $this->owner->getItems();
//
//        // if Items = ArrayList (not DataList), filterby callback (cause ->where() doesn't exist on ArrayList)
//        // TMP fix: somehow FW insists on filtering on 'DataObject' instead of going through many_many() for the correct class...
//        if(get_class($items)=="DataList"){
////            var_dump($catOrTagObj->many_many('Items'));die();
//            $IDs = $catOrTagObj->Items()->column('ID');
////            $IDs = $catOrTagObj->getManyManyComponents('Items')->column('ID');
////            var_dump($IDs);die();
//            return $items->filter('ID', $IDs);
//
//            // ArrayList, filter by callback ($catOrTag has to have an ID for this to work)
//        } else {
//            return $items->filterByCallback(
//                function($item, $list) use ($catOrTagObj) {
//                    if(get_class($catOrTagObj)=="Filterable_Category"){
//                        $hasToBeIn = $item->Categories()->column('ID');
//                    } else {
//                        $hasToBeIn = $item->Tags()->column('ID');
//                    }
//                    return in_array($catOrTagObj->ID, $hasToBeIn);
//                });
//        }
//    }
    
    /**
     * Returns a list of paginated blog posts based on the blogPost dataList
     *
     * @return PaginatedList
    **/
    public function PaginatedItems()
    {
//        $items = new PaginatedList($this->owner->Items, $this->owner->request);
        $items = new PaginatedList($this->owner->getFilteredArchiveItemsNew(), $this->owner->request);
        // If pagination is set to '0' then no pagination will be shown.
        if ($this->owner->ItemsPerPage > 0) {
            $items->setPageLength($this->owner->ItemsPerPage);
        } else {
            $items->setPageLength($this->owner->getItems()->count());
        }
        return $items;
    }
    
//    /**
//     * Fetches the archive year from the url
//     *
//     * @return int|null
//    **/
//    public function getArchiveYear()
//    {
//        $year = $this->owner->request->param("Year");
//        $dateGetParam = $this->owner->request->getVar('date');
//        if (preg_match("/^[0-9]{4}$/", $year)) {
//            return (int) $year;
//        }
//        return null;
//    }
//
//    /**
//     * Fetches the archive money from the url.
//     *
//     * @return int|null
//    **/
//    public function getArchiveMonth()
//    {
//        $month = $this->owner->request->param("Month");
//        if (preg_match("/^[0-9]{1,2}$/", $month)) {
//            if ($month > 0 && $month < 13) {
//                // Check that we have a valid date.
//                if (checkdate($month, 01, $this->owner->getArchiveYear())) {
//                    return (int) $month;
//                }
//            }
//        }
//        return null;
//    }
//
//    /**
//     * Fetches the archive day from the url
//     *
//     * @return int|null
//    **/
//    public function getArchiveDay()
//    {
//        $day = $this->owner->request->param("Day");
//        if (preg_match("/^[0-9]{1,2}$/", $day)) {
//
//            // Check that we have a valid date
//            if (checkdate($this->owner->getArchiveMonth(), $day, $this->owner->getArchiveYear())) {
//                return (int) $day;
//            }
//        }
//        return null;
//    }
//    /**
//     * Tag Getter for use in templates.
//     *
//     * @return FilterTag|null
//     **/
//    public function getCurrentTag()
//    {
//        // get from URL param or GET param
//        $tag = $this->owner->request->param("Tag");
//        if(!$tag) $tag = $this->owner->request->getVar('tag');
//        if ($tag) {
//            return $this->owner->Tags()
//                ->filter("URLSegment", $tag)
//                ->first();
//        }
//        return null;
//    }
//    /**
//     * Category Getter for use in templates.
//     *
//     * @return FilterCategory|null
//     **/
//    public function getCurrentCategory()
//    {
//        // get from URL param or GET param
//        $category = $this->owner->request->param("Category");
//        if(!$category) $category = $this->owner->request->requestVar('cat');
//        if ($category) {
//            return $this->owner->Categories()
//                ->filter("URLSegment", $category)
//                ->first();
//        }
//        return null;
//    }

//    /**
//     * Returns the current archive date.
//     *
//     * @return Date
//    **/
//    public function getArchiveDate()
//    {
//        $year = $this->owner->getArchiveYear();
//        $month = $this->owner->getArchiveMonth();
//        $day = $this->owner->getArchiveDay();
//
//        if ($year) {
//            if ($month) {
//                $date = $year . '-' . $month . '-01';
//                if ($day) {
//                    $date = $year . '-' . $month . '-' . $day;
//                }
//            } else {
//                $date = $year . '-01-01';
//            }
//            return DBField::create_field("Date", $date);
//        }
//    }
}
