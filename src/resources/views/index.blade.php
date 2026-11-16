@extends('web::layouts.grids.12')

@section('title', trans('quicksell::global.title'))
@section('page_header', trans('quicksell::global.title'))

@section('full')
  <div class="card">
    <div class="card-body">
      <p>{{ trans('quicksell::global.coming_soon') }}</p>
    </div>
  </div>
@endsection
