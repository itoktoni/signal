<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Builder;

trait Filterable
{
    /**
     * Apply filters based on request parameters.
     *
     * @param  \Illuminate\Http\Request  $request
     */
    public function scopeFilter(Builder $query, $request): Builder
    {
        // Apply filters for fields in $filterable
        if (property_exists($this, 'filterable') && is_array($this->filterable)) {
            foreach ($this->filterable as $field) {
                if ($request->filled($field)) {
                    $value = $request->$field;
                    if ($field === 'role' && $value === 'All Roles') {
                        continue; // Skip "All Roles"
                    }
                    $this->applyFieldFilter($query, $field, $value);
                }
            }
        }

        // Filter by search term based on filter field
        if ($request->filled('search')) {

            $field = $request->filter;

            if($field)
            {
                $query->where($field, 'like', '%'.$request->search.'%');
            }
            else
            {
                $query->where(self::getKeyName(), 'like', '%'.$request->search.'%');
            }
        }

        // Apply sorting
        if (property_exists($this, 'sortable') && is_array($this->sortable) && $request->filled('sort') && in_array($request->sort, $this->sortable)) {
            $direction = $request->direction === 'desc' ? 'desc' : 'asc';
            $query->orderBy($this->mapField($request->sort), $direction);
        }

        return $query;
    }

    /**
     * Apply filter for a specific field.
     * Can be overridden in the model for custom logic.
     *
     * @param  string  $field
     * @param  mixed  $value
     * @return void
     */
    protected function applyFieldFilter(Builder $query, $field, $value)
    {
        $column = $this->mapField($field);
        $query->where($column, 'like', '%'.$value.'%');
    }

    /**
     * Map field names to database columns.
     *
     * @param  string  $field
     * @return string
     */
    protected function mapField($field)
    {
        $map = [
            'username' => 'name',
            // Add more mappings as needed
        ];

        return $map[$field] ?? $field;
    }
}
