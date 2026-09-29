<?php

/*
|--------------------------------------------------------------------------
| CMS helpers
|--------------------------------------------------------------------------
|
| Global functions the site's Blade templates use to reach CMS content. They
| are thin wrappers over the Cms read API, kept as plain functions because a
| template reads better as `setting('site.email')` than as a facade call.
|
| Loaded by composer's `files` autoloader, so they are available everywhere
| without the host having to import anything. Each is guarded with
| function_exists so a host that already defines one of these names keeps its
| own — the package never clobbers the application.
|
*/

use Cms\Facades\Cms;
use Cms\Support\Media;
use Illuminate\Support\Facades\Log;

if (! function_exists('setting')) {
    /**
     * An editable piece of site chrome, with the wording to fall back on.
     *
     * Defaults are the text the theme shipped with, so an unedited site renders
     * exactly as it did before any of this became editable.
     */
    function setting($key, $default = null)
    {
        return Cms::setting($key, $default);
    }
}

if (! function_exists('site_text')) {
    /**
     * Text with the shared settings written into it: `{phone}`, `{email}`
     * and whatever else `cms.settings.tokens` names.
     *
     * Component content passes through this on its own as the page renders;
     * this is for a hand-written view holding text of its own.
     */
    function site_text($text)
    {
        return \Cms\Settings\Tokens::apply($text);
    }
}

if (! function_exists('menu')) {
    function menu(){

        return (object) Cms::menu();
    }
}

if (! function_exists('footer_menu')) {
    function footer_menu(){

        return (object) Cms::footerMenu();
    }
}

if (! function_exists('category')) {
    function category(){

        return Cms::categories();
    }
}

if (! function_exists('_blogs')) {
    function _blogs(){

        return Cms::blogs();
    }
}

if (! function_exists('pagedata')) {
    function pagedata($data = null, $returnObject = false, $index = -1)
    {
        try {
            if (is_array($data)) {
                $data = (object) $data;
            }
            if ($index > -1) {
                return $data->groups[$index];
            }

            $groups = (object) $data->groups;

            $blocks_data = [];

            foreach ($groups as $group) {
                $blockName = isset($group['name']) ? $group['name'] : null;
                $elements = $group['element'];

                if ($blockName) {
                    if (!isset($blocks_data[$blockName])) {
                        $blocks_data[$blockName] = [];
                    }

                    $elementArray = [];

                    foreach ($elements as $element) {
                        $elementName = $element['name'];
                        $value = $element['value'];

                        $elementArray[$elementName] = (object)$element; // Create an object from the element
                    }

                    $blocks_data[$blockName][] = (object) $elementArray;
                
                }
            }

            if ($returnObject) {
                return (object) $blocks_data;
            }
            return $blocks_data;
        } catch (\Exception $e) {
            Log::info("Error Occurred in pagedata " . $e->getMessage());
            return null;
        }
    }
}

if (! function_exists('groups')) {
    function groups( $data ){

        try
        {
            if(! $data ){ return null; }

            $groups = _value($data, "groups");

            $group_holder = [];

            foreach ($groups as $group){

                if( $g_key = _value($group, "group_name")){

                    $group_holder[$g_key] = _value($group, "elements");
                }
            }

            return $group_holder;

        } catch (\Exception $e){

            Log::info("Error Occurred in blocks" . $e->getMessage());
            return null;
        }
    }
}

if (! function_exists('sort_keys')) {
    function sort_keys(array $data){

        $holder = [];
        foreach ($data as $v){

            foreach ($v as $k){
                $holder[$k["name"]] = $k;
            }
        }
        return $holder;
    }
}

if (! function_exists('cms_image')) {
    /**
     * Resolve a CMS image reference to a usable URL.
     *
     * The CMS stores images as absolute URLs, which breaks as soon as the site is
     * served from a different host (local / staging / live), so the host is always
     * stripped. If the file is missing — an unpublished slot, or a record left over
     * from a previous site — the supplied theme asset is used instead, so a section
     * never renders a broken image.
     *
     * @param  string|null $source   value straight out of the CMS
     * @param  string|null $fallback theme asset path, relative to public/
     * @return string                empty when there is nothing to show
     */
    function cms_image($source = null, $fallback = null)
    {
        $path = preg_replace('#^https?://[^/]+#', '', (string) $source);

        if ($path !== '' && file_exists(public_path($path))) {
            return asset(ltrim($path, '/'));
        }

        return $fallback ? asset($fallback) : '';
    }
}

if (! function_exists('cms_media')) {
    /**
     * The URL for an image a component stores: a path on the CMS media disk,
     * a file under public/, or an absolute URL. Empty when there is nothing
     * to show and no fallback.
     *
     * @param  mixed        $value     what the component stored
     * @param  string|null  $fallback  theme asset path, relative to public/
     */
    function cms_media($value = null, $fallback = null)
    {
        return Media::url($value, $fallback);
    }
}

if (! function_exists('cms_component')) {
    /**
     * Render a library component by handle or id, anywhere in Blade — so a
     * hand-written view can still share a section editors maintain.
     */
    function cms_component($reference, $page = null)
    {
        return Cms::component($reference, $page);
    }
}

if (! function_exists('cms_class')) {
    /**
     * A CSS class the generic components render with; see
     * `cms.rendering.classes`.
     */
    function cms_class($name)
    {
        return config("cms.rendering.classes.{$name}", "cms-{$name}");
    }
}
