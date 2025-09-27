@props(['field', 'model', 'label' => null])

<td data-label="{{ $label ?: ucfirst($field) }}">{{ $model->$field }}</td>