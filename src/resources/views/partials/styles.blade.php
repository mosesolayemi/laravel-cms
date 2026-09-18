{{--
    Just enough styling for the package's generic components when they are
    rendered in cms::shell. A site with its own shell styles them itself, via
    the class names in `cms.rendering.classes`.
--}}
<style>
    .cms-body { margin: 0; font: 16px/1.65 system-ui, -apple-system, "Segoe UI", sans-serif; color: #1f2937; }
    .cms-section { padding: 48px 0; }
    .cms-container { width: 100%; max-width: 1140px; margin: 0 auto; padding: 0 16px; box-sizing: border-box; }
    .cms-container--narrow { max-width: 760px; }
    .cms-container--full { max-width: none; }
    .cms-prose img { max-width: 100%; height: auto; }
    .cms-prose a { color: #2563eb; }
    .cms-align-center { text-align: center; }
    .cms-align-right { text-align: right; }
    .cms-heading__sub { margin-top: .5em; color: #6b7280; }
    .cms-figure { margin: 0; }
    .cms-figure img { display: block; max-width: 100%; height: auto; }
    .cms-figure figcaption { margin-top: 8px; font-size: 14px; color: #6b7280; }
    .cms-buttons { display: flex; flex-wrap: wrap; gap: 12px; }
    .cms-buttons.cms-align-center { justify-content: center; }
    .cms-buttons.cms-align-right { justify-content: flex-end; }
    .cms-button { display: inline-block; padding: 12px 22px; border-radius: 6px; background: #1d4ed8; color: #fff; text-decoration: none; font-weight: 600; }
    .cms-button--secondary { background: transparent; color: #1d4ed8; box-shadow: inset 0 0 0 2px currentColor; }
    .cms-button--link { background: none; color: #1d4ed8; padding-left: 0; padding-right: 0; text-decoration: underline; }
    .cms-spacer--sm { height: 16px; } .cms-spacer--md { height: 40px; } .cms-spacer--lg { height: 80px; } .cms-spacer--xl { height: 120px; }
</style>
