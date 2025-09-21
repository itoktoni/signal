A `.geminiignore` file is used to specify files and directories that Gemini should ignore. It uses glob patterns, similar to a `.gitignore` file.

Here is an example of a `.geminiignore` file:

```
# Ignore node_modules directory
node_modules/

# Ignore log files
*.log

# Ignore build artifacts
/public/build/
```

## Example: Creating a Simple Application

You can instruct Gemini to create a simple application. Here's an example of how you might ask Gemini to create a "Hello World" page in this Laravel project.

**User:** Create a new route in `routes/web.php` that responds to `/hello` and returns a simple "Hello World" message.

**Gemini's Action:** Gemini would then use its tools to modify the `routes/web.php` file and add the following code:

```php
Route::get('/hello', function () {
    return 'Hello World';
});
```

## Detailed Example: Building a Blog Application

Here's a more detailed, step-by-step example of how you could ask Gemini to build a simple blog within this Laravel project.

### Step 1: Create the Post Model and Migration

**User:** Create a new Eloquent model named `Post` and its corresponding migration file. The `posts` table should have a `title` (string), a `body` (text), and timestamps.

**Gemini's Action:** Gemini would run the following command:

```bash
php artisan make:model Post -m
```

Then, Gemini would modify the generated migration file in `database/migrations/` to add the `title` and `body` columns.

### Step 2: Create the Controller

**User:** Create a new controller named `PostController`.

**Gemini's Action:** Gemini would run:

```bash
php artisan make:controller PostController
```

### Step 3: Define the Routes

**User:** Add routes to `routes/web.php` for a `PostController` that handle listing all posts (`/posts`) and showing a single post (`/posts/{post}`).

**Gemini's Action:** Gemini would add the following to `routes/web.php`:

```php
use App\Http\Controllers\PostController;

Route::get('/posts', [PostController::class, 'index']);
Route::get('/posts/{post}', [PostController::class, 'show']);
```

### Step 4: Implement the Controller Logic

**User:** In `PostController`, implement the `index` method to retrieve all posts and pass them to a `posts.index` view. Implement the `show` method to pass a single post to a `posts.show` view.

**Gemini's Action:** Gemini would modify `app/Http/Controllers/PostController.php` to implement the requested logic.

### Step 5: Create the Views

**User:** Create a new Blade view at `resources/views/posts/index.blade.php` to display a list of post titles. Then, create a view at `resources/views/posts/show.blade.php` to display a single post's title and body.

**Gemini's Action:** Gemini would create the two Blade files with basic HTML to display the post data.

This example illustrates how you can break down a larger task into smaller, actionable steps for Gemini to execute.
