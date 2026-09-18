{{--
    A builder page: the layout's regions around the page's own content,
    rendered inside the layout's shell. $regions arrive already rendered —
    see Cms\Rendering\PageRenderer for why.

    The shell is any view that yields `cms_header`, `content` and `cms_footer`
    (and optionally `title`, `meta_description`, and the `cms_head` and
    `cms_body_end` stacks). See cms::shell for the minimal one.
--}}
@extends($shell)

@section('title', $title)

@if (filled($description))
    @section('meta_description', $description)
@endif

@section('cms_header')
{{ $regions['header'] }}
@endsection

@section('content')
{{ $regions['before'] }}
@if ($published = $page->publishedView())
{{-- Published with cms:publish-page: the file draws the content. --}}
@include($published, ['page' => $page, 'cms' => $context])
@elseif (filled($page->content))
{{ $regions['content'] }}
@elseif ($context->preview)
@include('cms::preview.placeholder')
@endif
{{ $regions['after'] }}
@endsection

@section('cms_footer')
{{ $regions['footer'] }}
@endsection

@if ($context->preview)
    @push('cms_body_end')
        @include('cms::preview.bar')
    @endpush
@endif
