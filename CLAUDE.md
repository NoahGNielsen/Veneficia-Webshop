Last updated 04/11-26
# CLAUDE.md

## Rules (from the project owner)

- **Every change to `database/schema.sql` also goes in its own `.sql` file in `database/migrations/`.** Name it after the
  date and what changed, e.g. `2020-01-01 - Added a new colum with titles.sql` (YY-MM-DD). It must hold only the
  statements needed to bring an existing database up to date (`ALTER TABLE ...` etc.). `schema.sql` itself always holds
  the full, current schema.
- **Automatically make a commit after making changes**, with an appropriate commit message. Existing history uses
  short, lowercase, plain-English messages (`added leaderboard`, `QOL update to matador`).
- `database/migrations/*` is in `.gitignore`, so migrations are never part of a commit.
- **Update sitemap**, when a new site that should be on visible on a search engine is added.
  Make sure to add it to sitemap.xml
- **When the colours in `src/assets/css/mainStyle.css` change, update the header colours too.** The `theme-color`
  metas in `src/assets/php/header.php` (light and dark) and `theme_color`/`background_color` in `src/manifest.json`
  must match mainStyle's `--background`.
- **Functionality first, not looks.** Right now the priority is only that the site works. A CSS designer will later
  restyle the whole site with the proper CSS, so any CSS written now exists only to make pages usable and functional,
  not pretty. Keep it minimal and don't spend effort on visual polish.
- **Security has very high priority.** Always check changes for security holes (SQL injection, XSS, CSRF, auth and
  access-control flaws, unsafe file handling, leaked secrets etc.) and fix them.
- **Page speed has fairly high priority.** Always spend time optimising what you touch (efficient queries, no
  unnecessary requests or heavy assets, caching where it makes sense).