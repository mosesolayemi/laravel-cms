<?php

namespace PhynixMedia\Cms\Http\Controllers;

use PhynixMedia\Cms\Cms as CmsApi;
use PhynixMedia\Cms\Facades\Cms;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * Serves a CMS page for any URL the host application has not claimed.
 *
 * Reached through the package's fallback route, so a fresh installation
 * serves its pages with nothing added to the host's routes/web.php. A host
 * that wants its own behaviour — staging gates, extra segments, a different
 * slug scheme — defines a real route instead, which always wins over a
 * fallback, and this is never reached.
 */
class PageController extends Controller
{
    public function __invoke(Request $request): View
    {
        $path = trim($request->path(), '/');

        $url = $path === '' || $path === '/'
            ? (string) config('cms.routing.home', 'home')
            : $path;

        $page = Cms::page($url);

        // Some sites key their pages by the last segment alone rather than by
        // the full path. That is off by default: it makes any unmatched URL
        // able to resolve to a page anywhere in the tree, which is surprising
        // and makes two URLs serve one page.
        if (! $page && config('cms.routing.match_last_segment', false)) {
            $page = Cms::page(last(explode('/', $url)));
        }

        // Unpublished pages stay reachable to editors through the panel's
        // preview, never to visitors.
        abort_if(! $page || (int) $page->status !== CmsApi::PUBLISHED, 404);

        Cms::recordVisit($page->url);

        return Cms::render($page);
    }
}
