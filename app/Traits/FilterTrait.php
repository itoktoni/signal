<?php

namespace App\Traits;

trait FilterTrait
{
    /**
     * Apply filters to the query based on request parameters.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return void
     */
    public function applyFilters($query, array $filters)
    {
        foreach ($filters as $key => $field) {
            if (is_numeric($key)) {
                // Simple filter: field name
                $value = request($field);
                if ($value) {
                    $query->where($field, 'like', '%'.$value.'%');
                }
            } else {
                // Advanced filter: key is param, field is the column or special
                if ($key === 'search' && $field === 'filter') {
                    $filterField = request('filter');
                    $searchValue = request('search');
                    if ($filterField && $searchValue && $filterField !== 'All Filter') {
                        $query->where($this->mapFilterField($filterField), 'like', '%'.$searchValue.'%');
                    }
                }
            }
        }
    }

    /**
     * Map filter field names to database columns.
     *
     * @param  string  $field
     * @return string
     */
    protected function mapFilterField($field)
    {
        $map = [
            'Username' => 'name',
            'Role' => 'role',
            // Add more mappings as needed
        ];

        return $map[$field] ?? $field;
    }
}
