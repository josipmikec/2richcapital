# 2Rich.Capital Project Rules & Architecture

You are assisting with the development of the 2Rich.Capital web application. Please adhere strictly to the following architectural guidelines and design principles.

## Tech Stack & Architecture
- **Backend:** Vanilla PHP (No frameworks like Laravel or Symfony).
- **Frontend:** Vanilla JavaScript and Vanilla CSS. Do not use React, Vue, or TailwindCSS.
- **Database:** WordPress backend architecture. We interact directly with custom WP tables (e.g., `wp_rich_signals`, `wp_rich_signal_group_messages`, `wp_rich_journal`).
- **Directory Structure:**
  - `/app/dashboard/` - Main dashboard UI and logic.
  - `/app/api/` - Backend API endpoints returning JSON.
  - `/app/components/` - Reusable PHP components (modals, headers).
  - `/app/assets/` - CSS and JS files.

## Design & Aesthetics (CRITICAL)
- **Visual Excellence:** The app must maintain a highly premium, state-of-the-art aesthetic. 
- **Colors:** Use dark modes with metallic gold/yellow accents (`#f2ca50`, `#e7c36a`). Avoid generic flat colors. Use smooth gradients and glassmorphism.
- **Interactions:** Always include subtle micro-animations, hover effects, and smooth transitions. The UI should feel dynamic and alive.

## Coding Conventions & Known Quirks
- **Timezones & Dates:** All dates injected from the PHP backend to JavaScript are handled via the global `window.formatUserDate(dateStr, mode)` function (defined in `general-settings-modal.php`). 
  - *Never* use `new Date().toLocaleString()` directly for UI rendering. 
  - The PHP backend stores local server time (`current_time('mysql')`). To synchronize properly, the frontend relies on `window.SERVER_OFFSET` which is automatically appended to raw dates before JS parses them.
- **Modals:** Use the existing overlay architecture (e.g., `.general-settings-overlay`). Modals should have backdrop blurs (`backdrop-filter: blur(8px)`) and smooth scale-in transitions.
- **API Responses:** All API endpoints in `/app/api/` should return strict JSON with a `success` boolean (e.g., `echo json_encode(['success' => true, 'data' => $data]);`).

## Workflow
- When modifying large files (like `app/dashboard/index.php`), always use specific regex/grep searches to find the exact lines, and use targeted chunk replacements rather than rewriting the whole file.
- If writing new UI components, always ensure they match the existing premium gold/dark aesthetic before considering the task complete.
