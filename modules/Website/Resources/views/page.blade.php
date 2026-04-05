@extends('website::layouts.website')

@section('content')
    @foreach($blocks as $block)
        @if($block->is_visible)
            @include($block->getViewName(), [
                'block' => $block,
                'content' => $block->content ?? [],
                'settings' => $block->settings ?? [],
                'locale' => $locale,
            ])
        @endif
    @endforeach
@endsection
