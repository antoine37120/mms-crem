<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Openplain\FilamentTreeView\Concerns\HasTreeStructure;

class DocumentationPage extends Model
{
    use HasTreeStructure;

    protected $fillable = [
        'title',
        'content',
        'parent_id',
        'order',
    ];

    public function parent()
    {
        return $this->belongsTo(DocumentationPage::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(DocumentationPage::class, 'parent_id');
    }
}
