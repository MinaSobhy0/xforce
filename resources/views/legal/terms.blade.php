@extends('legal.layout', [
    'pageTitle' => __('legal.terms.title'),
    'effectiveDate' => '2026-04-25',
])

@section('content')
    @if(app()->getLocale() === 'ar')
        @include('legal.content.terms-ar')
    @else
        @include('legal.content.terms-en')
    @endif
@endsection
