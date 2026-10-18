# Tests

Two checks, both aimed at one question: **does this package work for someone
who requires nothing but this package?**

That question is invisible during development. The package is written inside a
host application, so every class the host happens to require is autoloadable
here whether or not `composer.json` names it. `v1.0.0` shipped
`Intervention\Image` that way — `ImageProcessor` imported it in six places, the
`require` block never asked for it, and it worked only because the first host
application required the library itself. On anyone else's install the class was
simply absent.

```bash
composer test                 # both, in order
composer test:dependencies    # the static one — instant
composer test:install         # the real one — several minutes
```

## `check-dependencies.php` — static

Tokenises every file in `src/`, collects the root namespace of every import and
fully-qualified reference, and fails if one is not accounted for. A namespace
counts as accounted for if it is the package's own, provided by a declared
`require`, or listed in `$transitive` with the dependency that pulls it in.

A new third-party namespace therefore fails the check until someone decides
which it is. Two lists at the top of the file hold the mapping; both are
commented with what belongs where.

It costs nothing to run and would have caught the `v1.0.0` bug on its own.

## `install.sh` — end to end

Builds a throwaway Laravel application with `composer create-project`, requires
the package from the working tree through a path repository, and then checks the
install the way a consumer meets it:

| | |
|---|---|
| Composer | resolves on each supported major; the app requires *only* the package; `intervention/image` arrives anyway |
| `cms:install` | runs, migrates, and is safe to run twice |
| `auth:admin` | creates the first administrator non-interactively |
| Routing | the page route is registered with no `routes/web.php` wiring |
| HTTP | `/` and `/admin/login` both answer 200 from a real server |
| Inside the app | `verify-install.php` runs — see below |

```bash
tests/install.sh                      # every supported major
tests/install.sh 13                   # one of them
tests/install.sh 13 --keep            # leave the app behind to poke at
tests/install.sh 13 --reuse           # reuse a built app: fast, re-installs the package
tests/install.sh --work-dir /path     # build somewhere other than $TMPDIR
```

`--reuse` is for iterating on the package: it skips `create-project`, which is
nearly all of the runtime. It does mean the schema already exists, so the
migration leg proves nothing on a reused app — a cold run is the honest one.

The majors it tests live in `VERSIONS` at the top of the script. **When
`illuminate/support` in `composer.json` gains a major, add it there too** —
otherwise the constraint claims support for a version nothing has checked.

## `fixtures/verify-install.php` — inside the application

Run by `install.sh`, and usable on its own against any installed app:

```bash
php tests/fixtures/verify-install.php /path/to/app
```

It boots the framework properly rather than going through `tinker`, so a
missing class fails the run instead of printing a message. It asserts all 14
tables by name, the published files and the media symlink, that the package
config merged, that a layout was seeded and an administrator exists, and then
puts `ImageProcessor` through a real crop: a deliberately wrong-shaped 900×300
JPEG in, a file on the media disk at the exact preset dimensions out, both
presets via `storeMany()`, and `forget()` twice to confirm the second pass does
not raise on a file already gone.

Run against an app installed with `--no-admin`, the administrator check is the
one that will fail; that is expected.

## Requirements

`composer`, `php` and `curl` on `PATH`, network access for Composer, and the
`gd` extension (or `imagick`, with `cms.images.driver` set to match) — the
package needs one of the two for any image handling, which is why the verifier
checks for it explicitly rather than failing deep inside Intervention.

## What is not covered

No unit tests. The package's units are Filament resources, Eloquent models and
Blade views, which need a booted application to mean anything, and a
Testbench suite would also satisfy its own dependencies from `require-dev` —
hiding exactly the class of bug these two checks exist to find. If unit tests
are added later, they belong beside these rather than instead of them.

Table-name collisions are not covered either. `admins`, `web_blogs` and
`page_visits` are generic enough that an established application may already
have them, and `migrate` would fail; these tests only ever install into a fresh
app, so they will never see it.
