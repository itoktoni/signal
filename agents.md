# My Project AGENTS.md

## General Guidelines
- **Language/Framework:** Prefer TypeScript with React and Next.js.
- **Styling:** Use Tailwind CSS for utility-first styling. Avoid custom CSS files where possible.
- **State Management:** Utilize Zustand for global state management.
- **Data Fetching:** Use React Query for data fetching and caching.

## Project Structure
- **Components:** Place reusable UI components in `src/components/`.
- **Pages:** Next.js pages reside in `src/pages/`.
- **API Routes:** Backend API routes are in `src/pages/api/`.
- **Utilities:** Common utility functions should be in `src/lib/`.

## Development Environment
- **this using laravel 12

## Code Style and Formatting
- if we make have if make like
if(1=1)
{

}

## Component Usage Examples
- **Input Component:** `<x-input type="email" name="email" col="12" required/>`
- **Select Component:** `<x-select name="role" :options="['admin' => 'Admin', 'user' => 'User']" required searchable/>`
- **Textarea Component:** `<x-textarea name="description" rows="4" required/>`
- **Footer Component:** `<x-footer> <x-button type="submit" class="primary">Submit</x-button> </x-footer>`
- prefer to make like
<x-input type="email" name="email" col="12" required/>

## don't make like
- <x-input
                    type="email"
                    name="email"
                    col="12"
                    required
                />
