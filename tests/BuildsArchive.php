<?php

namespace Restruct\SilverStripe\FilterableArchive\Tests;

use Restruct\SilverStripe\FilterableArchive\FilterProp;
use Restruct\SilverStripe\FilterableArchive\Tests\Stub\FAHolder;
use Restruct\SilverStripe\FilterableArchive\Tests\Stub\FAItem;

/**
 * Builds a small archive in code: one holder, three dated items, two categories and two tags.
 *
 * A trait rather than a shared base class on purpose. SOP: never declare an abstract class in a
 * module's tests/ that the manifest could pick up as a DataObject; a trait keeps that whole
 * question away.
 */
trait BuildsArchive
{
    /** @var FAHolder */
    protected $holder;

    /** @var FAItem[] keyed by a short name */
    protected $items = [];

    /** @var FilterProp[] keyed by URLSegment */
    protected $props = [];

    protected function buildArchive(array $holderFields = []): FAHolder
    {
        $this->holder = FAHolder::create(array_merge([
            'Title' => 'News',
            'URLSegment' => 'news',
            'DateFilterEnabled' => true,
            'CategoriesFilterEnabled' => true,
            'TagsFilterEnabled' => true,
        ], $holderFields));
        $this->holder->write();

        $dates = [
            'jan2023' => '2023-01-15 10:00:00',
            'may2024' => '2024-05-03 09:30:00',
            'may2024b' => '2024-05-20 14:00:00',
        ];
        foreach ($dates as $key => $date) {
            $item = FAItem::create([
                'Title' => $key,
                'URLSegment' => $key,
                'ParentID' => $this->holder->ID,
                'Date' => $date,
            ]);
            $item->write();
            $this->items[$key] = $item;
        }

        # Categories hang off the holder through CatHolderPage, tags through TagHolderPage
        foreach (['Events' => 'CatHolderPageID', 'Press' => 'CatHolderPageID', 'Blue' => 'TagHolderPageID', 'Green' => 'TagHolderPageID'] as $title => $key) {
            $prop = FilterProp::create(['Title' => $title, $key => $this->holder->ID]);
            $prop->write();
            $this->props[$prop->URLSegment] = $prop;
        }

        # jan2023: Events + Blue; may2024: Press + Blue + Green; may2024b: nothing
        $this->items['jan2023']->Categories()->add($this->props['events']);
        $this->items['jan2023']->Tags()->add($this->props['blue']);
        $this->items['may2024']->Categories()->add($this->props['press']);
        $this->items['may2024']->Tags()->add($this->props['blue']);
        $this->items['may2024']->Tags()->add($this->props['green']);

        return $this->holder;
    }
}
