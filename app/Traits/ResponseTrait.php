<?php

namespace App\Traits;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

trait ResponseTrait
{
    /**
     * Return view or JSON response based on request type
     *
     * @param string $view
     * @param array $data
     * @param int $status
     * @return \Illuminate\Http\Response|\Illuminate\View\View
     */
    protected function views(string $view, array $data = [], int $status = 200)
    {
        $request = app(Request::class);

        if ($request->expectsJson()) {
            return response()->json($data, $status);
        }

        return view($view, $this->share($data));
    }


    /**
     * Get automatic view module from controller class and method names
     *
     * @return string
     */
    protected function module($function = null)
    {
        // Get the class name (e.g., UserController)
        $className = class_basename(get_class($this));

        // Remove 'Controller' suffix and convert to lowercase
        $module = strtolower(str_replace('Controller', '', $className));

        // Get the method name (e.g., getCreate)
        $method = debug_backtrace()[1]['function'];

        // Remove 'get' or 'post' prefix and convert to lowercase
        $action = strtolower(preg_replace('/^(get|post)/', '', $method));

        if($function)
        {
            $action = $function;
        }

        return $module . '.' . $action;
    }


    /**
     * Create a new user with validation and return appropriate response
     *
     * @param Request $request
     * @param string $redirectRoute
     * @param string $successMessage
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    protected function create(array $data, string $redirectRoute = 'user.index', string $successMessage = 'created successfully')
    {
        $validate = request()->validate($this->model->rules, $this->model->messages ?? []);

        $data = $this->model->create(array_merge($data, $validate));

        if (request()->expectsJson()) {
            return response()->json([
                'data' => $data,
                'message' => $successMessage
            ], 201);
        }

        return redirect()->route($redirectRoute)->with('success', $successMessage);
    }

    protected function update(array $data, $model, string $redirectRoute = 'user.index', string $successMessage = 'updated successfully')
    {
        $validate = request()->validate([
            'name' => 'sometimes|required|string|max:255',
            'email' => ['sometimes', 'required', 'email', 'max:255', \Illuminate\Validation\Rule::unique('users')->ignore($model->id)],
        ], $this->model->messages ?? []);

        $model->update(array_merge($data, $validate));

        if (request()->expectsJson()) {
            return response()->json([
                'data' => $model,
                'message' => $successMessage
            ]);
        }

        return redirect()->route($redirectRoute)->with('success', $successMessage);
    }

    protected function share(array $data = [])
    {
        return array_merge([
            'model' => false
        ], $data);
    }

}