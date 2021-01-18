<?php

namespace Restruct\SilverStripe\FilterableArchive;

use SilverStripe\ORM\DataObject;

class FilterMapping extends DataObject
{
    private static $table_name = 'FilterMapping';

    private static $has_one = [
        'Item' => DataObject::class, // Polymorphic has_one
        'Tag'  => FilterTag::class,
        'Cat'  => FilterCategory::class,
    ];
}