<?php

namespace Tests\Unit;

use App\FormDataBinder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FormDataBinderTest extends TestCase
{
    use RefreshDatabase;
    public function test_bind_and_get()
    {
        $model = (object) ['name' => 'John', 'email' => 'john@example.com'];

        FormDataBinder::bind($model);

        $this->assertEquals($model, FormDataBinder::get());
    }

    public function test_pop()
    {
        $model1 = (object) ['name' => 'John'];
        $model2 = (object) ['name' => 'Jane'];

        FormDataBinder::bind($model1);
        FormDataBinder::bind($model2);

        $this->assertEquals($model2, FormDataBinder::get());

        FormDataBinder::pop();

        $this->assertEquals($model1, FormDataBinder::get());
    }
}