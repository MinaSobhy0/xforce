@extends('legal.layout', [
    'pageTitle' => __('legal.privacy.title'),
    'effectiveDate' => '2026-04-25',
])

@section('content')
    @if(app()->getLocale() === 'ar')
        @include('legal.content.privacy-ar')
    @else
        @include('legal.content.privacy-en')
    @endif
@endsection
