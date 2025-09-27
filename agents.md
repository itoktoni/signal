<<<<<<< HEAD
# Crypto Analyst AGENTS.md
=======
# My Project AGENTS.md
>>>>>>> 17a0eb08f4524e7884b116c79fd6f58b7e626383

## General Guidelines
- **Language/Framework:** this is laravel framework 12.
- **Styling:** Use chotta CSS for utility-first styling. Avoid custom CSS files where possible.

## Development Environment
- **this using laravel 12

## Code Style and Formatting
- if we make have if make like
- Function brackets: Place opening brace on the same line as the function declaration.
  Example:
<<<<<<< HEAD
  ```php
=======
>>>>>>> 17a0eb08f4524e7884b116c79fd6f58b7e626383
  public function index()
  {
      return redirect()->route($this->module('getData'));
  }
<<<<<<< HEAD
  ```
=======
>>>>>>> 17a0eb08f4524e7884b116c79fd6f58b7e626383

## Component Usage Examples
- **Input Component:** `<x-input type="email" name="email" col="12" required/>`
- **Select Component:** `<x-select name="role" :options="$users" option-key="id" option-value="name" required searchable multiple/>`
- **Textarea Component:** `<x-textarea name="description" rows="4" required/>`
- **Footer Component:** `<x-footer> <x-button type="submit" class="primary">Submit</x-button> </x-footer>`
- **Sort Link Component:** `<x-sort-link column="id" route="user.getData" text="ID" />`
- prefer to make like
<x-input type="email" name="email" col="12" required/>

## JavaScript Guidelines
- Move all page-specific JavaScript to `resources/js/app.js` instead of inline scripts in Blade views.
- Use global functions for reusable logic like confirmations.
- Wrap page-specific code in checks for element existence.
