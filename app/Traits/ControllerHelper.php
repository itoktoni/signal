<?php

namespace App\Traits;

use Illuminate\Http\Request;

trait ControllerHelper
{
    /**
     * Return view or JSON response based on request type
     *
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
    public function module($function = null)
    {
        // Get the class name (e.g., UserController)
        $className = class_basename(get_class($this));

        // Remove 'Controller' suffix and convert to lowercase
        $module = strtolower(str_replace('Controller', '', $className));

        // Get the method name (e.g., getCreate)
        $method = debug_backtrace()[1]['function'];

        // Remove 'get' or 'post' prefix and convert to lowercase
        $action = strtolower(preg_replace('/^(get|post)/', '', $method));

        if ($function)
        {
            $action = $function;
        }

        return $module.'.'.$action;
    }

    /**
     * Create a new record with validation and return appropriate response
     *
     * @param  Request  $request
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    protected function create(array $data, $redirectRoute = null, $successMessage = null)
    {
        if (!$redirectRoute) {
            $redirectRoute = $this->module('getData');
        }
        if (!$successMessage) {
            $successMessage = ucfirst($this->getModuleName()) . ' created successfully';
        }

        if(method_exists($this->model, 'rules'))
        {
            $validate = request()->validate($this->model->rules(null, 'create'), $this->model->messages ?? []);
        }
        else
        {
            $validate = request()->all();
        }

        $data = $this->model->create(array_merge($data, $validate));

        $this->json($data, $successMessage, 201);

        return redirect()->route($redirectRoute)->with('success', $successMessage);
    }

    protected function update(array $data, $model, $redirectRoute = null, $successMessage = null)
    {
        if (!$redirectRoute) {
            $redirectRoute = $this->module('getData');
        }
        if (!$successMessage) {
            $successMessage = ucfirst($this->getModuleName()) . ' updated successfully';
        }

        $validate = request()->validate($this->model->rules($model->id, 'update'), $this->model->messages ?? []);

        $updateData = array_merge($data, $validate);

        // Remove password fields if empty
        if (empty($updateData['password'])) {
            unset($updateData['password'], $updateData['password_confirmation']);
        }

        $model->update($updateData);

        $this->json($model, $successMessage);

        return redirect()->route($redirectRoute)->with('success', $successMessage);
    }

    private function json($data, $message, $status = 200)
    {
        if (request()->expectsJson()) {
            return response()->json([
                'data' => $data,
                'message' => $message,
            ], $status);
        }
    }

    protected function share(array $data = [])
    {
        return array_merge([
            'model' => false,
            'context' => $this->getContext(),
        ], $data);
    }

    protected function getModel($code, $relations = [])
    {
        return $this->model->findOrFail($code)->with($relations);
    }

    /**
     * Get current controller context
     */
    protected function getContext(): array
    {
        $request = request();
        $route = $request->route();

        if (! $route) {
            return $this->getDefaultContext();
        }

        $controller = $route->getController();
        $className = get_class($controller);
        $shortName = class_basename($className);
        $controllerShort = str_replace('Controller', '', $shortName);
        $module = strtolower($controllerShort);
        $pluralModule = $this->pluralize($module);

        $action = $route->getActionMethod();
        $cleanAction = strtolower(preg_replace('/^(get|post)/', '', $action));

        return [
            'controller' => $shortName,
            'controller_short' => $controllerShort,
            'module' => $module,
            'module_plural' => $pluralModule,
            'action' => $cleanAction,
            'current_route' => $route->getName(),
            'is_create' => $cleanAction === 'create',
            'is_edit' => $cleanAction === 'update',
            'is_index' => in_array($cleanAction, ['index', 'data']),
            'is_show' => $cleanAction === 'show',
            'route_parameters' => $route->parameters(),
        ];
    }

    /**
     * Get default context when no route is available
     */
    private function getDefaultContext(): array
    {
        return [
            'controller' => null,
            'controller_short' => null,
            'module' => null,
            'module_plural' => null,
            'action' => null,
            'current_route' => null,
            'is_create' => false,
            'is_edit' => false,
            'is_index' => false,
            'is_show' => false,
            'route_parameters' => [],
        ];
    }

    /**
     * Get current controller name
     */
    protected function getControllerName(): ?string
    {
        return $this->getContext()['controller'];
    }

    /**
     * Get current controller short name (without Controller)
     */
    protected function getControllerShortName(): ?string
    {
        return $this->getContext()['controller_short'];
    }

    /**
     * Get current module name
     */
    protected function getModuleName(): ?string
    {
        return $this->getContext()['module'];
    }

    /**
     * Get current action name
     */
    protected function getActionName(): ?string
    {
        return $this->getContext()['action'];
    }

    /**
     * Check if current action is create
     */
    protected function isCreateAction(): bool
    {
        return $this->getContext()['is_create'];
    }

    /**
     * Check if current action is edit
     */
    protected function isEditAction(): bool
    {
        return $this->getContext()['is_edit'];
    }

    /**
     * Check if current action is index
     */
    protected function isIndexAction(): bool
    {
        return $this->getContext()['is_index'];
    }

    /**
     * Check if current action is show
     */
    protected function isShowAction(): bool
    {
        return $this->getContext()['is_show'];
    }

    /**
     * Get route parameters
     */
    protected function getRouteParameters(): array
    {
        return $this->getContext()['route_parameters'];
    }

    /**
     * Simple pluralization function
     */
    private function pluralize(string $word): string
    {
        if (substr($word, -1) === 'y') {
            return substr($word, 0, -1).'ies';
        } elseif (substr($word, -1) === 's' || substr($word, -2) === 'es') {
            return $word;
        } else {
            return $word.'s';
        }
    }
}
