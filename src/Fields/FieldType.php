<?php

declare(strict_types=1);

namespace IhumbakWooBulkEdit\Fields;

enum FieldType: string
{
    case Text = 'text';
    case Textarea = 'textarea';
    case Number = 'number';
    case Price = 'price';
    case Integer = 'integer';
    case Select = 'select';
    case Boolean = 'boolean';
    case Date = 'date';
    case Taxonomy = 'taxonomy';
    case Image = 'image';
    case Gallery = 'gallery';
    case CustomMeta = 'custom_meta';
}
