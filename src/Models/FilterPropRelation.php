<?php

namespace Restruct\SilverStripe\FilterableArchive;

use SilverStripe\ORM\DataObject;

class FilterPropRelation extends DataObject
{
    private static $table_name = 'FilterPropRelation';

    private static $has_one = [
        'Item' => DataObject::class, // Polymorphic has_one
        'Category' => FilterProp::class,
        'Tag' => FilterProp::class,
    ];
}