# Project Rules

Laravel + Filament camping and hospitality system. Specs live in `docs/internal/` (gitignored). Read `prd-sistem-hospitality-camping.md` sections 5 and 7 before structural changes.

## 1. No emoji

Zero emoji anywhere: code, comments, Blade views, UI copy, seeders, commit messages, docs, logs. Use text labels and SVG icons (Heroicons / Lucide) instead of emoji characters. Verify before finishing a task by grepping the changed files for emoji ranges.

## 2. Have a taste in design

- Every screen needs a deliberate visual direction: a committed palette, a type pairing, a spacing scale, and one memorable detail. No default-looking templates.
- Avoid generic AI patterns: purple-blue gradients on white, identical card grids, centered hero with two buttons, lorem-style marketing copy ("seamless", "unforgettable").
- Typography: pair a display face with a readable body face, set a modular scale, keep line length 60 to 75 characters.
- Color: define tokens once (CSS variables or Tailwind theme), 1 dominant, 1 accent, neutrals with a slight tint. Check contrast (WCAG AA minimum).
- Layout: use asymmetry and whitespace on purpose, consistent 4/8 px spacing, mobile-first at 360 px.
- Motion: subtle and purposeful (state change, reveal), respect `prefers-reduced-motion`.
- Copy: write in the owner's voice with real facts (tent types, facilities, location, price), in Indonesian for end users.
- Accessibility basics are not optional: labels tied to inputs, focus states, alt text, semantic landmarks.

## 3. Research references before designing

Before building or restyling any UI, find real references first, then write a short note of what was borrowed and why.

- Search the web for 3 to 5 strong references in the same domain (glamping and camping booking, boutique hospitality, restaurant QR menus, admin dashboards). Use real products and design galleries, for example Hipcamp, Under Canvas, Aman, Airbnb, Linear, Stripe Dashboard, Dribbble, Mobbin, Awwwards.
- Record the references and decisions in the PR or commit body, or in `docs/internal/design-notes.md`.
- Never copy assets, logos, or text from references. Borrow structure, rhythm, and principles only.
- For Filament panels, follow Filament conventions first, then customize theme tokens instead of overriding markup.

## 4. Clean code

- Names reveal intent. English identifiers, snake_case for DB, camelCase for variables and methods, PascalCase for classes (see PRD section 7.3 glossary).
- Small functions with one reason to change. No dead code, no commented-out code, no TODO without an issue number.
- Comments explain why, not what.
- No magic numbers or strings: use PHP backed Enums for statuses, config or `settings` for tax, hold minutes, and similar values.
- No duplicated business logic. Pricing lives in one place only.
- Validate input with Form Requests. Never trust prices from the browser.
- Handle errors with specific exceptions, log them, show user-safe messages. No empty catch blocks.
- Money is integer rupiah. Dates and times use Asia/Jakarta.
- Format with Laravel Pint before finishing.

## 5. Clean architecture

- Thin controllers and Filament classes: they translate HTTP or UI input into calls on services and return a response.
- Business rules live in `app/Services` (and Actions where a single use case fits better). Services depend on Eloquent models and interfaces, not on HTTP or Filament.
- Models hold relations, casts, scopes, and simple domain helpers only. No request or UI knowledge.
- Dependencies point inward: UI and HTTP -> Services -> Models. Never the reverse.
- External systems (payment gateway, WhatsApp, fingerprint) sit behind an interface in a service, bound in a provider, so they can be swapped and faked in tests.
- Side effects run in Jobs and Listeners, scheduled through `routes/console.php`.
- Wrap multi-step writes in DB transactions and lock rows where double booking is possible.
- Every P1 feature ships with a feature test.
- One concept, one name, across DB, code, and UI.
